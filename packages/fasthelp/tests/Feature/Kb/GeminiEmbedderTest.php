<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response;
use Tabadev\FastHelp\Contracts\Embedder;
use Tabadev\FastHelp\Services\Gemini\GeminiEmbedder;

function embedderClientReturning(array $responses): Client
{
    $stack = HandlerStack::create(new MockHandler($responses));

    return new Client(['handler' => $stack, 'base_uri' => 'https://example.test/']);
}

it('returns a float vector from a normal Gemini embedContent response', function () {
    $body = json_encode(['embedding' => ['values' => [0.1, 0.2, 0.3]]]);
    $svc = new GeminiEmbedder(embedderClientReturning([new Response(200, [], $body)]));
    $result = $svc->embed('hello world');
    expect($result)->toBe([0.1, 0.2, 0.3]);
});

it('returns null without calling HTTP when text is blank', function () {
    // MockHandler with NO responses — if HTTP is called it throws, proving it was not called.
    $svc = new GeminiEmbedder(embedderClientReturning([]));
    expect($svc->embed('   '))->toBeNull()
        ->and($svc->embed(''))->toBeNull();
});

it('returns null gracefully when Gemini returns an error response', function () {
    $svc = new GeminiEmbedder(embedderClientReturning([new Response(500, [], '{"error":"boom"}')]));
    expect($svc->embed('some text'))->toBeNull();
});

it('returns null gracefully on a ConnectException', function () {
    $svc = new GeminiEmbedder(embedderClientReturning([
        new ConnectException('connection refused', new PsrRequest('POST', 'x')),
    ]));
    expect($svc->embed('some text'))->toBeNull();
});

it('resolves GeminiEmbedder from the container via the Embedder contract', function () {
    expect(app(Embedder::class))->toBeInstanceOf(GeminiEmbedder::class);
});
