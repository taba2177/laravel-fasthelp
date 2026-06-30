<?php

use Illuminate\Support\Facades\Bus;
use Tabadev\FastHelp\Jobs\CrawlSiteJob;
use Tabadev\FastHelp\Services\Crawl\SiteCrawler;

it('queues a CrawlSiteJob when run without --sync', function () {
    Bus::fake();

    $this->artisan('fasthelp:scan')
        ->assertExitCode(0);

    Bus::assertDispatched(CrawlSiteJob::class);
});

it('dispatches the job with the provided url and max options', function () {
    Bus::fake();

    $this->artisan('fasthelp:scan', ['--url' => 'https://x.test', '--max' => 5])
        ->assertExitCode(0);

    Bus::assertDispatched(CrawlSiteJob::class, function (CrawlSiteJob $job) {
        return $job->baseUrl === 'https://x.test' && $job->maxPages === 5;
    });
});

it('runs the crawl synchronously when --sync is passed', function () {
    $called = false;
    $calledWith = [];

    $fakeCrawler = new class extends SiteCrawler
    {
        public bool $called = false;

        public array $calledWith = [];

        public function __construct() {}

        public function crawl(?string $baseUrl = null, ?int $maxPages = null): array
        {
            $this->called = true;
            $this->calledWith = [$baseUrl, $maxPages];

            return ['discovered' => 1, 'indexed' => 1, 'skipped' => 0, 'failed' => 0];
        }
    };

    $this->app->bind(SiteCrawler::class, fn () => $fakeCrawler);

    $this->artisan('fasthelp:scan', ['--sync' => true, '--url' => 'https://x.test', '--max' => 5])
        ->assertExitCode(0);

    expect($fakeCrawler->called)->toBeTrue()
        ->and($fakeCrawler->calledWith)->toBe(['https://x.test', 5]);
});
