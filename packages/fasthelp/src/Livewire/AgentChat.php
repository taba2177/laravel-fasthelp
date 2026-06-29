<?php

namespace Tabadev\FastHelp\Livewire;

use Livewire\Component;
use Tabadev\FastHelp\Models\Conversation;
use Tabadev\FastHelp\Services\ConversationService;
use Tabadev\FastHelp\Services\PresenceService;

class AgentChat extends Component
{
    public string $uuid = '';

    public array $messages = [];

    public string $body = '';

    public int $onlineCount = 0;

    protected ?Conversation $conversationInstance = null;

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;

        if (auth()->id() !== null) {
            app(PresenceService::class)->markOnline((int) auth()->id());
        }

        $this->loadMessages();
        $this->onlineCount = app(PresenceService::class)->onlineCount();
    }

    public function send(): void
    {
        $this->validate([
            'body' => 'required|string|max:5000',
        ]);

        app(ConversationService::class)->postAgentMessage(
            $this->conversation(),
            (int) auth()->id(),
            $this->body
        );

        $this->body = '';
        $this->loadMessages();
    }

    public function markResolved(): void
    {
        app(ConversationService::class)->resolve($this->conversation());

        $this->loadMessages();
    }

    public function goOnline(): void
    {
        if (auth()->id() !== null) {
            app(PresenceService::class)->markOnline((int) auth()->id());
        }

        $this->onlineCount = app(PresenceService::class)->onlineCount();
    }

    public function goAway(): void
    {
        if (auth()->id() !== null) {
            app(PresenceService::class)->markAway((int) auth()->id());
        }

        $this->onlineCount = app(PresenceService::class)->onlineCount();
    }

    public function goOffline(): void
    {
        if (auth()->id() !== null) {
            app(PresenceService::class)->markOffline((int) auth()->id());
        }

        $this->onlineCount = app(PresenceService::class)->onlineCount();
    }

    public function conversation(): Conversation
    {
        if ($this->conversationInstance === null || $this->conversationInstance->uuid !== $this->uuid) {
            $this->conversationInstance = Conversation::query()
                ->where('uuid', $this->uuid)
                ->firstOrFail();
        }

        return $this->conversationInstance;
    }

    public function loadMessages(): void
    {
        $conversation = Conversation::query()->where('uuid', $this->uuid)->first();

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
                'sender_id' => $message->sender_id,
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
        $prefix = config('fasthelp.broadcasting.channel_prefix', 'fasthelp');

        return [
            'echo-private:'.$prefix.'.conversation.'.$this->uuid.',.message.sent' => 'onMessageReceived',
        ];
    }

    public function render()
    {
        return view('fasthelp::livewire.agent-chat');
    }
}
