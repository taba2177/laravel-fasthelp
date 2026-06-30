<?php

use Illuminate\Support\Carbon;
use Tabadev\FastHelp\Models\LinkPreview;

it('creates a LinkPreview via factory', function () {
    $preview = LinkPreview::factory()->create();

    expect($preview->exists)->toBeTrue()
        ->and($preview->url)->not->toBeNull()
        ->and($preview->title)->not->toBeNull();
});

it('persists link preview fields', function () {
    $preview = LinkPreview::factory()->create([
        'url' => 'https://example.com',
        'title' => 'Example Title',
        'description' => 'An example description.',
        'image' => 'https://example.com/image.png',
    ]);

    $fresh = LinkPreview::find($preview->id);

    expect($fresh->url)->toBe('https://example.com')
        ->and($fresh->title)->toBe('Example Title')
        ->and($fresh->description)->toBe('An example description.')
        ->and($fresh->image)->toBe('https://example.com/image.png');
});

it('casts fetched_at as a datetime', function () {
    $preview = LinkPreview::factory()->create(['fetched_at' => now()]);

    expect($preview->fetched_at)->toBeInstanceOf(Carbon::class);
});

it('allows null fetched_at', function () {
    $preview = LinkPreview::factory()->create(['fetched_at' => null]);

    expect(LinkPreview::find($preview->id)->fetched_at)->toBeNull();
});
