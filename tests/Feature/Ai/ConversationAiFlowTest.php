<?php

use Illuminate\Support\Facades\Event;
use Tabadev\FastHelp\Contracts\SmartReply;
use Tabadev\FastHelp\Enums\ConversationStatus;
use Tabadev\FastHelp\Enums\MessageSender;
use Tabadev\FastHelp\Events\ConversationStarted;
use Tabadev\FastHelp\Events\MessageSent;
use Tabadev\FastHelp\Models\Conversation;
use Tabadev\FastHelp\Services\ConversationService;
use Tabadev\FastHelp\Services\SmartReplyResult;

function bindFakeSmartReply(SmartReplyResult $result): void
{
    app()->bind(SmartReply::class, fn () => new class($result) implements SmartReply
    {
        public function __construct(private SmartReplyResult $result) {}

        public function reply(Conversation $conversation, string $message): SmartReplyResult
        {
            return $this->result;
        }
    });
}

it('creates a bot message when the smart reply answers', function () {
    Event::fake([MessageSent::class]);
    config()->set('fasthelp.ai.enabled', true);
    bindFakeSmartReply(SmartReplyResult::answer('bot says hi'));

    $conversation = Conversation::factory()->create([
        'status' => ConversationStatus::Open,
    ]);

    $service = app(ConversationService::class);
    $service->postClientMessage($conversation, 'hello');

    expect(
        $conversation->messages()
            ->where('sender_type', MessageSender::Bot)
            ->where('body', 'bot says hi')
            ->exists()
    )->toBeTrue();
});

it('flips status to pending when the smart reply requests handoff', function () {
    Event::fake([MessageSent::class, ConversationStarted::class]);
    config()->set('fasthelp.ai.enabled', true);
    bindFakeSmartReply(SmartReplyResult::handoff());

    $conversation = Conversation::factory()->create([
        'status' => ConversationStatus::Open,
    ]);

    $service = app(ConversationService::class);
    $service->postClientMessage($conversation, 'I want a human');

    expect($conversation->refresh()->status)->toBe(ConversationStatus::Pending);
});

it('does not invoke AI or create a bot message when ai is disabled', function () {
    Event::fake([MessageSent::class]);
    config()->set('fasthelp.ai.enabled', false);
    bindFakeSmartReply(SmartReplyResult::answer('should never be used'));

    $conversation = Conversation::factory()->create([
        'status' => ConversationStatus::Open,
    ]);

    $service = app(ConversationService::class);
    $service->postClientMessage($conversation, 'hello');

    expect($conversation->messages()->where('sender_type', MessageSender::Bot)->exists())->toBeFalse()
        ->and($conversation->refresh()->status)->toBe(ConversationStatus::Open);
});
