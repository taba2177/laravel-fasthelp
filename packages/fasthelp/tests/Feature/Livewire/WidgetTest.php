<?php

use Livewire\Livewire as LivewireTester;
use Tabadev\FastHelp\Contracts\SmartReply;
use Tabadev\FastHelp\Enums\ConversationStatus;
use Tabadev\FastHelp\Enums\MessageSender;
use Tabadev\FastHelp\Livewire\Widget;
use Tabadev\FastHelp\Models\Conversation;
use Tabadev\FastHelp\Services\PresenceService;
use Tabadev\FastHelp\Services\SmartReplyResult;

function bindFakeSmartReplyForWidget(SmartReplyResult $result): void
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

it('sets onlineCount from PresenceService on mount', function () {
    app(PresenceService::class)->markOnline(501);

    LivewireTester::test(Widget::class)
        ->assertSet('onlineCount', 1);
});

it('creates a client message and populates messages when sending', function () {
    LivewireTester::test(Widget::class)
        ->set('body', 'Hello there')
        ->call('sendMessage')
        ->assertSet('body', '');

    $conversation = Conversation::query()->first();

    expect($conversation)->not->toBeNull()
        ->and(
            $conversation->messages()
                ->where('sender_type', MessageSender::Client)
                ->where('body', 'Hello there')
                ->exists()
        )->toBeTrue();
});

it('shows the bot reply in messages when AI is enabled', function () {
    config(['fasthelp.ai.enabled' => true]);
    bindFakeSmartReplyForWidget(SmartReplyResult::answer('hi from bot'));

    $component = LivewireTester::test(Widget::class)
        ->set('body', 'I need help')
        ->call('sendMessage');

    $messages = $component->get('messages');

    $botMessage = collect($messages)->firstWhere('sender_type', 'bot');

    expect($botMessage)->not->toBeNull()
        ->and($botMessage['body'])->toBe('hi from bot');
});

it('sets the conversation status to pending when human handoff is requested', function () {
    $component = LivewireTester::test(Widget::class)
        ->set('body', 'Hello')
        ->call('sendMessage')
        ->call('requestHuman');

    expect($component->get('status'))->toBe('pending');

    $conversation = Conversation::query()->first();

    expect($conversation->status)->toBe(ConversationStatus::Pending);
});

it('returns the echo-private listener once a conversation exists', function () {
    $component = LivewireTester::test(Widget::class);

    expect($component->instance()->getListeners())->toBe([]);

    $component->set('body', 'Hello')->call('sendMessage');

    $conversation = Conversation::query()->first();

    $listeners = $component->instance()->getListeners();

    expect($listeners)->toHaveKey(
        'echo-private:fasthelp.conversation.'.$conversation->uuid.',.message.sent'
    );
});
