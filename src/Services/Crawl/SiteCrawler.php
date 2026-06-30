<?php

namespace Tabadev\FastHelp\Services\Crawl;

use DOMDocument;
use DOMElement;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use Tabadev\FastHelp\Contracts\Embedder;
use Tabadev\FastHelp\Models\KbPage;

class SiteCrawler
{
    /** File extensions that are clearly not crawlable HTML. */
    private const SKIP_EXTENSIONS = [
        'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico',
        'zip', 'gz', 'tar', 'rar', '7z',
        'css', 'js', 'json', 'xml', 'rss',
        'mp3', 'mp4', 'avi', 'mov', 'webm', 'wav',
        'woff', 'woff2', 'ttf', 'eot',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    ];

    /** @var string[] Disallowed path prefixes parsed from robots.txt. */
    private array $disallowed = [];

    public function __construct(private Embedder $embedder, private ?Client $client = null) {}

    /**
     * Crawl the site and (re)index its pages into the knowledge base.
     *
     * @return array{discovered:int,indexed:int,skipped:int,failed:int}
     */
    public function crawl(?string $baseUrl = null, ?int $maxPages = null): array
    {
        $summary = ['discovered' => 0, 'indexed' => 0, 'skipped' => 0, 'failed' => 0];

        $base = $baseUrl ?? config('fasthelp.kb.base_url');
        $max = $maxPages ?? (int) config('fasthelp.kb.max_pages', 100);

        if (blank($base)) {
            return $summary;
        }

        $parts = parse_url($base);
        if ($parts === false || empty($parts['host'])) {
            return $summary;
        }

        $host = $parts['host'];
        $scheme = $parts['scheme'] ?? 'https';
        $origin = "{$scheme}://{$host}".(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (config('fasthelp.kb.respect_robots')) {
            $this->loadRobots($origin);
        }

        $urls = $this->discover($base, $origin, $host, $max);
        $summary['discovered'] = count($urls);

        foreach ($urls as $url) {
            try {
                $result = $this->indexPage($url);
                $summary[$result]++;
            } catch (\Throwable $e) {
                Log::warning('SiteCrawler: page failed', ['url' => $url, 'error' => $e->getMessage()]);
                $summary['failed']++;

                if ($page = KbPage::where('url', $url)->first()) {
                    $page->update(['status' => 'failed']);
                }
            }
        }

        return $summary;
    }

    /**
     * Discover candidate URLs via sitemap, falling back to BFS link crawling.
     *
     * @return string[]
     */
    private function discover(string $base, string $origin, string $host, int $max): array
    {
        $fromSitemap = $this->discoverFromSitemap($origin, $host);

        if (! empty($fromSitemap)) {
            return array_slice($fromSitemap, 0, $max);
        }

        return $this->discoverViaBfs($base, $host, $max);
    }

    /**
     * @return string[]
     */
    private function discoverFromSitemap(string $origin, string $host): array
    {
        $response = $this->fetch("{$origin}/sitemap.xml");

        if (! $response || ! $this->isSuccess($response)) {
            return [];
        }

        $locs = $this->parseSitemapLocs((string) $response->getBody());

        // Sitemap index: a sitemap pointing to other sitemaps. Expand one level.
        $isIndex = str_contains((string) $response->getBody(), '<sitemapindex');
        if ($isIndex) {
            $pages = [];
            foreach ($locs as $childSitemap) {
                $child = $this->fetch($childSitemap);
                if ($child && $this->isSuccess($child)) {
                    $pages = array_merge($pages, $this->parseSitemapLocs((string) $child->getBody()));
                }
            }
            $locs = $pages;
        }

        $urls = [];
        foreach ($locs as $loc) {
            $normalized = $this->normalizeUrl($loc);
            if ($normalized !== null && $this->isCandidate($normalized, $host)) {
                $urls[$normalized] = true;
            }
        }

        return array_keys($urls);
    }

    /**
     * @return string[]
     */
    private function parseSitemapLocs(string $xml): array
    {
        $locs = [];
        if (preg_match_all('/<loc>\s*(.*?)\s*<\/loc>/is', $xml, $matches)) {
            foreach ($matches[1] as $loc) {
                $locs[] = html_entity_decode(trim($loc));
            }
        }

        return $locs;
    }

    /**
     * @return string[]
     */
    private function discoverViaBfs(string $base, string $host, int $max): array
    {
        $start = $this->normalizeUrl($base);
        if ($start === null) {
            return [];
        }

        $queue = [$start];
        $seen = [$start => true];
        $discovered = [];

        while (! empty($queue) && count($discovered) < $max) {
            $url = array_shift($queue);

            if (! $this->isCandidate($url, $host)) {
                continue;
            }

            $discovered[] = $url;

            $response = $this->fetch($url);
            if (! $response || ! $this->isSuccess($response) || ! $this->isHtml($response)) {
                continue;
            }

            foreach ($this->extractLinks((string) $response->getBody(), $url) as $link) {
                if (! isset($seen[$link]) && $this->isCandidate($link, $host)) {
                    $seen[$link] = true;
                    $queue[] = $link;
                }
            }
        }

        return array_slice($discovered, 0, $max);
    }

    /**
     * Extract same-document absolute links from an HTML body.
     *
     * @return string[]
     */
    private function extractLinks(string $html, string $pageUrl): array
    {
        $dom = $this->loadDom($html);
        $links = [];

        foreach ($dom->getElementsByTagName('a') as $a) {
            /** @var DOMElement $a */
            $href = trim($a->getAttribute('href'));
            if ($href === '' || str_starts_with($href, '#')
                || str_starts_with($href, 'mailto:')
                || str_starts_with($href, 'tel:')
                || str_starts_with($href, 'javascript:')) {
                continue;
            }

            $absolute = $this->resolveUrl($href, $pageUrl);
            $normalized = $absolute !== null ? $this->normalizeUrl($absolute) : null;
            if ($normalized !== null) {
                $links[$normalized] = true;
            }
        }

        return array_keys($links);
    }

    /**
     * Fetch + parse + upsert a single page.
     *
     * @return 'indexed'|'skipped'|'failed'
     */
    private function indexPage(string $url): string
    {
        $response = $this->fetch($url);

        if (! $response || ! $this->isSuccess($response) || ! $this->isHtml($response)) {
            if ($page = KbPage::where('url', $url)->first()) {
                $page->update(['status' => 'failed']);
            }

            return 'failed';
        }

        $extracted = $this->extract((string) $response->getBody());

        if ($extracted['noindex']) {
            return 'skipped';
        }

        $content = $extracted['content'];
        $hash = md5($content);

        $existing = KbPage::where('url', $url)->first();
        if ($existing && $existing->content_hash === $hash) {
            return 'skipped';
        }

        $excerpt = mb_substr($content, 0, 6000);
        $vector = $this->embedder->embed($excerpt);

        KbPage::updateOrCreate(['url' => $url], [
            'title' => $extracted['title'],
            'description' => $extracted['description'],
            'og_image' => $extracted['og_image'],
            'content' => mb_substr($content, 0, 12000),
            'content_hash' => $hash,
            'embedding' => $vector,
            'status' => 'ok',
            'indexed_at' => now(),
        ]);

        return 'indexed';
    }

    /**
     * Extract title/description/og_image/content/noindex from HTML.
     *
     * @return array{title:?string,description:?string,og_image:?string,content:string,noindex:bool}
     */
    private function extract(string $html): array
    {
        $dom = $this->loadDom($html);

        $title = $this->firstNodeText($dom, 'title');

        $description = $this->metaContent($dom, 'name', 'description')
            ?? $this->metaContent($dom, 'property', 'og:description');

        $ogImage = $this->metaContent($dom, 'property', 'og:image');

        $robots = $this->metaContent($dom, 'name', 'robots') ?? '';
        $noindex = str_contains(strtolower($robots), 'noindex');

        // Strip non-content nodes before reading visible text.
        foreach (['script', 'style', 'noscript', 'template', 'svg'] as $tag) {
            $nodes = iterator_to_array($dom->getElementsByTagName($tag));
            foreach ($nodes as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        $text = $body ? $body->textContent : $dom->textContent;
        $content = trim(preg_replace('/\s+/u', ' ', $text ?? ''));

        return [
            'title' => $title,
            'description' => $description,
            'og_image' => $ogImage,
            'content' => $content,
            'noindex' => $noindex,
        ];
    }

    private function loadDom(string $html): DOMDocument
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    private function firstNodeText(DOMDocument $dom, string $tag): ?string
    {
        $node = $dom->getElementsByTagName($tag)->item(0);
        if (! $node) {
            return null;
        }

        $text = trim($node->textContent);

        return $text === '' ? null : $text;
    }

    private function metaContent(DOMDocument $dom, string $attr, string $value): ?string
    {
        foreach ($dom->getElementsByTagName('meta') as $meta) {
            /** @var DOMElement $meta */
            if (strcasecmp($meta->getAttribute($attr), $value) === 0) {
                $content = $meta->getAttribute('content');

                return $content === '' ? null : $content;
            }
        }

        return null;
    }

    private function loadRobots(string $origin): void
    {
        $this->disallowed = [];

        $response = $this->fetch("{$origin}/robots.txt");
        if (! $response || ! $this->isSuccess($response)) {
            return; // Treat as no restrictions.
        }

        $applies = false;
        foreach (preg_split('/\r\n|\r|\n/', (string) $response->getBody()) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$field, $val] = array_pad(explode(':', $line, 2), 2, '');
            $field = strtolower(trim($field));
            $val = trim($val);

            if ($field === 'user-agent') {
                $applies = $val === '*';
            } elseif ($field === 'disallow' && $applies && $val !== '') {
                $this->disallowed[] = $val;
            }
        }
    }

    private function isAllowed(string $path): bool
    {
        foreach ($this->disallowed as $prefix) {
            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                return false;
            }
        }

        return true;
    }

    /** Whether a normalized URL is worth fetching for the current crawl. */
    private function isCandidate(string $url, string $host): bool
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return false;
        }

