<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromptPriorityRequest;
use App\Models\Prompt;
use Illuminate\Http\RedirectResponse;

class PromptPriorityController extends Controller
{
    /**
     * Set a prompt's priority.
     */
    public function __invoke(PromptPriorityRequest $request, Prompt $prompt): RedirectResponse
    {
        $prompt->update(['priority' => $request->priority()]);

        return back();
    }
}
