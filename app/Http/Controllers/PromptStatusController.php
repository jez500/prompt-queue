<?php

namespace App\Http\Controllers;

use App\Enums\PromptStatus;
use App\Http\Requests\PromptStatusRequest;
use App\Models\Prompt;
use Illuminate\Http\RedirectResponse;

class PromptStatusController extends Controller
{
    /**
     * Set a requested status or advance a freshly copied prompt to implementing.
     */
    public function __invoke(PromptStatusRequest $request, Prompt $prompt): RedirectResponse
    {
        $status = $request->status();

        if ($status !== null) {
            $prompt->update(['status' => $status]);

            return back();
        }

        if ($prompt->status === PromptStatus::Todo) {
            $prompt->update(['status' => PromptStatus::Implementing]);
        }

        return back();
    }
}
