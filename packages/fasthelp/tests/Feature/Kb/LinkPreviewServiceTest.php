<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Tabadev\FastHelp\Models\KbPage;
use Tabadev\FastHelp\Models\LinkPreview;
use Tabadev\FastHelp\Services\Links\LinkPreviewService;

/**
 * Build a Guzzle client whose handler is a closure that resolves requests
 * by URL (or path) from a map — deterministic and order-independent.
 */
function previewClient(array $map, ?int &$callCount = null): Client
{
    $calls = &$callCount;
    $handler = function ($request, $options) use ($map, &$calls) {
        if ($calls !== null) {
            $calls++;
        }
        $url = (string) $request->getUri();
        $path = $request->getUri()->getPath();
        $res = $map[$url] ?? $map[$path] ?? new Response(404);

        return Create::promiseFor($res);
    };

    return new Client(['handler' => $handler]);
}

function ogHtml(string $title, string $description, string $image): string
{
    return <<<HTML
    <!DOCTYPE html>
    <html><head>
        <meta property="og:title" content="{$title}">
        <meta property="og:description" content="{$description}">
        <meta property="og:image" content="{$image}">
        <title>Fallback Title</title>
    </head><body><p>Content</p></body></html>
    HTML;
}

// ---------------------------------------------------------------------------
// 1. Internal hit — KbPage wins, no HTTP call made
// ---------------------------------------------------------------------------
it('returns internal KbPage data without making an HTTP call', function () {
    $page = KbPage::factory()->create([
        'url' => 'https://example.test/pricing',
        'title' => 'Pricing Page',
        'description' => 'Our pricing plans',
        'og_image' => 'https://example.test/pricing.png',
    ]);

    // Handler will throw if it is invoked, proving no HTTP call was made.
    $handler = function () {
        throw new RuntimeException('HTTP client was called but should not have been');
    };
    $client = new Client(['handler' => $handler]);

    $service = new LinkPreviewService($client);
    $result = $service->for('https://example.test/pricing');

    expect($result)->not->toBeNull()
        ->and($result['url'])->toBe('https://example.test/pricing')
        ->and($result['title'])->toBe('Pricing Page')
        ->and($result['description'])->toBe('Our pricing plans')
        ->and($result['image'])->toBe('https://example.test/pricing.png')
        ->and($result['internal'])->toBeTrue();

    // No LinkPreview row should have been created for an internal hit.
    expect(LinkPreview::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// 2. External fetch + cache; second call hits cache (handler called only once)
// ---------------------------------------------------------------------------
it('fetches external URL, persists a LinkPreview row, and returns cached result on second call', function () {
    $callCount = 0;
    $html = ogHtml('OG Title', 'OG Description', 'https://ext.test/img.png');

    $client = previewClient([
        'https://ext.test/post' => new Response(200, ['Content-Type' => 'text/html'], $html),
    ], $callCount);

    $service = new LinkPreviewService($client);
    $result = $service->for('https://ext.test/post');

    expect($result)->not->toBeNull()
        ->and($result['url'])->toBe('https://ext.test/post')
        ->and($result['title'])->toBe('OG Title')
        ->and($result['description'])->toBe('OG Description')
        ->and($result['image'])->toBe('https://ext.test/img.png')
        ->and($result['internal'])->toBeFalse();

    // A LinkPreview row must have been persisted.
    expect(LinkPreview::where('url', 'https://ext.test/post')->exists())->toBeTrue();
    expect($callCount)->toBe(1);

    // Second call — must hit the cache, not the network.
    $result2 = $service->for('https://ext.test/post');
    expect($result2['title'])->toBe('OG Title')
        ->and($callCount)->toBe(1); // handler was NOT called again
});

// ---------------------------------------------------------------------------
// 3. Invalid / non-http URLs → null
// ---------------------------------------------------------------------------
it('returns null for non-http URLs', function (string $url) {
    $service = new LinkPreviewService;
    expect($service->for($url))->toBeNull();
})->with([
    'mailto:user@example.com',
    'ftp://files.example.com/file.txt',
    'not a url at all',
    '',
]);

// ---------------------------------------------------------------------------
// 4. Fetch failure (500 / connection error) → null, no exception
// ---------------------------------------------------------------------------
it('returns null on HTTP 500 without throwing', function () {
    $client = previewClient([
        'https://fail.test/page' => new Response(500, [], 'server error'),
    ]);

    $service = new LinkPreviewService($client);
    expect($service->for('https://fail.test/page'))->toBeNull();
});

it('returns null on connection error without throwing', function () {
    $handler = function () {
        throw new ConnectException(
            'Connection refused',
            new Request('GET', 'https://down.test/page')
        );
    };
    $client = new Client(['handler' => $handler]);
    $service = new LinkPreviewService($client);

    expect($service->for('https://down.test/page'))->toBeNull();
});

// ---------------------------------------------------------------------------
// 5. previewsFor — multiple URLs in body, internal + external, dedup, order
// ---------------------------------------------------------------------------
it('extracts, deduplicates and resolves previews from a message body', function () {
    // Seed an internal page for the pricing URL.
    KbPage::factory()->create([
        'url' => 'https://example.test/pricing',
        'title' => 'Pricing',
        'description' => 'Plans',
        'og_image' => null,
    ]);

    $html = ogHtml('Post Title', 'Post Desc', 'https://ext.test/img.png');
    $client = previewClient([
        'https://ext.test/post' => new Response(200, ['Content-Type' => 'text/html'], $html),
    ]);

    $service = new LinkPreviewService($client);

    $body = 'See https://ext.test/post and https://example.test/pricing for details.';
    $previews = $service->previewsFor($body);

    expect($previews)->toHaveCount(2)
        ->and($previews[0]['url'])->toBe('https://ext.test/post')
        ->and($previews[0]['internal'])->toBeFalse()
        ->and($previews[1]['url'])->toBe('https://example.test/pricing')
        ->and($previews[1]['internal'])->toBeTrue();
});

it('returns empty array when body contains no URLs', function () {
    $service = new LinkPreviewService;
    expect($service->previewsFor('Hello, how can I help you?'))->toBe([]);
});

it('deduplicates repeated URLs, preserving first-occurrence order', function () {
    $html = ogHtml('Post', 'Desc', 'https://ext.test/img.png');
    $callCount = 0;
    $client = previewClient([
        'https://ext.test/post' => new Response(200, ['Content-Type' => 'text/html'], $html),
    ], $callCount);

    $service = new LinkPreviewService($client);

    $body = 'First: https://ext.test/post second mention: https://ext.test/post end.';
    $previews = $service->previewsFor($body);

    expect($previews)->toHaveCount(1)
        ->and($previews[0]['url'])->toBe('https://ext.test/post')
        ->and($callCount)->toBe(1); // fetched only once
});

it('strips trailing punctuation from extracted URLs', function () {
    $html = ogHtml('Page', 'Desc', '');
    $client = previewClient([
        'https://ext.test/page' => new Response(200, ['Content-Type' => 'text/html'], $html),
    ]);

    $service = new LinkPreviewService($client);

    $body = 'Visit https://ext.test/page. Or https://ext.test/page, and https://ext.test/page!';
    $previews = $service->previewsFor($body);

    // All three trailing-punctuation variants should resolve to the same URL → deduped to 1.
    expect($previews)->toHaveCount(1)
        ->and($previews[0]['url'])->toBe('https://ext.test/page');
});
