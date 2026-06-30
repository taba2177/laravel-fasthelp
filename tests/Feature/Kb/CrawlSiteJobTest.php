<?php

use Tabadev\FastHelp\Jobs\CrawlSiteJob;
use Tabadev\FastHelp\Services\Crawl\SiteCrawler;

it('calls crawl on the SiteCrawler with the provided base url and max pages', function () {
    $fakeCrawler = new class extends SiteCrawler
    {
        public bool $called = false;

        public array $calledWith = [];

        public function __construct() {}

        public function crawl(?string $baseUrl = null, ?int $maxPages = null): array
        {
            $this->called = true;
            $this->calledWith = [$baseUrl, $maxPages];

            return ['discovered' => 3, 'indexed' => 3, 'skipped' => 0, 'failed' => 0];
        }
    };

    $this->app->bind(SiteCrawler::class, fn () => $fakeCrawler);

    $job = new CrawlSiteJob('https://x.test', 7);
    $job->handle(app(SiteCrawler::class));

    expect($fakeCrawler->called)->toBeTrue()
        ->and($fakeCrawler->calledWith)->toBe(['https://x.test', 7]);
});

it('passes null values when constructed without arguments', function () {
    $fakeCrawler = new class extends SiteCrawler
    {
        public array $calledWith = ['not-called', 'not-called'];

        public function __construct() {}

        public function crawl(?string $baseUrl = null, ?int $maxPages = null): array
        {
            $this->calledWith = [$baseUrl, $maxPages];

            return ['discovered' => 0, 'indexed' => 0, 'skipped' => 0, 'failed' => 0];
        }
    };

    $this->app->bind(SiteCrawler::class, fn () => $fakeCrawler);

    $job = new CrawlSiteJob;
    $job->handle(app(SiteCrawler::class));

    expect($fakeCrawler->calledWith)->toBe([null, null]);
});
