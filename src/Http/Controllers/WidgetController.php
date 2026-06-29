<?php

namespace Tabadev\FastHelp\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Tabadev\FastHelp\Models\Conversation;
use Tabadev\FastHelp\Models\Message;
use Tabadev\FastHelp\Services\ConversationService;
use Tabadev\FastHelp\Services\PresenceService;
use Tabadev\FastHelp\Support\Identity;
use Tabadev\FastHelp\Support\IdentityResolver;

class WidgetController extends Controller
{
    public function __construct(
        protected IdentityResolver $identity,
        protected ConversationService $conversations,
        protected PresenceService $presence,
    ) {}

    public function start(Request $request): JsonResponse
    {
        $identity = $this->identity->resolve();

        $conversation = $this->conversations->start($identity, $request->input('url'));

        return response()->json([
            'conversation' => [
                'uuid' => $conversation->uuid,
                'status' => $conversation->status->value,
            ],
            'messages' => $this->serializeMessages($conversation->messages),
            'online_count' => $this->presence->onlineCount(),
        ]);
    }

    public function messages(Request $request, string $uuid): JsonResponse
    {
        $identity = $this->identity->resolve();

        $conversation = $this->findConversationOrFail($uuid);

        $this->authorizeOwnership($conversation, $identity);

        $query = $conversation->messages();

        if ($request->filled('after')) {
            $query->where('id', '>', (int) $request->input('after'));
        }

        return response()->json([
            'messages' => $this->serializeMessages($query->get()),
            'status' => $conversation->status->value,
        ]);
    }

    public function postMessage(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $identity = $this->identity->resolve();

        $conversation = $this->findConversationOrFail($uuid);

        $this->authorizeOwnership($conversation, $identity);

        $this->conversations->postClientMessage($conversation, $request->input('body'));

        $query = $conversation->messages();

        if ($request->filled('after')) {
            $query->where('id', '>', (int) $request->input('after'));
        }

        return response()->json([
            'messages' => $this->serializeMessages($query->get()),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'online_count' => $this->presence->onlineCount(),
        ]);
    }

    protected function findConversationOrFail(string $uuid): Conversation
    {
        return Conversation::query()->where('uuid', $uuid)->firstOrFail();
    }

    protected function authorizeOwnership(Conversation $conversation, Identity $identity): void
    {
        $ownsAsVisitor = (int) $conversation->visitor_id === (int) $identity->visitor->id;

        $ownsAsUser = $identity->isUser()
            && $conversation->client_type === $identity->user->getMorphClass()
            && (int) $conversation->client_id === (int) $identity->user->getKey();

        if (! $ownsAsVisitor && ! $ownsAsUser) {
            abort(403);
        }
    }

    /**
     * @param  iterable<int, Message>  $messages
     * @return array<int, array<string, mixed>>
     */
    protected function serializeMessages(iterable $messages): array
    {
        $result = [];

        foreach ($messages as $message) {
            $result[] = [
                'id' => $message->id,
                'sender_type' => $message->sender_type->value,
                'body' => $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
            ];
        }

        return $result;
    }
}
