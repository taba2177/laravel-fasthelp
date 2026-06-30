<?php

use Tabadev\FastHelp\Filament\FastHelpPlugin;
use Tabadev\FastHelp\Filament\Resources\KnowledgePageResource;
use Tabadev\FastHelp\Models\KbPage;
use Tabadev\FastHelp\Support\Settings;

it('binds the KbPage model to the resource', function () {
    expect(KnowledgePageResource::getModel())->toBe(KbPage::class);
});

it('registers the index page', function () {
    expect(KnowledgePageResource::getPages())->toHaveKey('index');
});

it('exposes the expected navigation group and label', function () {
    expect(KnowledgePageResource::getNavigationLabel())->toBe('Knowledge Base')
        ->and(KnowledgePageResource::getNavigationGroup())->toBe('FastHelp');
});

it('instantiates the plugin without error', function () {
    $plugin = FastHelpPlugin::make();
    expect($plugin)->toBeInstanceOf(FastHelpPlugin::class)
        ->and($plugin->getId())->toBe('fasthelp');
});

it('round-trips a kb.enabled setting', function () {
    $settings = app(Settings::class);
    $settings->set('kb.enabled', true);
    expect($settings->get('kb.enabled'))->toBeTrue();
});
