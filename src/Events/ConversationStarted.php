<?php

namespace Tabadev\FastHelp\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Tabadev\FastHelp\Models\Conversation;

class ConversationStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Conversation $conversation) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->channelPrefix().'.agents')];
    }

    public function broadcastAs(): string
    {
        return 'conversation.started';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'uuid' => $this->conversation->uuid,
            'status' => $this->conversation->status->value,
            'visitor_name' => $this->conversation->visitor_name,
            'current_url' => $this->conversation->current_url,
            'created_at' => $this->conversation->created_at?->toIso8601String(),
        ];
    }

    private function channelPrefix(): string
    {
        return config('fasthelp.broadcasting.channel_prefix', 'fasthelp');
    }
}