        if (config('fasthelp.kb.same_domain_only') && $parts['host'] !== $host) {
            return false;
        }

        $path = $parts['path'] ?? '/';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== '' && in_array($ext, self::SKIP_EXTENSIONS, true)) {
            return false;
        }

        return $this->isAllowed($path);
    }

    /** Resolve a possibly-relative href against the page URL. */
    private function resolveUrl(string $href, string $pageUrl): ?string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $base = parse_url($pageUrl);
        if ($base === false || empty($base['host'])) {
            return null;
        }

        $scheme = $base['scheme'] ?? 'https';
        $origin = "{$scheme}://{$base['host']}".(isset($base['port']) ? ':'.$base['port'] : '');

        if (str_starts_with($href, '//')) {
            return $scheme.':'.$href;
        }

        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        // Relative to the current directory.
        $basePath = $base['path'] ?? '/';
        $dir = substr($basePath, 0, strrpos($basePath, '/') + 1);
        if ($dir === '') {
            $dir = '/';
        }

        return $origin.$dir.$href;
    }

    /** Strip the #fragment; return null for non-http(s) URLs. */
    private function normalizeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return null;
        }

        if (($pos = strpos($url, '#')) !== false) {
            $url = substr($url, 0, $pos);
        }

        return rtrim($url, ' ');
    }

    private function isSuccess(ResponseInterface $response): bool
    {
        $status = $response->getStatusCode();

        return $status >= 200 && $status < 300;
    }

    private function isHtml(ResponseInterface $response): bool
    {
        $type = strtolower($response->getHeaderLine('Content-Type'));

        // Empty Content-Type is tolerated; only reject clearly non-HTML types.
        if ($type === '') {
            return true;
        }

        return str_contains($type, 'text/html') || str_contains($type, 'application/xhtml');
    }

    private function fetch(string $url): ?ResponseInterface
    {
        try {
            return $this->client()->get($url, ['http_errors' => false]);
        } catch (\Throwable $e) {
            Log::debug('SiteCrawler: fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function client(): Client
    {
        return $this->client ??= new Client([
            'timeout' => 15,
            'headers' => ['User-Agent' => config('fasthelp.kb.user_agent')],
        ]);
    }
}
