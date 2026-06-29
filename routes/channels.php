<?php

use Illuminate\Support\Facades\Broadcast;
use Tabadev\FastHelp\Models\Conversation;
use Tabadev\FastHelp\Support\AgentResolver;

$prefix = config('fasthelp.broadcasting.channel_prefix', 'fasthelp');

// Public "{prefix}.status" channel needs no authorization entry — public
// channels are not authorized, they only carry the agent-availability count.

// Presence channel: live agent roster for the agent dashboard.
Broadcast::channel($prefix.'.presence.agents', function ($user) {
    if (! app(AgentResolver::class)->isAgent($user)) {
        return false;
    }

    return [
        'id' => $user->getKey(),
        'name' => $user->name ?? null,
    ];
});

// Private channel: agent notifications (new conversations).
Broadcast::channel($prefix.'.agents', function ($user) {
    return app(AgentResolver::class)->isAgent($user);
});

// Private channel: messages + typing for a single conversation.
// Authorized for agents OR the authenticated owning user.
Broadcast::channel($prefix.'.conversation.{uuid}', function ($user, $uuid) {
    $conversation = Conversation::where('uuid', $uuid)->first();

    if (! $conversation) {
        return false;
    }

    $isAgent = app(AgentResolver::class)->isAgent($user);

    $isOwningUser = $conversation->client_type === get_class($user)
        && (int) $conversation->client_id === (int) $user->getKey();

    return $isAgent || $isOwningUser;
});
