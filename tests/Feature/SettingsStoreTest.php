<?php

use Tabadev\FastHelp\Models\Setting;
use Tabadev\FastHelp\Support\Settings;

it('returns the config default when no override row exists', function () {
    config(['fasthelp.widget.title' => 'Need help?']);

    expect(app(Settings::class)->get('widget.title'))->toBe('Need help?');
});

it('returns the stored override after set', function () {
    $settings = app(Settings::class);

    $settings->set('widget.title', 'Hello');

    expect($settings->get('widget.title'))->toBe('Hello');
});

it('round-trips an array value', function () {
    $settings = app(Settings::class);

    $settings->set('ai.handoff_keywords', ['human', 'agent']);

    expect($settings->get('ai.handoff_keywords'))->toBe(['human', 'agent']);
});

it('round-trips a true boolean value', function () {
    $settings = app(Settings::class);

    $settings->set('ai.enabled', true);

    expect($settings->get('ai.enabled'))->toBe(true);
});

it('round-trips a false boolean value', function () {
    $settings = app(Settings::class);

    $settings->set('ai.enabled', false);

    expect($settings->get('ai.enabled'))->toBe(false);
});

it('overwrites an existing row on set (upsert, not duplicate)', function () {
    $settings = app(Settings::class);

    $settings->set('widget.title', 'First');
    $settings->set('widget.title', 'Second');

    expect($settings->get('widget.title'))->toBe('Second')
        ->and(Setting::query()->where('key', 'widget.title')->count())->toBe(1);
});

it('falls back to a given default when there is no row and no config value', function () {
    $settings = app(Settings::class);

    expect($settings->get('totally.unknown.key', 'fallback'))->toBe('fallback');
});

it('returns all stored overrides keyed by setting key', function () {
    $settings = app(Settings::class);

    $settings->set('widget.title', 'Hello');
    $settings->set('ai.enabled', true);

    expect($settings->all())->toBe([
        'widget.title' => 'Hello',
        'ai.enabled' => true,
    ]);
});
