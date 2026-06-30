<?php

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Tabadev\FastHelp\Contracts\Embedder;
use Tabadev\FastHelp\Models\KbPage;
use Tabadev\FastHelp\Services\Crawl\SiteCrawler;

/**
 * URL-mapped Guzzle client: BFS order is unpredictable, so we resolve each
 * request by its full URL or path rather than relying on FIFO ordering.
 */
function crawlerClient(array $map): Client
{
    $handler = function ($request, $options) use ($map) {
        $url = (string) $request->getUri();
        $path = $request->getUri()->getPath();
        $res = $map[$url] ?? $map[$path] ?? new Response(404);

        return Create::promiseFor($res);
    };

    return new Client(['handler' => $handler]);
}

/** A fake embedder returning a fixed vector while tracking call count. */
function fakeEmbedder(): Embedder
{
    return new class implements Embedder
    {
        public int $calls = 0;

        public function embed(string $text): ?array
        {
            $this->calls++;

            return [0.1, 0.2, 0.3];
        }
    };
}

function htmlResponse(string $body): Response
{
    return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $body);
}

function xmlResponse(string $body): Response
{
    return new Response(200, ['Content-Type' => 'application/xml'], $body);
}

function pageHtml(string $title, string $description, string $ogImage, string $body, string $extraHead = ''): string
{
    return <<<HTML
    <!DOCTYPE html>
    <html><head>
        <title>{$title}</title>
        <meta name="description" content="{$description}">
        <meta property="og:image" content="{$ogImage}">
        {$extraHead}
    </head><body>{$body}</body></html>
    HTML;
}

beforeEach(function () {
    config([
        'fasthelp.kb.base_url' => 'https://example.test',
        'fasthelp.kb.respect_robots' => false,
        'fasthelp.kb.same_domain_only' => true,
        'fasthelp.kb.max_pages' => 100,
    ]);
});

it('discovers pages via sitemap and indexes them', function () {
    $sitemap = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
        <url><loc>https://example.test/</loc></url>
        <url><loc>https://example.test/pricing</loc></url>
    </urlset>
    XML;

    $map = [
        'https://example.test/sitemap.xml' => xmlResponse($sitemap),
        'https://example.test/' => htmlResponse(pageHtml('Home', 'Home desc', 'https://example.test/home.png', '<p>Welcome home</p>')),
        'https://example.test/pricing' => htmlResponse(pageHtml('Pricing', 'Pricing desc', 'https://example.test/price.png', '<p>Our pricing</p>')),
    ];

    $embedder = fakeEmbedder();
    $crawler = new SiteCrawler($embedder, crawlerClient($map));

    $summary = $crawler->crawl();

    expect($summary['indexed'])->toBe(2)
        ->and(KbPage::count())->toBe(2);

    $home = KbPage::where('url', 'https://example.test/')->first();
    expect($home->title)->toBe('Home')
        ->and($home->description)->toBe('Home desc')
        ->and($home->og_image)->toBe('https://example.test/home.png')
        ->and($home->content)->toContain('Welcome home')
        ->and($home->embedding)->toBe([0.1, 0.2, 0.3])
        ->and($home->status)->toBe('ok');

    $pricing = KbPage::where('url', 'https://example.test/pricing')->first();
    expect($pricing->title)->toBe('Pricing')
        ->and($pricing->description)->toBe('Pricing desc')
        ->and($pricing->og_image)->toBe('https://example.test/price.png')
        ->and($pricing->content)->toContain('Our pricing')
        ->and($pricing->embedding)->toBe([0.1, 0.2, 0.3]);
});

it('dedupes unchanged pages on re-crawl and does not re-embed', function () {
    $sitemap = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <urlset>
        <url><loc>https://example.test/</loc></url>
        <url><loc>https://example.test/pricing</loc></url>
    </urlset>
    XML;

    $map = [
        'https://example.test/sitemap.xml' => xmlResponse($sitemap),
        'https://example.test/' => htmlResponse(pageHtml('Home', 'Home desc', '', '<p>Welcome home</p>')),
        'https://example.test/pricing' => htmlResponse(pageHtml('Pricing', 'Pricing desc', '', '<p>Our pricing</p>')),
    ];

    $embedder = fakeEmbedder();

    $first = (new SiteCrawler($embedder, crawlerClient($map)))->crawl();
    expect($first['indexed'])->toBe(2)
        ->and($embedder->calls)->toBe(2);

    $second = (new SiteCrawler($embedder, crawlerClient($map)))->crawl();
    expect($second['indexed'])->toBe(0)
        ->and($second['skipped'])->toBe(2)
        ->and($embedder->calls)->toBe(2) // unchanged: no new embed calls
        ->and(KbPage::count())->toBe(2);
});

