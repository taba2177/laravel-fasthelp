<?php

namespace Tabadev\FastHelp\Console;

use Illuminate\Console\Command;
use Tabadev\FastHelp\Jobs\CrawlSiteJob;
use Tabadev\FastHelp\Services\Crawl\SiteCrawler;

class ScanSiteCommand extends Command
{
    protected $signature = 'fasthelp:scan
        {--url= : Base URL to crawl}
        {--max= : Max pages}
        {--sync : Run now instead of queueing}';

    protected $description = 'Crawl the website into the FastHelp knowledge base.';

    public function handle(): int
    {
        $url = $this->option('url') ?: null;
        $max = $this->option('max') !== null ? (int) $this->option('max') : null;

        if ($this->option('sync')) {
            $summary = app(SiteCrawler::class)->crawl($url, $max);

            $this->info("FastHelp crawl complete — discovered: {$summary['discovered']}, indexed: {$summary['indexed']}, skipped: {$summary['skipped']}, failed: {$summary['failed']}.");

            return self::SUCCESS;
        }

        CrawlSiteJob::dispatch($url, $max);

        $this->info('FastHelp crawl queued.');

        return self::SUCCESS;
    }
}
