<?php

namespace Tabadev\FastHelp\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Tabadev\FastHelp\Filament\Pages\FastHelpSettings;
use Tabadev\FastHelp\Filament\Resources\AgentResource;
use Tabadev\FastHelp\Filament\Resources\ConversationResource;
use Tabadev\FastHelp\Filament\Resources\KnowledgePageResource;

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
        $panel->resources($this->resources())
            ->pages($this->pages());
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * The set of Filament resources registered by this plugin.
     *
     * @return array<class-string>
     */
    protected function resources(): array
    {
        return [
            ConversationResource::class,
            AgentResource::class,
            KnowledgePageResource::class,
        ];
    }

    /**
     * The set of standalone Filament pages registered by this plugin.
     *
     * @return array<class-string>
     */
    protected function pages(): array
    {
        return [
            FastHelpSettings::class,
        ];
    }
}
