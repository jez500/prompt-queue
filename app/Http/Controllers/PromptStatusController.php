<?php

namespace App\Http\Controllers;

use App\Enums\PromptStatus;
use App\Models\Prompt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PromptStatusController extends Controller
{
    /**
     * Advance a freshly copied prompt from todo to implementing.
     *
     * Any other status is left alone: re-copying something mid-flight is not a
     * state change, and re-copying a finished prompt must not resurrect it.
     */
    public function __invoke(Request $request, Prompt $prompt): RedirectResponse
    {
        Gate::authorize('update', $prompt);

        if ($prompt->status === PromptStatus::Todo) {
            $prompt->update(['status' => PromptStatus::Implementing]);
        }

        return back();
    }
}
