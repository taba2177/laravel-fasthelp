<?php

namespace Tabadev\FastHelp\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ParticipantTyping implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $conversationUuid, public string $who) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->channelPrefix().'.conversation.'.$this->conversationUuid)];
    }

    public function broadcastAs(): string
    {
        return 'typing';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'who' => $this->who,
        ];
    }

    private function channelPrefix(): string
    {
        return config('fasthelp.broadcasting.channel_prefix', 'fasthelp');
    }
}
