<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\QueryException;

test('a tag belongs to a user', function () {
    $user = User::factory()->create();

    $tag = Tag::factory()->forUser($user)->create(['name' => 'refactor']);

    expect($tag->user->is($user))->toBeTrue()
        ->and($user->tags()->count())->toBe(1);
});

test('a user cannot have two tags with the same name', function () {
    $user = User::factory()->create();

    Tag::factory()->forUser($user)->create(['name' => 'bug']);

    expect(fn () => Tag::factory()->forUser($user)->create(['name' => 'bug']))
        ->toThrow(QueryException::class);
});
