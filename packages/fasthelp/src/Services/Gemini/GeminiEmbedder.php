<?php

namespace Tabadev\FastHelp\Services\Gemini;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Tabadev\FastHelp\Contracts\Embedder;

class GeminiEmbedder implements Embedder
{
    public function __construct(private ?Client $client = null) {}

    public function embed(string $text): ?array
    {
        if (blank($text)) {
            return null;
        }

        $key = config('fasthelp.ai.api_key');
        $model = config('fasthelp.kb.embedding_model', 'text-embedding-004');

        try {
            $response = $this->client()->post("models/{$model}:embedContent?key={$key}", [
                'json' => [
                    'model' => "models/{$model}",
                    'content' => [
                        'parts' => [
                            ['text' => $text],
                        ],
                    ],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $values = $data['embedding']['values'] ?? null;

            return is_array($values) && ! empty($values) ? $values : null;
        } catch (\Throwable $e) {
            Log::error('GeminiEmbedder error: '.$e->getMessage(), [
                'text_length' => strlen($text),
            ]);

            return null;
        }
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
