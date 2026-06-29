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
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'fasthelp');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'fasthelp');

        $this->registerChannels();
        $this->registerRoutes();
        $this->registerPublishing();
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

    /**
     * Load the widget's HTTP routes, if present.
     *
     * The routes file is a stub until Task 11 populates it; guarding on
     * file_exists() keeps this safe regardless of package state.
     */
    protected function registerRoutes(): void
    {
        if (! file_exists($routes = __DIR__.'/../routes/web.php')) {
            return;
        }

        $this->loadRoutesFrom($routes);
    }

    /**
     * Register all publishable asset groups (config, migrations, views,
     * translations, and front-end assets).
     */
    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/fasthelp.php' => config_path('fasthelp.php'),
        ], 'fasthelp-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'fasthelp-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/fasthelp'),
        ], 'fasthelp-views');

        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path('vendor/fasthelp'),
        ], 'fasthelp-translations');

        $this->publishes([
            __DIR__.'/../resources/dist' => public_path('vendor/fasthelp'),
        ], 'fasthelp-assets');
    }
}
