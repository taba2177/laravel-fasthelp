<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Tabadev\FastHelp\Models\KbPage;

it('creates a KbPage via factory', function () {
    $page = KbPage::factory()->create();

    expect($page->exists)->toBeTrue()
        ->and($page->url)->not->toBeNull()
        ->and($page->title)->not->toBeNull();
});

it('round-trips the embedding array via cast', function () {
    $embedding = [0.1, 0.2, 0.3, 0.9876543210];

    $page = KbPage::factory()->create(['embedding' => $embedding]);

    $fresh = KbPage::find($page->id);

    expect($fresh->embedding)->toBeArray()
        ->and($fresh->embedding)->toHaveCount(4)
        ->and($fresh->embedding[0])->toBeFloat()
        ->and($fresh->embedding)->toBe($embedding);
});

it('casts indexed_at as a datetime', function () {
    $page = KbPage::factory()->create(['indexed_at' => now()]);

    expect($page->indexed_at)->toBeInstanceOf(Carbon::class);
});

it('enforces url uniqueness', function () {
    KbPage::factory()->create(['url' => 'https://example.com/page']);

    expect(fn () => KbPage::factory()->create(['url' => 'https://example.com/page']))
        ->toThrow(QueryException::class);
});

it('stores a null embedding without error', function () {
    $page = KbPage::factory()->create(['embedding' => null]);

    expect(KbPage::find($page->id)->embedding)->toBeNull();
});
