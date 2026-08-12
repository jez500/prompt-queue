<?php

namespace App\Policies;

use App\Models\Prompt;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PromptPolicy
{
    /**
     * Determine whether the user can update the prompt.
     */
    public function update(User $user, Prompt $prompt): Response
    {
        return $this->owns($user, $prompt);
    }

    /**
     * Determine whether the user can delete the prompt.
     */
    public function delete(User $user, Prompt $prompt): Response
    {
        return $this->owns($user, $prompt);
    }

    /**
     * Deny as not found so a stranger cannot confirm the prompt exists.
     */
    private function owns(User $user, Prompt $prompt): Response
    {
        return $user->id === $prompt->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
