<?php

use App\Models\User;

it('creates a user from options', function (): void {
    $this->artisan('pq:create-user', [
        '--name' => 'Jez',
        '--email' => 'jez@example.com',
        '--password' => 'correct-horse-battery-staple',
    ])->assertSuccessful();

    $user = User::where('email', 'jez@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Jez');
});

it('hashes the password rather than storing it raw', function (): void {
    $this->artisan('pq:create-user', [
        '--name' => 'Jez',
        '--email' => 'jez@example.com',
        '--password' => 'correct-horse-battery-staple',
    ])->assertSuccessful();

    $user = User::where('email', 'jez@example.com')->firstOrFail();

    expect($user->password)->not->toBe('correct-horse-battery-staple')
        ->and(Hash::check('correct-horse-battery-staple', $user->password))->toBeTrue();
});

it('rejects a duplicate email', function (): void {
    User::factory()->create(['email' => 'jez@example.com']);

    $this->artisan('pq:create-user', [
        '--name' => 'Jez',
        '--email' => 'jez@example.com',
        '--password' => 'correct-horse-battery-staple',
    ])->assertFailed();

    expect(User::where('email', 'jez@example.com')->count())->toBe(1);
});

it('rejects an invalid email', function (): void {
    $this->artisan('pq:create-user', [
        '--name' => 'Jez',
        '--email' => 'not-an-email',
        '--password' => 'correct-horse-battery-staple',
    ])->assertFailed();

    expect(User::count())->toBe(0);
});
