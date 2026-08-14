<?php

namespace Database\Factories;

use App\Enums\SsoProvider;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserIdentity>
 */
class UserIdentityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => SsoProvider::Authelia,
            'provider_user_id' => fake()->unique()->uuid(),
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
        ];
    }

    /**
     * Bind the identity to an existing user, mirroring their name and email.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
        ]);
    }

    /**
     * Pin the identity to a known OIDC subject.
     */
    public function withSubject(string $subject): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_user_id' => $subject,
        ]);
    }
}
