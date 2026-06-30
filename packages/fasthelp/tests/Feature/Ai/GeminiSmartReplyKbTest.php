<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Collection;
use Tabadev\FastHelp\Models\Conversation;
use Tabadev\FastHelp\Services\Gemini\GeminiSmartReply;
use Tabadev\FastHelp\Services\Knowledge\KnowledgeRetriever;

function geminiClientWithHistory(array $responses, array &$history): Client
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    return new Client(['handler' => $stack, 'base_uri' => 'https://example.test/']);
}

function bindFakeRetriever(array $pages): void
{
    app()->bind(KnowledgeRetriever::class, fn () => new class($pages) extends KnowledgeRetriever
    {
        public function __construct(private array $fakePages) {}

        public function retrieve(string $q, ?int $k = null): Collection
        {
            return collect($this->fakePages);
        }
    });
}

function geminiResponseJson(string $text = 'Here is some help.'): string
{
    return json_encode([
        'candidates' => [
            ['content' => ['parts' => [['text' => $text]]]],
        ],
    ]);
}

it('injects KB context into the Gemini request when kb.enabled is true and retriever returns pages', function () {
    config()->set('fasthelp.ai.handoff_keywords', ['human']);
    config()->set('fasthelp.kb.enabled', true);

    bindFakeRetriever([
        [
            'url' => 'https://example.test/pricing',
            'title' => 'Pricing',
            'description' => 'Our pricing plans',
            'excerpt' => 'Our plans start at $9',
            'score' => 0.9,
        ],
    ]);

    $history = [];
    $client = geminiClientWithHistory([new Response(200, [], geminiResponseJson('Check our pricing page.'))], $history);

    $svc = new GeminiSmartReply($client);
    $result = $svc->reply(Conversation::factory()->create(), 'What are your pricing options?');

    expect($result->shouldHandoff)->toBeFalse()
        ->and($result->reply)->toBe('Check our pricing page.');

    $sent = (string) $history[0]['request']->getBody();
    $decoded = json_decode($sent, true);
    $promptText = $decoded['contents'][0]['parts'][0]['text'];

    expect($promptText)
        ->toContain('https://example.test/pricing')
        ->toContain('Pricing');
});

it('does not inject KB context into the Gemini request when kb.enabled is false', function () {
    config()->set('fasthelp.ai.handoff_keywords', ['human']);
    config()->set('fasthelp.kb.enabled', false);

    // Even if the retriever is bound, it must not be called; bind it to a version
    // that would include a telltale string so we can assert it is absent.
    bindFakeRetriever([
        [
            'url' => 'https://example.test/pricing',
            'title' => 'Pricing',
            'description' => 'Our pricing plans',
            'excerpt' => 'SHOULD_NOT_APPEAR_IN_PROMPT',
            'score' => 0.9,
        ],
    ]);

    $history = [];
    $client = geminiClientWithHistory([new Response(200, [], geminiResponseJson('Normal answer.'))], $history);

    $svc = new GeminiSmartReply($client);
    $result = $svc->reply(Conversation::factory()->create(), 'What are your pricing options?');

    expect($result->shouldHandoff)->toBeFalse()
        ->and($result->reply)->toBe('Normal answer.');

    $sent = (string) $history[0]['request']->getBody();

    expect($sent)->not->toContain('SHOULD_NOT_APPEAR_IN_PROMPT')
        ->and($sent)->not->toContain('https://example.test/pricing');
});

it('handoff keyword short-circuits before any KB retrieval or HTTP call', function () {
    config()->set('fasthelp.ai.handoff_keywords', ['human']);
    config()->set('fasthelp.kb.enabled', true);

    // Retriever that throws if consulted — proves it was never called.
    app()->bind(KnowledgeRetriever::class, fn () => new class extends KnowledgeRetriever
    {
        public function __construct() {}

        public function retrieve(string $q, ?int $k = null): Collection
        {
            throw new RuntimeException('KnowledgeRetriever should not have been called');
        }
    });

    // Empty MockHandler — throws OutOfBoundsException if HTTP is attempted.
    $history = [];
    $client = geminiClientWithHistory([], $history);

    $svc = new GeminiSmartReply($client);
    $result = $svc->reply(Conversation::factory()->create(), 'I want to talk to a human please');

    expect($result->shouldHandoff)->toBeTrue()
        ->and($result->reply)->toBeNull()
        ->and($history)->toBeEmpty();
});

it('falls back gracefully when retriever returns empty collection', function () {
    config()->set('fasthelp.ai.handoff_keywords', ['human']);
    config()->set('fasthelp.kb.enabled', true);

    bindFakeRetriever([]); // empty — no context block should be prepended

    $history = [];
    $client = geminiClientWithHistory([new Response(200, [], geminiResponseJson('Plain answer.'))], $history);

    $svc = new GeminiSmartReply($client);
    $result = $svc->reply(Conversation::factory()->create(), 'Tell me something');

    expect($result->shouldHandoff)->toBeFalse()
        ->and($result->reply)->toBe('Plain answer.');

    $sent = (string) $history[0]['request']->getBody();

    // Context block header must NOT appear in the prompt when pages are empty.
    expect($sent)->not->toContain('Use ONLY the following pages');
});
