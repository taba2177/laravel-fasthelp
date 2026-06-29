<?php

use Tabadev\FastHelp\Filament\FastHelpPlugin;
use Tabadev\FastHelp\Filament\Resources\ConversationResource;
use Tabadev\FastHelp\Models\Conversation;

it('binds the conversation model to the resource', function () {
    expect(ConversationResource::getModel())->toBe(Conversation::class);
});

it('registers the index and view pages', function () {
    expect(ConversationResource::getPages())->toHaveKeys(['index', 'view']);
});

it('exposes a plugin instance with the expected id', function () {
    $plugin = FastHelpPlugin::make();

    expect($plugin)->toBeInstanceOf(FastHelpPlugin::class)
        ->and($plugin->getId())->toBe('fasthelp');
});
