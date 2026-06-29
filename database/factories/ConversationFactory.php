<?php

namespace Tabadev\FastHelp\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tabadev\FastHelp\Enums\ConversationStatus;
use Tabadev\FastHelp\Models\Conversation;

class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'status' => ConversationStatus::Open,
            'visitor_name' => fake()->name(),
            'visitor_email' => fake()->safeEmail(),
            'current_url' => fake()->url(),
            'meta' => [],
        ];
    }
}
