<?php

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Tabadev\FastHelp\Support\AgentResolver;

function makeAgentResolverTestUser(): Authenticatable
{
    test()->loadMigrationsFrom(
        __DIR__.'/../../vendor/orchestra/testbench-core/laravel/migrations'
    );

    $user = new Authenticatable;
    $user->forceFill([
        'name' => 'Test Agent',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
    ])->save();

    return $user;
}

it('returns false for a null user', function () {
    expect(app(AgentResolver::class)->isAgent(null))->toBeFalse();
});

it('returns true by default when the user id exists in fasthelp_agents', function () {
    $user = makeAgentResolverTestUser();

    DB::table('fasthelp_agents')->insert([
        'user_id' => $user->getAuthIdentifier(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(app(AgentResolver::class)->isAgent($user))->toBeTrue();
});

it('returns false by default when the user id is absent from fasthelp_agents', function () {
    $user = makeAgentResolverTestUser();

    expect(app(AgentResolver::class)->isAgent($user))->toBeFalse();
});

it('lets a configured resolver callback override the database check', function () {
    $user = makeAgentResolverTestUser();

    config(['fasthelp.agents.resolver' => fn ($u) => true]);

    expect(app(AgentResolver::class)->isAgent($user))->toBeTrue();

    config(['fasthelp.agents.resolver' => fn ($u) => false]);

    expect(app(AgentResolver::class)->isAgent($user))->toBeFalse();
});
