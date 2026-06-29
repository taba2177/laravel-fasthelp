<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response;
use Tabadev\FastHelp\Models\Conversation;
use Tabadev\FastHelp\Services\Gemini\GeminiSmartReply;

function geminiClientReturning(array $responses): Client
{
    $stack = HandlerStack::create(new MockHandler($responses));

    return new Client(['handler' => $stack, 'base_uri' => 'https://example.test/']);
}

it('returns an answer from a normal Gemini response', function () {
    config()->set('fasthelp.ai.handoff_keywords', ['human']);
    $body = json_encode(['candidates' => [['content' => ['parts' => [['text' => 'Sure, here is help.']]]]]]);
    $svc = new GeminiSmartReply(geminiClientReturning([new Response(200, [], $body)]));
    $result = $svc->reply(Conversation::factory()->create(), 'how do I reset my password?');
    expect($result->shouldHandoff)->toBeFalse()
        ->and($result->reply)->toBe('Sure, here is help.');
});

it('hands off on a keyword without calling Gemini', function () {
    config()->set('fasthelp.ai.handoff_keywords', ['human', 'agent']);
    // MockHandler with NO responses queued => if Gemini were called it would throw, proving it was not called.
    $svc = new GeminiSmartReply(geminiClientReturning([]));
    $result = $svc->reply(Conversation::factory()->create(), 'I want to talk to a human please');
    expect($result->shouldHandoff)->toBeTrue()
        ->and($result->reply)->toBeNull();
});

it('hands off gracefully when Gemini errors', function () {
    config()->set('fasthelp.ai.handoff_keywords', ['human']);
    $svc = new GeminiSmartReply(geminiClientReturning([
        new ConnectException('boom', new PsrRequest('POST', 'x')),
    ]));
    $result = $svc->reply(Conversation::factory()->create(), 'hello there');
    expect($result->shouldHandoff)->toBeTrue()
        ->and($result->reply)->toBeNull();
});
