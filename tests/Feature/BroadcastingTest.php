<?php

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Tabadev\FastHelp\Events\AgentPresenceChanged;
use Tabadev\FastHelp\Events\ConversationStarted;
use Tabadev\FastHelp\Events\MessageSent;
use Tabadev\FastHelp\Events\ParticipantTyping;
use Tabadev\FastHelp\Models\Conversation;
use Tabadev\FastHelp\Models\Message;

it('broadcasts MessageSent on the conversation private channel', function () {
    $c = Conversation::factory()->create();
    $m = Message::factory()->create(['conversation_id' => $c->id]);
    $event = new MessageSent($m);
    expect($event)->toBeInstanceOf(ShouldBroadcast::class);
    $channels = $event->broadcastOn();
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-fasthelp.conversation.'.$c->uuid)
        ->and($event->broadcastAs())->toBe('message.sent')
        ->and($event->broadcastWith())->toHaveKeys(['id', 'conversation_id', 'sender_type', 'body', 'created_at']);
});

it('broadcasts ConversationStarted to the agents private channel', function () {
    $c = Conversation::factory()->create();
    $event = new ConversationStarted($c);
    expect($event->broadcastOn()[0]->name)->toBe('private-fasthelp.agents')
        ->and($event->broadcastAs())->toBe('conversation.started');
});

it('broadcasts AgentPresenceChanged on the public status channel', function () {
    $event = new AgentPresenceChanged(3);
    $ch = $event->broadcastOn()[0];
    expect($ch)->toBeInstanceOf(Channel::class)
        ->and($ch->name)->toBe('fasthelp.status')
        ->and($event->broadcastWith())->toBe(['online_count' => 3]);
});

it('broadcasts ParticipantTyping on the conversation channel', function () {
    $event = new ParticipantTyping('abc-uuid', 'agent');
    expect($event->broadcastOn()[0]->name)->toBe('private-fasthelp.conversation.abc-uuid')
        ->and($event->broadcastWith())->toBe(['who' => 'agent']);
});

it('registers the channel authorization callbacks via the service provider', function () {
    // The provider's boot() requires routes/channels.php, which calls
    // Broadcast::channel() for the agents, presence.agents and
    // conversation.{uuid} channels. If the require had failed, the app
    // would not have booted at all. We additionally assert the callbacks
    // are known to the underlying broadcaster.
    $registrar = app(Factory::class)->driver();

    $registered = (function () {
        return $this->channels;
    })->call($registrar);

    expect(array_keys($registered))->toContain('fasthelp.agents')
        ->and(array_keys($registered))->toContain('fasthelp.presence.agents')
        ->and(array_keys($registered))->toContain('fasthelp.conversation.{uuid}');
});
