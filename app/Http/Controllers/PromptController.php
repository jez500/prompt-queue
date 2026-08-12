<?php

namespace App\Http\Controllers;

use App\Enums\PromptPriority;
use App\Enums\PromptStatus;
use App\Http\Requests\PromptStoreRequest;
use App\Models\Prompt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PromptController extends Controller
{
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
