<?php

use Illuminate\Support\Facades\Blade;

it('registers the fastHelpWidget directive', function () {
    expect(Blade::getCustomDirectives())->toHaveKey('fastHelpWidget');
});

it('renders the embed view with the widget markup when enabled', function () {
    config(['fasthelp.widget.enabled' => true]);

    expect(view()->exists('fasthelp::embed'))->toBeTrue();

    $html = null;

    try {
        $html = Blade::render('@fastHelpWidget');
    } catch (Throwable $e) {
        $html = null;
    }

    expect($html)->not->toBeNull()
        ->and($html)->toContain('fasthelp.css')
        ->and($html)->toContain('fasthelp.js');
});

it('renders effectively empty when the widget is disabled', function () {
    config(['fasthelp.widget.enabled' => false]);

    $html = Blade::render('@fastHelpWidget');

    expect(trim($html))->toBe('')
        ->and($html)->not->toContain('fasthelp.css')
        ->and($html)->not->toContain('fasthelp.js');
});
