<?php

use Illuminate\Support\Facades\Blade;
use Tabadev\FastHelp\Support\Settings;

it('config widget.auto_theme exists and is a bool', function () {
    $value = config('fasthelp.widget.auto_theme');

    expect($value)->toBeBool();
});

it('config widget.auto_theme defaults to true', function () {
    expect(config('fasthelp.widget.auto_theme'))->toBeTrue();
});

it('Settings round-trips widget.auto_theme', function () {
    $settings = app(Settings::class);

    $settings->set('widget.auto_theme', false);

    expect($settings->get('widget.auto_theme'))->toBeFalse();
});

it('embed output contains the fhAuto variable when widget is enabled', function () {
    config(['fasthelp.widget.enabled' => true]);

    $html = null;

    try {
        $html = Blade::render('@fastHelpWidget');
    } catch (Throwable $e) {
        $html = null;
    }

    expect($html)->not->toBeNull()
        ->and($html)->toContain('fhAuto');
});
