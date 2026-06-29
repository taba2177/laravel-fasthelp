<?php

namespace Tabadev\FastHelp\Livewire;

use Livewire\Component;
use Tabadev\FastHelp\Models\Conversation;
use Tabadev\FastHelp\Services\ConversationService;
use Tabadev\FastHelp\Services\PresenceService;
use Tabadev\FastHelp\Support\IdentityResolver;
use Tabadev\FastHelp\Support\Settings;

class Widget extends Component
{
    public bool $open = false;

    public ?string $conversationUuid = null;

    public array $messages = [];

    public string $body = '';

    public int $onlineCount = 0;

    public string $status = 'open';

    public ?string $pageUrl = null;

    public function mount(): void
    {
        $this->onlineCount = app(PresenceService::class)->onlineCount();
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;

        if ($this->open && $this->conversationUuid === null) {
            $this->ensureConversation();
            $this->loadMessages();
        }
    }

    public function ensureConversation(): Conversation
    {
        if ($this->conversationUuid === null) {
            $identity = app(IdentityResolver::class)->resolve();

            $conversation = app(ConversationService::class)->start(
                $identity,
                $this->pageUrl ?? request()->headers->get('referer')
            );

            $this->conversationUuid = $conversation->uuid;
            $this->status = $conversation->status->value;

            return $conversation;
        }

        return Conversation::query()->where('uuid', $this->conversationUuid)->firstOrFail();
    }

    public function sendMessage(): void
    {
        $this->validate([
            'body' => 'required|string|max:5000',
        ]);

        $conversation = $this->ensureConversation();

        app(ConversationService::class)->postClientMessage($conversation, $this->body);

        $this->body = '';
        $this->status = $conversation->refresh()->status->value;
        $this->loadMessages();
    }

    public function requestHuman(): void
    {
        $conversation = $this->ensureConversation();

        app(ConversationService::class)->requestHumanHandoff($conversation);

        $this->status = $conversation->refresh()->status->value;
        $this->loadMessages();
    }

    public function loadMessages(): void
    {
        if ($this->conversationUuid === null) {
            $this->messages = [];

            return;
        }

        $conversation = Conversation::query()->where('uuid', $this->conversationUuid)->first();

        if (! $conversation) {
            $this->messages = [];

            return;
        }

        $this->messages = $conversation->messages()
            ->orderBy('id')
            ->get()
            ->map(fn ($message) => [
                'id' => $message->id,
                'sender_type' => $message->sender_type->value,
                'body' => $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
            ])
            ->all();
    }

    public function onMessageReceived($payload = null): void
    {
        $this->loadMessages();
    }

    public function getListeners(): array
    {
        if ($this->conversationUuid === null) {
            return [];
        }

        return [
            'echo-private:'.$this->channelName().',.message.sent' => 'onMessageReceived',
        ];
    }

    protected function channelName(): string
    {
        return config('fasthelp.broadcasting.channel_prefix', 'fasthelp').'.conversation.'.$this->conversationUuid;
    }

    public function render()
    {
        $settings = app(Settings::class);

        return view('fasthelp::livewire.widget', [
            'widgetEnabled' => (bool) $settings->get('widget.enabled', true),
            'widgetTitle' => $settings->get('widget.title', 'Need help?'),
            'widgetGreeting' => $settings->get('widget.greeting', 'Hi! How can we help you today?'),
            'widgetPosition' => $settings->get('widget.position', 'bottom-right'),
            'widgetColors' => [
                'primary' => $settings->get('widget.colors.primary'),
                'on_primary' => $settings->get('widget.colors.on_primary'),
            ],
            'widgetIcon' => $settings->get('widget.launcher_icon'),
        ]);
    }
}
