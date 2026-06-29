<?php

namespace Tabadev\FastHelp\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tabadev\FastHelp\Enums\AgentPresence;
use Tabadev\FastHelp\Models\AgentStatus;

class AgentStatusFactory extends Factory
{
    protected $model = AgentStatus::class;

    public function definition(): array
    {
        return [
            'user_id' => fake()->unique()->numberBetween(1, 100000),
            'status' => AgentPresence::Offline,
            'last_seen_at' => now(),
        ];
    }
}
