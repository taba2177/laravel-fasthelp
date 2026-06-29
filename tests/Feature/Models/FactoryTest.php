<?php

use Tabadev\FastHelp\Models\AgentStatus;
use Tabadev\FastHelp\Models\Conversation;
use Tabadev\FastHelp\Models\Message;
use Tabadev\FastHelp\Models\Visitor;

it('builds a conversation with messages via factories', function () {
    $c = Conversation::factory()->has(Message::factory()->count(3))->create();
    expect($c->exists)->toBeTrue()
        ->and($c->messages)->toHaveCount(3)
        ->and($c->uuid)->not->toBeNull();
});

it('builds visitors and agent statuses', function () {
    expect(Visitor::factory()->create()->exists)->toBeTrue()
        ->and(AgentStatus::factory()->create()->exists)->toBeTrue();
});
