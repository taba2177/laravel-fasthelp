<?php

use Tabadev\FastHelp\Enums\AgentPresence;
use Tabadev\FastHelp\Models\AgentStatus;

it('creates an agent status and casts status to the AgentPresence enum', function () {
    $status = AgentStatus::create([
        'user_id' => 1,
        'status' => AgentPresence::Online,
    ]);

    expect($status->status)->toBe(AgentPresence::Online)
        ->and($status->user_id)->toBe(1);
});
