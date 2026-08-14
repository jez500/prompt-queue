<?php

namespace App\Http\Controllers;

use App\Actions\SyncPromptTags;
use App\Enums\PromptPriority;
use App\Enums\PromptStatus;
use App\Http\Requests\PromptIndexRequest;
use App\Http\Requests\PromptStoreRequest;
use App\Http\Requests\PromptUpdateRequest;
use App\Http\Resources\PromptResource;
use App\Models\Prompt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PromptController extends Controller
{
    /**
     * Show the prompt workbench.
     */
    public function index(PromptIndexRequest $request): Response
    {
        $prompts = Prompt::query()
            ->whereBelongsTo($request->user())
            ->when($request->hasBucket(), fn (Builder $query) => $query->inBucket($request->bucketProjectId()))
            ->search($request->searchTerm())
            ->withStatuses($request->statuses())
            ->withPriorities($request->priorities())
            ->withTagNames($request->tagNames())
            ->with(['project', 'tags'])
            ->orderBy('position')
            ->orderByDesc('id')
            ->get();

        /*
          The editor's prompt is the only one whose body is sent. It is
          resolved here rather than on the client so a link to ?prompt=<id>
          opens that prompt directly.
        */
        $selected = $prompts->firstWhere('id', $request->selectedPromptId())
            ?? $prompts->first();

        return Inertia::render('prompts/Index', [
            'prompts' => PromptResource::collection($prompts)->resolve(),
            'selected' => $selected
                ? (new PromptResource($selected))->withBody()->resolve()
                : null,
            'canReorder' => $request->canReorder(),
            'filters' => [
                'project' => $request->string('project')->toString() ?: null,
                'q' => $request->searchTerm(),
                'status' => array_map(fn (PromptStatus $status): string => $status->value, $request->statuses()),
                'priority' => array_map(fn (PromptPriority $priority): string => $priority->value, $request->priorities()),
                'tags' => $request->tagNames(),
            ],
        ]);
    }

    /**
     * Capture a new prompt at the top of its bucket.
     */
    public function store(PromptStoreRequest $request): RedirectResponse
    {
        $projectId = $request->bucketProjectId();

        DB::transaction(function () use ($request, $projectId): void {
            Prompt::query()
                ->whereBelongsTo($request->user())
                ->inBucket($projectId)
                ->increment('position');

            $request->user()->prompts()->create([
                'project_id' => $projectId,
                'title' => $request->title(),
                'body' => $request->string('body')->toString(),
                'status' => $request->status(),
                'priority' => $request->priority(),
                'position' => 0,
            ]);
        });

        return back();
    }

    /**
     * Update a prompt, moving it between projects and syncing its tags.
     */
    public function update(PromptUpdateRequest $request, Prompt $prompt, SyncPromptTags $syncTags): RedirectResponse
    {
        Gate::authorize('update', $prompt);

        $targetProjectId = $request->bucketProjectId();
        $movesProject = $request->shouldMoveProject() && $targetProjectId !== $prompt->project_id;

        DB::transaction(function () use ($request, $prompt, $targetProjectId, $movesProject, $syncTags): void {
            $attributes = $request->fillableAttributes();

            if ($movesProject) {
                Prompt::query()
                    ->whereBelongsTo($request->user())
                    ->inBucket($targetProjectId)
                    ->increment('position');

                $attributes['project_id'] = $targetProjectId;
                $attributes['position'] = 0;
            }

            $prompt->update($attributes);

            if ($request->shouldSyncTags()) {
                $syncTags($prompt, $request->user(), $request->tagNames());
            }
        });

        return back();
    }

    /**
     * Delete a prompt.
     */
    public function destroy(Request $request, Prompt $prompt): RedirectResponse
    {
        Gate::authorize('delete', $prompt);

        $prompt->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Prompt deleted.')]);

        return back();
    }
}
