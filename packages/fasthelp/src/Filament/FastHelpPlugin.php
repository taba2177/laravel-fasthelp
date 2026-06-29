<?php

namespace Tabadev\FastHelp\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Tabadev\FastHelp\Filament\Resources\ConversationResource;

class FastHelpPlugin implements Plugin
{
    public function getId(): string
    {
        return 'fasthelp';
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function register(Panel $panel): void
    {
        $panel->resources($this->resources());
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * The set of Filament resources registered by this plugin.
     *
     * Kept as its own method so a future task can append AgentResource
     * (and any others) without touching register().
     *
     * @return array<class-string>
     */
    protected function resources(): array
    {
        return [
            ConversationResource::class,
        ];
    }
}
