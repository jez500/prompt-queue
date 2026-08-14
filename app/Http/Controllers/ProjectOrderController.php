<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectReorderRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ProjectOrderController extends Controller
{
    /**
     * Rewrite every project position to match the order supplied by the client.
     */
    public function __invoke(ProjectReorderRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            foreach ($request->projectIds() as $position => $id) {
                Project::query()
                    ->whereBelongsTo($request->user())
                    ->whereKey($id)
                    ->update(['position' => $position]);
            }
        });

        return back();
    }
}
