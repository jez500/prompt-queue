<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password as promptPassword;
use function Laravel\Prompts\text;

/**
 * Creates a login for a self-hosted instance.
 *
 * Registration is deliberately closed (see config/fortify.php), so a fresh
 * container has no way to make its first user through the UI. This is that
 * way in — documented in the README as the step after `docker compose up`.
 */
class CreateUserCommand extends Command
{
    /** @var string */
    protected $signature = 'pq:create-user
        {--name= : The display name}
        {--email= : The email address used to log in}
        {--password= : The password (prompted for when omitted)}';

    /** @var string */
    protected $description = 'Create a Prompt Queue user';

    public function handle(): int
    {
        $name = $this->option('name') ?? text(
            label: 'Name',
            required: true,
        );

        $email = $this->option('email') ?? text(
            label: 'Email',
            required: true,
        );

        $password = $this->option('password') ?? promptPassword(
            label: 'Password',
            required: true,
        );

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
                'password' => ['required', 'string', Password::default()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->components->info("User [{$user->email}] created.");

        return self::SUCCESS;
    }
}
