<?php

namespace App\Actions;

use App\Models\Prompt;
use App\Models\User;

class SyncPromptTags
{
    /**
     * Attach exactly the named tags to the prompt, creating any that are new to the user.
     *
     * @param  array<int, string>  $names
     */
    public function __invoke(Prompt $prompt, User $user, array $names): void
    {
        $ids = array_map(
            fn (string $name): int => $user->tags()->firstOrCreate(['name' => $name])->id,
            $names,
        );

        $prompt->tags()->sync($ids);
    }
}
