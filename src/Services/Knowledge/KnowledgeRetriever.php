<?php

namespace Tabadev\FastHelp\Services\Knowledge;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tabadev\FastHelp\Contracts\Embedder;
use Tabadev\FastHelp\Models\KbPage;
use Tabadev\FastHelp\Support\Vectors;

class KnowledgeRetriever
{
    public function __construct(private Embedder $embedder) {}

    /**
     * Retrieve the most relevant KB pages for the given query.
     *
     * Returns a Collection of associative arrays with keys:
     *   url, title, description, excerpt, score
     *
     * Never throws — degrades to an empty collection on any failure.
     */
    public function retrieve(string $query, ?int $k = null): Collection
    {
        if (blank($query)) {
            return collect();
        }

        $vector = $this->embedder->embed($query);

        if (empty($vector)) {
            return collect();
        }

        $minSimilarity = config('fasthelp.kb.min_similarity', 0.65);
        $topK = $k ?? config('fasthelp.kb.retrieve_top_k', 4);

        $candidates = KbPage::query()->whereNotNull('embedding')->get();

        return $candidates
            ->map(function (KbPage $page) use ($vector): array {
                return [
                    'url' => $page->url,
                    'title' => $page->title,
                    'description' => $page->description,
                    'excerpt' => Str::limit($page->content, 600),
                    'score' => round(Vectors::cosine($vector, $page->embedding), 4),
                ];
            })
            ->filter(fn (array $item): bool => $item['score'] >= $minSimilarity)
            ->sortByDesc('score')
            ->take($topK)
            ->values();
    }
}
