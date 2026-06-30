<?php

namespace Tabadev\FastHelp\Services\Gemini;

use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Tabadev\FastHelp\Contracts\SmartReply;
use Tabadev\FastHelp\Enums\MessageSender;
use Tabadev\FastHelp\Models\Conversation;
use Tabadev\FastHelp\Services\Knowledge\KnowledgeRetriever;
use Tabadev\FastHelp\Services\SmartReplyResult;
use Tabadev\FastHelp\Support\Settings;

class GeminiSmartReply implements SmartReply
{
    public function __construct(private ?Client $client = null) {}

    public function reply(Conversation $conversation, string $message): SmartReplyResult
    {
        $handoffKeywords = app(Settings::class)->get('ai.handoff_keywords', []);
        $lowerMessage = strtolower($message);

        foreach ($handoffKeywords as $keyword) {
            if ($keyword !== '' && str_contains($lowerMessage, strtolower($keyword))) {
                return SmartReplyResult::handoff();
            }
        }

        $kbContext = null;
        if (config('fasthelp.kb.enabled')) {
            try {
                $pages = app(KnowledgeRetriever::class)->retrieve($message);
                if ($pages->isNotEmpty()) {
                    $kbContext = $this->buildKbContext($pages);
                }
            } catch (\Throwable $e) {
                Log::warning('GeminiSmartReply: KB retrieval failed, continuing without context: '.$e->getMessage());
            }
        }

        try {
            $fullPrompt = $this->buildPrompt($conversation, $message, $kbContext);

            $apiKey = app(Settings::class)->get('ai.api_key');
            $model = app(Settings::class)->get('ai.model', 'gemini-1.5-flash');

            $response = $this->client()->post("models/{$model}:generateContent?key={$apiKey}", [
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $fullPrompt],
                            ],
                        ],
                    ],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (empty($text)) {
                return SmartReplyResult::handoff();
            }

            return SmartReplyResult::answer($text);
        } catch (\Throwable $e) {
            Log::error('GeminiSmartReply error: '.$e->getMessage(), [
                'conversation_id' => $conversation->id,
                'message' => $message,
            ]);

            return SmartReplyResult::handoff();
        }
    }

    private function buildKbContext(Collection $pages): string
    {
        $block = "You are answering questions about our website. Use ONLY the following pages when relevant, and when a page answers the customer's need, include its URL in your reply so they can go straight there.\n";

        foreach ($pages as $page) {
            $block .= "\nPage: {$page['title']}";
            $block .= "\nURL: {$page['url']}";
            $block .= "\nExcerpt: {$page['excerpt']}";
            $block .= "\n---";
        }

        return $block;
    }

    private function buildPrompt(Conversation $conversation, string $message, ?string $kbContext = null): string
    {
        $lines = [];

        if ($kbContext !== null) {
            $lines[] = $kbContext;
        }

        $lines[] = app(Settings::class)->get('ai.system_prompt');

        $recentMessages = $conversation->messages()
            ->latest()
            ->take(10)
            ->get()
            ->reverse();

        foreach ($recentMessages as $previous) {
            $speaker = $previous->sender_type === MessageSender::Client ? 'Customer' : 'Assistant';
            $lines[] = "{$speaker}: {$previous->body}";
        }

        $lines[] = "Customer: {$message}";

        return implode("\n", $lines);
    }

    private function client(): Client
    {
        return $this->client ??= new Client([
            'base_uri' => 'https://generativelanguage.googleapis.com/v1beta/',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }
}
