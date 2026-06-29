<?php

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Event;
use Tabadev\FastHelp\Enums\ConversationStatus;
use Tabadev\FastHelp\Enums\MessageSender;
use Tabadev\FastHelp\Events\ConversationStarted;
use Tabadev\FastHelp\Events\MessageSent;
use Tabadev\FastHelp\Models\Conversation;
use Tabadev\FastHelp\Models\Visitor;
use Tabadev\FastHelp\Services\ConversationService;
use Tabadev\FastHelp\Support\Identity;

function makeTestUser(): Authenticatable
{
    test()->loadMigrationsFrom(
        __DIR__.'/../../vendor/orchestra/testbench-core/laravel/migrations'
    );

    $user = new Authenticatable;
    $user->forceFill([
        'name' => 'Test Agent',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
    ])->save();

    return $user;
}

it('starts a conversation for a guest identity', function () {
    Event::fake([ConversationStarted::class]);

    $visitor = Visitor::factory()->create([
        'name' => 'Jane Guest',
        'email' => 'jane@example.com',
    ]);
    $identity = new Identity($visitor);

    $service = app(ConversationService::class);
    $conversation = $service->start($identity, 'https://example.com/page');

    expect($conversation->exists)->toBeTrue()
        ->and($conversation->status)->toBe(ConversationStatus::Open)
        ->and($conversation->visitor_id)->toBe($visitor->id)
        ->and($conversation->visitor_name)->toBe('Jane Guest')
        ->and($conversation->visitor_email)->toBe('jane@example.com')
        ->and($conversation->current_url)->toBe('https://example.com/page')
        ->and($conversation->client_type)->toBeNull()
        ->and($conversation->client_id)->toBeNull()
        ->and($conversation->last_message_at)->not->toBeNull();

    Event::assertDispatched(ConversationStarted::class, function ($event) use ($conversation) {
        return $event->conversation->is($conversation);
    });
});

it('starts a conversation for a user identity and associates the client morph', function () {
    Event::fake([ConversationStarted::class]);

    $visitor = Visitor::factory()->create();
    $user = makeTestUser();
    $identity = new Identity($visitor, $user);

    $service = app(ConversationService::class);
    $conversation = $service->start($identity);

    expect($conversation->client_type)->toBe($user->getMorphClass())
        ->and($conversation->client_id)->toBe($user->getKey())
        ->and($conversation->client)->not->toBeNull()
        ->and($conversation->client->is($user))->toBeTrue();

    Event::assertDispatched(ConversationStarted::class);
});

it('stores a client message and bumps last_message_at', function () {
    Event::fake([MessageSent::class]);

    $conversation = Conversation::factory()->create([
        'last_message_at' => now()->subDay(),
    ]);

    $service = app(ConversationService::class);
    $message = $service->postClientMessage($conversation, 'Hello, I need help');

    expect($message->exists)->toBeTrue()
        ->and($message->sender_type)->toBe(MessageSender::Client)
        ->and($message->sender_id)->toBeNull()
        ->and($message->body)->toBe('Hello, I need help')
        ->and($message->conversation_id)->toBe($conversation->id)
        ->and($conversation->refresh()->last_message_at->greaterThan(now()->subMinute()))->toBeTrue();

    Event::assertDispatched(MessageSent::class, function ($event) use ($message) {
        return $event->message->is($message)
            && $event->message->conversation !== null
            && $event->message->conversation->uuid === $message->conversation->uuid;
    });
});

it('stores an agent message and assigns the conversation when unassigned', function () {
    Event::fake([MessageSent::class]);

    $conversation = Conversation::factory()->create([
        'assigned_agent_id' => null,
        'status' => ConversationStatus::Open,
    ]);

    $service = app(ConversationService::class);
    $message = $service->postAgentMessage($conversation, 42, 'How can I help you today?');

    expect($message->sender_type)->toBe(MessageSender::Agent)
        ->and($message->sender_id)->toBe(42)
        ->and($message->body)->toBe('How can I help you today?')
        ->and($conversation->refresh()->assigned_agent_id)->toBe(42)
        ->and($conversation->status)->toBe(ConversationStatus::Assigned);

    Event::assertDispatched(MessageSent::class);
});

it('does not reassign an agent message on an already assigned conversation', function () {
    Event::fake([MessageSent::class]);

    $conversation = Conversation::factory()->create([
        'assigned_agent_id' => 7,
        'status' => ConversationStatus::Assigned,
    ]);

    $service = app(ConversationService::class);
    $service->postAgentMessage($conversation, 99, 'Following up');

    expect($conversation->refresh()->assigned_agent_id)->toBe(7)
        ->and($conversation->status)->toBe(ConversationStatus::Assigned);
});

it('requests human handoff by setting status to pending and dispatching ConversationStarted', function () {
    Event::fake([ConversationStarted::class]);

    $conversation = Conversation::factory()->create([
        'status' => ConversationStatus::Open,
    ]);

    $service = app(ConversationService::class);
    $service->requestHumanHandoff($conversation);

    expect($conversation->refresh()->status)->toBe(ConversationStatus::Pending);

    Event::assertDispatched(ConversationStarted::class, function ($event) use ($conversation) {
        return $event->conversation->is($conversation);
    });
});

it('resolves a conversation and stores the rating', function () {
    $conversation = Conversation::factory()->create([
        'status' => ConversationStatus::Assigned,
        'rating' => null,
    ]);

    $service = app(ConversationService::class);
    $service->resolve($conversation, 5);

    expect($conversation->refresh()->status)->toBe(ConversationStatus::Resolved)
        ->and($conversation->rating)->toBe(5);
});

it('resolves a conversation without a rating leaving rating untouched', function () {
    $conversation = Conversation::factory()->create([
        'status' => ConversationStatus::Assigned,
        'rating' => null,
    ]);

    $service = app(ConversationService::class);
    $service->resolve($conversation);

    expect($conversation->refresh()->status)->toBe(ConversationStatus::Resolved)
        ->and($conversation->rating)->toBeNull();
});
