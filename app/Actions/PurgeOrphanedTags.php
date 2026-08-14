<?php

namespace App\Actions;

use App\Models\User;

class PurgeOrphanedTags
{
    /**
     * Delete the user's tags that no longer sit on any prompt.
     *
     * Tags are created by typing them onto a prompt and there is no screen
     * for managing them, so a tag that outlives its last prompt would sit in
     * the filter bar forever with no way to remove it. A soft-deleted prompt
     * counts as gone — it is not on any screen, so neither should its tags
     * be.
     */
    public function __invoke(User $user): void
    {
        $user->tags()->whereDoesntHave('prompts')->delete();
    }
}
