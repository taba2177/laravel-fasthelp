<?php

namespace Tabadev\FastHelp\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tabadev\FastHelp\Enums\MessageSender;
use Tabadev\FastHelp\Models\Conversation;
use Tabadev\FastHelp\Models\Message;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_type' => MessageSender::Client,
            'sender_id' => null,
            'body' => fake()->sentence(),
            'attachments' => [],
        ];
    }
}
