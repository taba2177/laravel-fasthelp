<?php

use Illuminate\Support\Str;
use Tabadev\FastHelp\Contracts\Embedder;
use Tabadev\FastHelp\Models\KbPage;
use Tabadev\FastHelp\Services\Knowledge\KnowledgeRetriever;

/**
 * Fake embedder that returns a pre-configured vector for any query.
 */
function fakeRetrieverEmbedder(?array $vector): Embedder
{
    return new class($vector) implements Embedder
    {
        public int $calls = 0;

        public function __construct(private ?array $vector) {}

        public function embed(string $text): ?array
        {
            $this->calls++;

            return $this->vector;
        }
    };
}

beforeEach(function () {
    config([
        'fasthelp.kb.retrieve_top_k' => 4,
        'fasthelp.kb.min_similarity' => 0.65,
    ]);
});

it('returns pages ordered by cosine similarity descending, respecting top-k', function () {
    // pricing = [1,0,0], about = [0,1,0], plans = [0.9,0.1,0]
    // Query vector [1,0,0] → pricing cos=1.0, plans cos≈0.994, about cos=0.0
    KbPage::factory()->create(['url' => 'https://example.test/pricing', 'title' => 'Pricing', 'embedding' => [1, 0, 0]]);
    KbPage::factory()->create(['url' => 'https://example.test/about', 'title' => 'About', 'embedding' => [0, 1, 0]]);
    KbPage::factory()->create(['url' => 'https://example.test/plans', 'title' => 'Plans', 'embedding' => [0.9, 0.1, 0]]);

    $embedder = fakeRetrieverEmbedder([1, 0, 0]);
    $retriever = new KnowledgeRetriever($embedder);

    $results = $retriever->retrieve('pricing', k: 2);

    expect($results)->toHaveCount(2);
    expect($results[0]['url'])->toBe('https://example.test/pricing');
    expect($results[1]['url'])->toBe('https://example.test/plans');
});

it('excludes pages whose cosine similarity is below min_similarity', function () {
    // With min_similarity=0.95, only pages very close to [1,0,0] should be returned.
    // pricing [1,0,0] cos=1.0 ✓, about [0,1,0] cos=0.0 ✗, plans [0.9,0.1,0] cos≈0.994 ✓
    KbPage::factory()->create(['url' => 'https://example.test/pricing', 'title' => 'Pricing', 'embedding' => [1, 0, 0]]);
    KbPage::factory()->create(['url' => 'https://example.test/about', 'title' => 'About', 'embedding' => [0, 1, 0]]);
    KbPage::factory()->create(['url' => 'https://example.test/plans', 'title' => 'Plans', 'embedding' => [0.9, 0.1, 0]]);

    config(['fasthelp.kb.min_similarity' => 0.95]);

    $embedder = fakeRetrieverEmbedder([1, 0, 0]);
    $retriever = new KnowledgeRetriever($embedder);

    $results = $retriever->retrieve('pricing');

    // Only pricing (1.0) and plans (~0.994) are >= 0.95; about (0.0) is excluded.
    $urls = $results->pluck('url')->all();
    expect($urls)->toContain('https://example.test/pricing')
        ->and($urls)->toContain('https://example.test/plans')
        ->and($urls)->not->toContain('https://example.test/about');
});

it('returns an empty collection for a blank query without calling the embedder', function () {
    $embedder = fakeRetrieverEmbedder([1, 0, 0]);
    $retriever = new KnowledgeRetriever($embedder);

    $results = $retriever->retrieve('   ');

    expect($results)->toBeEmpty();
    expect($embedder->calls)->toBe(0);
});

it('returns an empty collection when the embedder returns null', function () {
    KbPage::factory()->create(['url' => 'https://example.test/pricing', 'embedding' => [1, 0, 0]]);

    $embedder = fakeRetrieverEmbedder(null);
    $retriever = new KnowledgeRetriever($embedder);

    $results = $retriever->retrieve('some query');

    expect($results)->toBeEmpty();
});

it('ignores KbPages that have a null embedding', function () {
    KbPage::factory()->create(['url' => 'https://example.test/no-embed', 'embedding' => null]);
    KbPage::factory()->create(['url' => 'https://example.test/has-embed', 'embedding' => [1, 0, 0]]);

    $embedder = fakeRetrieverEmbedder([1, 0, 0]);
    $retriever = new KnowledgeRetriever($embedder);

    $results = $retriever->retrieve('query');

    $urls = $results->pluck('url')->all();
    expect($urls)->not->toContain('https://example.test/no-embed')
        ->and($urls)->toContain('https://example.test/has-embed');
});

it('returns items with the required keys: url, title, description, excerpt, score', function () {
    KbPage::factory()->create([
        'url' => 'https://example.test/pricing',
        'title' => 'Pricing Page',
        'description' => 'All about pricing',
        'content' => str_repeat('a', 800),
        'embedding' => [1, 0, 0],
    ]);

    $embedder = fakeRetrieverEmbedder([1, 0, 0]);
    $retriever = new KnowledgeRetriever($embedder);

    $results = $retriever->retrieve('pricing');

    expect($results)->toHaveCount(1);

    $item = $results->first();
    expect($item)->toHaveKey('url')
        ->and($item)->toHaveKey('title')
        ->and($item)->toHaveKey('description')
        ->and($item)->toHaveKey('excerpt')
        ->and($item)->toHaveKey('score');

    expect($item['url'])->toBe('https://example.test/pricing');
    expect($item['title'])->toBe('Pricing Page');
    expect($item['description'])->toBe('All about pricing');
    // excerpt is Str::limit($page->content, 600) so it should be <= 603 chars (600 + '...')
    expect(strlen($item['excerpt']))->toBeLessThanOrEqual(603);
    expect($item['score'])->toBeFloat();
});
