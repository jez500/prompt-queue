<?php

namespace App\Http\Controllers;

use App\Enums\PromptPriority;
use App\Enums\PromptStatus;
use App\Http\Requests\PromptIndexRequest;
use App\Http\Requests\PromptStoreRequest;
use App\Http\Resources\PromptResource;
use App\Models\Prompt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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
            ->with('tags')
            ->orderBy('position')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('prompts/Index', [
            'prompts' => PromptResource::collection($prompts)->resolve(),
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
                'body' => $request->string('body')->toString(),
                'status' => PromptStatus::Todo,
                'priority' => PromptPriority::Normal,
                'position' => 0,
            ]);
        });

        return back();
    }
}
