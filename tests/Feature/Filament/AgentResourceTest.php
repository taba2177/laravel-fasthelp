<?php

use Tabadev\FastHelp\Filament\Pages\FastHelpSettings;
use Tabadev\FastHelp\Filament\Resources\AgentResource;
use Tabadev\FastHelp\Models\Agent;

it('binds the agent model to the resource', function () {
    expect(AgentResource::getModel())->toBe(Agent::class);
});

it('registers the index, create and edit pages', function () {
    expect(AgentResource::getPages())->toHaveKeys(['index', 'create', 'edit']);
});

it('exposes the expected navigation group and label', function () {
    expect(AgentResource::getNavigationLabel())->toBe('Agents')
        ->and(AgentResource::getNavigationGroup())->toBe('FastHelp');
});

it('instantiates the settings page with the expected slug', function () {
    expect(FastHelpSettings::getSlug())->toBe('fasthelp-settings')
        ->and(FastHelpSettings::getNavigationLabel())->toBe('FastHelp Settings')
        ->and(FastHelpSettings::getNavigationGroup())->toBe('FastHelp');
});
