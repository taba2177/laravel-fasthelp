<?php

namespace Tabadev\FastHelp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Tabadev\FastHelp\Services\Crawl\SiteCrawler;

class CrawlSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?string $baseUrl = null,
        public ?int $maxPages = null
    ) {}

    public function handle(SiteCrawler $crawler): void
    {
        $summary = $crawler->crawl($this->baseUrl, $this->maxPages);

        Log::info('FastHelp crawl completed', $summary);
    }
}