it('skips pages marked noindex', function () {
    $sitemap = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <urlset>
        <url><loc>https://example.test/</loc></url>
        <url><loc>https://example.test/secret</loc></url>
    </urlset>
    XML;

    $map = [
        'https://example.test/sitemap.xml' => xmlResponse($sitemap),
        'https://example.test/' => htmlResponse(pageHtml('Home', 'Home desc', '', '<p>Welcome home</p>')),
        'https://example.test/secret' => htmlResponse(pageHtml('Secret', 'Secret desc', '', '<p>Hidden</p>', '<meta name="robots" content="noindex,follow">')),
    ];

    $embedder = fakeEmbedder();
    $summary = (new SiteCrawler($embedder, crawlerClient($map)))->crawl();

    expect($summary['indexed'])->toBe(1)
        ->and($summary['skipped'])->toBe(1)
        ->and(KbPage::where('url', 'https://example.test/secret')->exists())->toBeFalse()
        ->and(KbPage::where('url', 'https://example.test/')->exists())->toBeTrue();
});

it('respects the max_pages cap', function () {
    $sitemap = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <urlset>
        <url><loc>https://example.test/</loc></url>
        <url><loc>https://example.test/pricing</loc></url>
        <url><loc>https://example.test/about</loc></url>
    </urlset>
    XML;

    $map = [
        'https://example.test/sitemap.xml' => xmlResponse($sitemap),
        'https://example.test/' => htmlResponse(pageHtml('Home', 'Home desc', '', '<p>Welcome home</p>')),
        'https://example.test/pricing' => htmlResponse(pageHtml('Pricing', 'Pricing desc', '', '<p>Our pricing</p>')),
        'https://example.test/about' => htmlResponse(pageHtml('About', 'About desc', '', '<p>About us</p>')),
    ];

    $summary = (new SiteCrawler(fakeEmbedder(), crawlerClient($map)))->crawl(null, 1);

    expect($summary['indexed'])->toBe(1)
        ->and(KbPage::count())->toBe(1);
});

it('counts a failing page as failed without aborting the crawl', function () {
    $sitemap = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <urlset>
        <url><loc>https://example.test/</loc></url>
        <url><loc>https://example.test/broken</loc></url>
    </urlset>
    XML;

    $map = [
        'https://example.test/sitemap.xml' => xmlResponse($sitemap),
        'https://example.test/' => htmlResponse(pageHtml('Home', 'Home desc', '', '<p>Welcome home</p>')),
        'https://example.test/broken' => new Response(500, [], 'server error'),
    ];

    $summary = (new SiteCrawler(fakeEmbedder(), crawlerClient($map)))->crawl();

    expect($summary['indexed'])->toBe(1)
        ->and($summary['failed'])->toBe(1)
        ->and(KbPage::where('url', 'https://example.test/')->exists())->toBeTrue();
});

it('falls back to BFS when there is no sitemap and ignores external links', function () {
    $map = [
        // No sitemap.
        'https://example.test/sitemap.xml' => new Response(404),
        'https://example.test' => htmlResponse(pageHtml(
            'Home',
            'Home desc',
            '',
            '<a href="/about">About</a> <a href="https://other.test/x">External</a>'
        )),
        'https://example.test/about' => htmlResponse(pageHtml('About', 'About desc', '', '<p>About us</p>')),
    ];

    $summary = (new SiteCrawler(fakeEmbedder(), crawlerClient($map)))->crawl();

    expect($summary['indexed'])->toBe(2)
        ->and(KbPage::where('url', 'https://example.test/about')->exists())->toBeTrue()
        ->and(KbPage::where('url', 'https://other.test/x')->exists())->toBeFalse();
});
