<?php

namespace Tabadev\FastHelp\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Tabadev\FastHelp\Models\Visitor;

class VisitorFactory extends Factory
{
    protected $model = Visitor::class;

    public function definition(): array
    {
        return [
            'token' => Str::random(40),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'user_id' => null,
            'last_seen_at' => now(),
            'meta' => [],
        ];
    }
}
