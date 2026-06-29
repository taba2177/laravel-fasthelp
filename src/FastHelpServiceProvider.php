<?php

namespace Tabadev\FastHelp;

use Illuminate\Support\ServiceProvider;

class FastHelpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fasthelp.php', 'fasthelp');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/fasthelp.php' => config_path('fasthelp.php'),
        ], 'fasthelp-config');

        $this->registerChannels();
    }

    /**
     * Load the broadcast channel authorization callbacks.
     *
     * Note: this does NOT call Broadcast::routes() — the host application
     * owns the /broadcasting/auth route. Broadcast::channel() merely
     * registers authorization callbacks, so this is safe even if the host
     * application has not configured broadcasting at all.
     */
    protected function registerChannels(): void
    {
        if (! file_exists($channels = __DIR__.'/../routes/channels.php')) {
            return;
        }

        require $channels;
    }
}
