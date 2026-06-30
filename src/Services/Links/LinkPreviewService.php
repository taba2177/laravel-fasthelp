<?php

namespace Tabadev\FastHelp\Services\Links;

use DOMDocument;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Tabadev\FastHelp\Models\KbPage;
use Tabadev\FastHelp\Models\LinkPreview;

class LinkPreviewService
{
    public function __construct(private ?Client $client = null) {}

    /**
     * Resolve preview metadata for a single URL.
     *
     * Priority: internal KbPage → cached LinkPreview → live fetch.
     *
     * @return array{url:string,title:?string,description:?string,image:?string,internal:bool}|null
     */
    public function for(string $url): ?array
    {
        $url = $this->normalize($url);
        if ($url === null) {
            return null;
        }

        // 1. Internal: KbPage wins.
        $page = KbPage::where('url', $url)->first();
        if ($page) {
            return [
                'url' => $url,
                'title' => $page->title,
                'description' => $page->description,
                'image' => $page->og_image,
                'internal' => true,
            ];
        }

        // 2. Cache: already fetched.
        $cached = LinkPreview::where('url', $url)->first();
        if ($cached) {
            // A cached row with all-null preview data = negative result → return null.
            if ($cached->title === null && $cached->description === null && $cached->image === null) {
                return null;
            }

            return [
                'url' => $url,
                'title' => $cached->title,
                'description' => $cached->description,
                'image' => $cached->image,
                'internal' => false,
            ];
        }

        // 3. Fetch from the network.
        return $this->fetchPreview($url);
    }

    /**
     * Extract all http(s) URLs from a message body, resolve previews, and
     * return a deduplicated list (order-preserving) with nulls dropped.
     *
     * @return list<array{url:string,title:?string,description:?string,image:?string,internal:bool}>
     */
    public function previewsFor(string $body): array
    {
        $urls = $this->extractUrls($body);
        $results = [];

        foreach ($urls as $url) {
            $preview = $this->for($url);
            if ($preview !== null) {
                $results[] = $preview;
            }
        }

        return $results;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Validate and normalise a URL; return null if not http/https.
     */
    private function normalize(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            return null;
        }

        // Strip fragment.
        if (($pos = strpos($url, '#')) !== false) {
            $url = substr($url, 0, $pos);
        }

        return $url;
    }

    /**
     * Extract unique http(s) URLs from a text body, trimming trailing punctuation.
     *
     * @return list<string>
     */
    private function extractUrls(string $body): array
    {
        if (! preg_match_all('/\bhttps?:\/\/[^\s<>"\')\]]+/i', $body, $matches)) {
            return [];
        }

        $seen = [];
        $urls = [];

        foreach ($matches[0] as $raw) {
            // Trim trailing punctuation characters that are unlikely to be part of the URL.
            $url = rtrim($raw, '.,;:!?)\'');

            if (! isset($seen[$url])) {
                $seen[$url] = true;
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * Fetch a URL, parse OG/meta tags, persist to LinkPreview, and return.
     *
     * @return array{url:string,title:?string,description:?string,image:?string,internal:bool}|null
     */
    private function fetchPreview(string $url): ?array
    {
        try {
            $response = $this->client()->get($url, ['http_errors' => false]);
        } catch (\Throwable) {
            return null;
        }

        if (! $this->isSuccess($response) || ! $this->isHtml($response)) {
            return null;
        }

        $html = (string) $response->getBody();
        $meta = $this->parseMeta($html);

        // Nothing useful parsed — skip persisting.
        if ($meta['title'] === null && $meta['description'] === null && $meta['image'] === null) {
            return null;
        }

        LinkPreview::updateOrCreate(
            ['url' => $url],
            [
                'title' => $meta['title'],
                'description' => $meta['description'],
                'image' => $meta['image'],
                'fetched_at' => now(),
            ]
        );

        return [
            'url' => $url,
            'title' => $meta['title'],
            'description' => $meta['description'],
            'image' => $meta['image'],
            'internal' => false,
        ];
    }

    /**
     * Parse OG and standard meta tags from an HTML string.
     *
     * Preference: og:title → <title>, og:description → meta[name=description], og:image.
     *
     * @return array{title:?string,description:?string,image:?string}
     */
    private function parseMeta(string $html): array
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $title = null;
        $description = null;
        $image = null;

        foreach ($dom->getElementsByTagName('meta') as $meta) {
            /** @var \DOMElement $meta */
            $property = strtolower($meta->getAttribute('property'));
            $name = strtolower($meta->getAttribute('name'));
            $content = $meta->getAttribute('content');

            if ($content === '') {
                continue;
            }

            if ($property === 'og:title' && $title === null) {
                $title = $content;
            } elseif ($property === 'og:description' && $description === null) {
                $description = $content;
            } elseif ($property === 'og:image' && $image === null) {
                $image = $content;
            } elseif ($name === 'description' && $description === null) {
                $description = $content;
            }
        }

        // Fallback: <title> tag when og:title was not found.
        if ($title === null) {
            $node = $dom->getElementsByTagName('title')->item(0);
            if ($node) {
                $text = trim($node->textContent);
                if ($text !== '') {
                    $title = $text;
                }
            }
        }

        return compact('title', 'description', 'image');
    }

    private function isSuccess(ResponseInterface $response): bool
    {
        $status = $response->getStatusCode();

        return $status >= 200 && $status < 300;
    }

    private function isHtml(ResponseInterface $response): bool
    {
        $type = strtolower($response->getHeaderLine('Content-Type'));

        if ($type === '') {
            return true;
        }

        return str_contains($type, 'text/html') || str_contains($type, 'application/xhtml');
    }

    private function client(): Client
    {
        return $this->client ??= new Client([
            'timeout' => 8,
            'headers' => ['User-Agent' => config('fasthelp.kb.user_agent')],
        ]);
    }
}
