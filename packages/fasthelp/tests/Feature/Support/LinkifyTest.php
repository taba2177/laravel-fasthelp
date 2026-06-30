<?php

use Tabadev\FastHelp\Support\Linkify;

it('wraps a bare URL in an anchor tag', function () {
    $html = Linkify::toHtml('Visit https://example.com for info');

    expect($html)->toContain('<a href="https://example.com"')
        ->and($html)->toContain('https://example.com</a>')
        ->and($html)->toContain('target="_blank"')
        ->and($html)->toContain('rel="noopener noreferrer"');
});

it('HTML-escapes special characters so no raw tags appear', function () {
    $html = Linkify::toHtml('<script>alert(1)</script>');

    expect($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;');
});

it('keeps trailing punctuation outside the anchor', function () {
    $html = Linkify::toHtml('visit https://x.test/page.');

    // The dot must be outside the anchor
    expect($html)->toContain('<a href="https://x.test/page"')
        ->and($html)->toContain('https://x.test/page</a>.')
        ->and($html)->not->toContain('href="https://x.test/page."');
});

it('returns the escaped input unchanged when there is no URL', function () {
    $result = Linkify::toHtml('Hello & goodbye');

    expect($result)->toBe('Hello &amp; goodbye');
});

it('returns an empty string for null input', function () {
    expect(Linkify::toHtml(null))->toBe('');
});

it('returns an empty string for empty string input', function () {
    expect(Linkify::toHtml(''))->toBe('');
});

it('does not double-escape ampersands in URLs', function () {
    $html = Linkify::toHtml('Go to https://example.com/search?q=a&b=c now');

    // The URL contains & which htmlspecialchars converts to &amp; - the href should have the escaped form
    expect($html)->toContain('href="https://example.com/search?q=a&amp;b=c"');
});
