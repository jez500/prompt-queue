<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromptReorderRequest;
use App\Models\Prompt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PromptOrderController extends Controller
{
    /**
     * Rewrite every position in one bucket to match the order supplied by the client.
     */
    public function __invoke(PromptReorderRequest $request): RedirectResponse
    {
        $projectId = $request->bucketProjectId();

        DB::transaction(function () use ($request, $projectId): void {
            foreach ($request->promptIds() as $position => $id) {
                Prompt::query()
                    ->whereBelongsTo($request->user())
                    ->inBucket($projectId)
                    ->whereKey($id)
                    ->update(['position' => $position]);
            }
        });

        return back();
    }
}
