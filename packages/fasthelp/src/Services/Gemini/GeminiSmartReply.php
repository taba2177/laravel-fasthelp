<?php

namespace Tabadev\FastHelp\Services\Gemini;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Tabadev\FastHelp\Contracts\SmartReply;
use Tabadev\FastHelp\Enums\MessageSender;
use Tabadev\FastHelp\Models\Conversation;
use Tabadev\FastHelp\Services\SmartReplyResult;

class GeminiSmartReply implements SmartReply
{
    public function __construct(private ?Client $client = null) {}

    public function reply(Conversation $conversation, string $message): SmartReplyResult
    {
        $handoffKeywords = config('fasthelp.ai.handoff_keywords', []);
        $lowerMessage = strtolower($message);

        foreach ($handoffKeywords as $keyword) {
            if ($keyword !== '' && str_contains($lowerMessage, strtolower($keyword))) {
                return SmartReplyResult::handoff();
            }
        }

        try {
            $fullPrompt = $this->buildPrompt($conversation, $message);

            $apiKey = config('fasthelp.ai.api_key');
            $model = config('fasthelp.ai.model', 'gemini-1.5-flash');

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

    private function buildPrompt(Conversation $conversation, string $message): string
    {
        $lines = [config('fasthelp.ai.system_prompt')];

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
