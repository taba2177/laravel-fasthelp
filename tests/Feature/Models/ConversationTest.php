<?php

use Tabadev\FastHelp\Enums\ConversationStatus;
use Tabadev\FastHelp\Models\Conversation;

it('creates a conversation with a uuid and casts status', function () {
    $c = Conversation::create(['status' => ConversationStatus::Open]);
    expect($c->uuid)->not->toBeNull()
        ->and($c->status)->toBe(ConversationStatus::Open);
});

it('has many messages', function () {
    $c = Conversation::create(['status' => ConversationStatus::Open]);
    $c->messages()->create(['sender_type' => 'client', 'body' => 'hi']);
    expect($c->messages)->toHaveCount(1);
});
