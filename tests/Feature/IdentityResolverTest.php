<?php

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tabadev\FastHelp\Models\Visitor;
use Tabadev\FastHelp\Support\IdentityResolver;

function createTestUser(): Authenticatable
{
    test()->loadMigrationsFrom(
        __DIR__.'/../../vendor/orchestra/testbench-core/laravel/migrations'
    );

    $user = new Authenticatable;
    $user->forceFill([
        'name' => 'Test User',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
    ])->save();

    return $user;
}

it('creates a guest visitor when no cookie is present', function () {
    $identity = app(IdentityResolver::class)->resolve();

    expect($identity->isGuest())->toBeTrue()
        ->and(Visitor::count())->toBe(1)
        ->and($identity->visitor->token)->not->toBeNull();
});

it('resolves the same visitor for the same cookie token', function () {
    $visitor = Visitor::factory()->create();
    request()->cookies->set(config('fasthelp.cookie'), $visitor->token);

    $identity = app(IdentityResolver::class)->resolve();

    expect($identity->visitor->id)->toBe($visitor->id)
        ->and(Visitor::count())->toBe(1);
});

it('links an authenticated user onto the visitor and reports a user identity', function () {
    $visitor = Visitor::factory()->create(['user_id' => null]);
    request()->cookies->set(config('fasthelp.cookie'), $visitor->token);

    $user = createTestUser();
    $this->actingAs($user);

    $identity = app(IdentityResolver::class)->resolve();

    expect($identity->isUser())->toBeTrue()
        ->and($identity->visitor->fresh()->user_id)->toBe($user->getKey());
});
