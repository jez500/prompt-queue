<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectStoreRequest;
use App\Http\Requests\ProjectUpdateRequest;
use App\Models\Project;
use App\Models\Prompt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ProjectController extends Controller
{
    /**
     * Create a project for the current user.
     */
    public function store(ProjectStoreRequest $request): RedirectResponse
    {
        $request->user()->projects()->create($request->validated());

        return back();
    }

    /**
     * Rename or recolour a project.
     */
    public function update(ProjectUpdateRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $project->update($request->validated());

        return back();
    }

    /**
     * Delete a project, returning its prompts to the Inbox rather than destroying them.
     */
    public function destroy(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        DB::transaction(function () use ($request, $project): void {
            $offset = (int) Prompt::query()
                ->whereBelongsTo($request->user())
                ->inBucket(null)
                ->max('position');

            $project->prompts()->orderBy('position')->get()
                ->each(function (Prompt $prompt, int $index) use ($offset): void {
                    $prompt->update([
                        'project_id' => null,
                        'position' => $offset + $index + 1,
                    ]);
                });

            $project->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project deleted. Its prompts now have no project.')]);

        return redirect()->route('prompts.index');
    }
}
