<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tabadev\FastHelp\Enums\AgentPresence;
use Tabadev\FastHelp\Models\AgentStatus;
use Tabadev\FastHelp\Models\Conversation;
use Tabadev\FastHelp\Models\Visitor;

beforeEach(function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);

    // JSON test requests don't send cookies unless credentials are
    // explicitly enabled; the widget relies on the guest cookie to
    // re-identify the visitor across requests, so this is required for
    // any test that needs to simulate the "same visitor, second request"
    // flow.
    $this->withCredentials();
});

function fasthelpUrl(string $path): string
{
    $prefix = config('fasthelp.routes.prefix', 'fasthelp');

    return '/'.trim($prefix, '/').'/'.trim($path, '/');
}

it('starts a conversation and sets a guest cookie', function () {
    $response = $this->postJson(fasthelpUrl('conversation'), [
        'url' => 'https://example.com/pricing',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'conversation' => ['uuid', 'status'],
            'messages',
            'online_count',
        ]);

    expect($response->json('messages'))->toBe([])
        ->and(Conversation::count())->toBe(1);

    $cookieName = config('fasthelp.cookie', 'fasthelp_visitor');
    $cookie = $response->headers->getCookies();
    $names = array_map(fn ($c) => $c->getName(), $cookie);

    expect($names)->toContain($cookieName);
});

it('posts a client message and returns it in the response', function () {
    $start = $this->postJson(fasthelpUrl('conversation'));
    $uuid = $start->json('conversation.uuid');

    $cookieName = config('fasthelp.cookie', 'fasthelp_visitor');
    $cookieValue = collect($start->headers->getCookies())
        ->first(fn ($c) => $c->getName() === $cookieName)
        ->getValue();

    $response = $this->withUnencryptedCookie($cookieName, $cookieValue)
        ->postJson(fasthelpUrl("conversation/{$uuid}/messages"), [
            'body' => 'Hello, I need help',
        ]);

    $response->assertOk();

    $messages = $response->json('messages');

    expect($messages)->not->toBeEmpty();

    $bodies = array_column($messages, 'body');
    expect($bodies)->toContain('Hello, I need help');
});

it('returns messages for the owning visitor via the polling endpoint', function () {
    $start = $this->postJson(fasthelpUrl('conversation'));
    $uuid = $start->json('conversation.uuid');

    $cookieName = config('fasthelp.cookie', 'fasthelp_visitor');
    $cookieValue = collect($start->headers->getCookies())
        ->first(fn ($c) => $c->getName() === $cookieName)
        ->getValue();

    $this->withUnencryptedCookie($cookieName, $cookieValue)
        ->postJson(fasthelpUrl("conversation/{$uuid}/messages"), [
            'body' => 'Hello, I need help',
        ]);

    $response = $this->withUnencryptedCookie($cookieName, $cookieValue)
        ->getJson(fasthelpUrl("conversation/{$uuid}/messages"));

    $response->assertOk()
        ->assertJsonStructure(['messages', 'status']);

    $bodies = array_column($response->json('messages'), 'body');
    expect($bodies)->toContain('Hello, I need help');
});

it('forbids a different visitor from reading someone else'."'".'s conversation', function () {
    $otherVisitor = Visitor::factory()->create();
    $conversation = Conversation::factory()->create([
        'visitor_id' => $otherVisitor->id,
    ]);

    // Fresh guest, no cookie sent -> resolver creates a brand-new visitor.
    $response = $this->getJson(fasthelpUrl("conversation/{$conversation->uuid}/messages"));

    $response->assertForbidden();
});

it('forbids a different visitor from posting to someone else'."'".'s conversation', function () {
    $otherVisitor = Visitor::factory()->create();
    $conversation = Conversation::factory()->create([
        'visitor_id' => $otherVisitor->id,
    ]);

    $response = $this->postJson(fasthelpUrl("conversation/{$conversation->uuid}/messages"), [
        'body' => 'Sneaky message',
    ]);

    $response->assertForbidden();
});

it('returns 404 for an unknown conversation uuid', function () {
    $response = $this->getJson(fasthelpUrl('conversation/does-not-exist/messages'));

    $response->assertNotFound();
});

it('only returns messages newer than the given after id when polling', function () {
    $start = $this->postJson(fasthelpUrl('conversation'));
    $uuid = $start->json('conversation.uuid');

    $cookieName = config('fasthelp.cookie', 'fasthelp_visitor');
    $cookieValue = collect($start->headers->getCookies())
        ->first(fn ($c) => $c->getName() === $cookieName)
        ->getValue();

    $first = $this->withUnencryptedCookie($cookieName, $cookieValue)
        ->postJson(fasthelpUrl("conversation/{$uuid}/messages"), ['body' => 'first message']);

    $firstId = collect($first->json('messages'))->firstWhere('body', 'first message')['id'];

    $this->withUnencryptedCookie($cookieName, $cookieValue)
        ->postJson(fasthelpUrl("conversation/{$uuid}/messages"), ['body' => 'second message']);

    $response = $this->withUnencryptedCookie($cookieName, $cookieValue)
        ->getJson(fasthelpUrl("conversation/{$uuid}/messages")."?after={$firstId}");

    $bodies = array_column($response->json('messages'), 'body');

    expect($bodies)->not->toContain('first message')
        ->and($bodies)->toContain('second message');
});

it('reports the online agent count from the status endpoint', function () {
    AgentStatus::factory()->create(['status' => AgentPresence::Online, 'last_seen_at' => now()]);
    AgentStatus::factory()->create(['status' => AgentPresence::Online, 'last_seen_at' => now()]);
    AgentStatus::factory()->create(['status' => AgentPresence::Offline, 'last_seen_at' => now()]);

    $response = $this->getJson(fasthelpUrl('status'));

    $response->assertOk()
        ->assertJson(['online_count' => 2]);
});
