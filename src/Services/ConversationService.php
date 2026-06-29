<?php

namespace Tabadev\FastHelp\Services;

use Illuminate\Support\Facades\DB;
use Tabadev\FastHelp\Enums\ConversationStatus;
use Tabadev\FastHelp\Enums\MessageSender;
use Tabadev\FastHelp\Events\ConversationStarted;
use Tabadev\FastHelp\Events\MessageSent;
use Tabadev\FastHelp\Models\Conversation;
use Tabadev\FastHelp\Models\Message;
use Tabadev\FastHelp\Support\Identity;

class ConversationService
{
    public function start(Identity $identity, ?string $url = null): Conversation
    {
        return DB::transaction(function () use ($identity, $url) {
            $conversation = new Conversation([
                'status' => ConversationStatus::Open,
                'visitor_id' => $identity->visitor->id,
                'visitor_name' => $identity->visitor->name,
                'visitor_email' => $identity->visitor->email,
                'current_url' => $url,
                'last_message_at' => now(),
            ]);

            if ($identity->isUser()) {
                $conversation->client()->associate($identity->user);
            }

            $conversation->save();

            event(new ConversationStarted($conversation));

            return $conversation;
        });
    }

    public function postClientMessage(Conversation $conversation, string $body): Message
    {
        return DB::transaction(function () use ($conversation, $body) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_type' => MessageSender::Client,
                'sender_id' => null,
                'body' => $body,
            ]);

            $conversation->last_message_at = now();
            $conversation->save();

            $message->setRelation('conversation', $conversation);

            event(new MessageSent($message));

            return $message;
        });
    }

    public function postAgentMessage(Conversation $conversation, int $agentId, string $body): Message
    {
        return DB::transaction(function () use ($conversation, $agentId, $body) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_type' => MessageSender::Agent,
                'sender_id' => $agentId,
                'body' => $body,
            ]);

            if (! $conversation->assigned_agent_id) {
                $conversation->assigned_agent_id = $agentId;
                $conversation->status = ConversationStatus::Assigned;
            }

            $conversation->last_message_at = now();
            $conversation->save();

            $message->setRelation('conversation', $conversation);

            event(new MessageSent($message));

            return $message;
        });
    }

    public function requestHumanHandoff(Conversation $conversation): void
    {
        $conversation->status = ConversationStatus::Pending;
        $conversation->save();

        event(new ConversationStarted($conversation));
    }

    public function resolve(Conversation $conversation, ?int $rating = null): void
    {
        $conversation->status = ConversationStatus::Resolved;

        if ($rating !== null) {
            $conversation->rating = $rating;
        }

        $conversation->save();
    }
}
