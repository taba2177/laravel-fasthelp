<?php

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire as LivewireTester;
use Tabadev\FastHelp\Enums\AgentPresence;
use Tabadev\FastHelp\Enums\ConversationStatus;
use Tabadev\FastHelp\Enums\MessageSender;
use Tabadev\FastHelp\Events\MessageSent;
use Tabadev\FastHelp\Livewire\AgentChat;
use Tabadev\FastHelp\Models\AgentStatus;
use Tabadev\FastHelp\Models\Conversation;

function makeAgentChatUser(): Authenticatable
{
    test()->loadMigrationsFrom(
        __DIR__.'/../../../vendor/orchestra/testbench-core/laravel/migrations'
    );

    $user = new Authenticatable;
    $user->forceFill([
        'name' => 'Live Agent',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
    ])->save();

    return $user;
}

it('sends an agent message, assigns the conversation, and dispatches MessageSent', function () {
    Event::fake([MessageSent::class]);

    $agent = makeAgentChatUser();
    $this->actingAs($agent);

    $conversation = Conversation::factory()->create([
        'status' => ConversationStatus::Open,
        'assigned_agent_id' => null,
    ]);

    LivewireTester::test(AgentChat::class, ['uuid' => $conversation->uuid])
        ->set('body', 'Hello from the agent')
        ->call('send')
        ->assertSet('body', '');

    $message = $conversation->messages()->latest('id')->first();

    expect($message)->not->toBeNull()
        ->and($message->sender_type)->toBe(MessageSender::Agent)
        ->and((int) $message->sender_id)->toBe((int) $agent->getKey())
        ->and($message->body)->toBe('Hello from the agent');

    $conversation->refresh();

    expect((int) $conversation->assigned_agent_id)->toBe((int) $agent->getKey())
        ->and($conversation->status)->toBe(ConversationStatus::Assigned);

    Event::assertDispatched(MessageSent::class);
});

it('marks the agent online and sets onlineCount on mount', function () {
    $agent = makeAgentChatUser();
    $this->actingAs($agent);

    $conversation = Conversation::factory()->create();

    LivewireTester::test(AgentChat::class, ['uuid' => $conversation->uuid])
        ->assertSet('onlineCount', 1);

    $status = AgentStatus::where('user_id', $agent->getKey())->first();

    expect($status)->not->toBeNull()
        ->and($status->status)->toBe(AgentPresence::Online);
});

it('loads existing messages on mount as plain arrays', function () {
    $agent = makeAgentChatUser();
    $this->actingAs($agent);

    $conversation = Conversation::factory()->create();
    $conversation->messages()->create([
        'sender_type' => MessageSender::Client,
        'sender_id' => null,
        'body' => 'I need help',
    ]);

    $component = LivewireTester::test(AgentChat::class, ['uuid' => $conversation->uuid]);

    $messages = $component->get('messages');

    expect($messages)->toHaveCount(1)
        ->and($messages[0]['body'])->toBe('I need help')
        ->and($messages[0]['sender_type'])->toBe('client');
});

it('returns the expected echo-private listener for the conversation', function () {
    $agent = makeAgentChatUser();
    $this->actingAs($agent);

    $conversation = Conversation::factory()->create();

    $component = LivewireTester::test(AgentChat::class, ['uuid' => $conversation->uuid]);

    expect($component->instance()->getListeners())->toHaveKey(
        'echo-private:fasthelp.conversation.'.$conversation->uuid.',.message.sent'
    );
});

it('marks the conversation resolved via markResolved', function () {
    $agent = makeAgentChatUser();
    $this->actingAs($agent);

    $conversation = Conversation::factory()->create(['status' => ConversationStatus::Open]);

    LivewireTester::test(AgentChat::class, ['uuid' => $conversation->uuid])
        ->call('markResolved');

    expect($conversation->refresh()->status)->toBe(ConversationStatus::Resolved);
});
