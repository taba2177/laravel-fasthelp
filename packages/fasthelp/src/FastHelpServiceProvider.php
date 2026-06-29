<?php

namespace Tabadev\FastHelp;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Tabadev\FastHelp\Contracts\SmartReply;
use Tabadev\FastHelp\Livewire\Widget;
use Tabadev\FastHelp\Services\Gemini\GeminiSmartReply;

class FastHelpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fasthelp.php', 'fasthelp');

        $this->app->bind(SmartReply::class, function () {
            return match (config('fasthelp.ai.driver', 'gemini')) {
                default => new GeminiSmartReply,
            };
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'fasthelp');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'fasthelp');

        $this->registerChannels();
        $this->registerRoutes();
        $this->registerPublishing();
        $this->registerLivewireComponents();
        $this->registerBladeDirectives();
    }

    /**
     * Register the client-facing Livewire widget component.
     *
     * Guarded on class_exists() so the package degrades gracefully if
     * Livewire is somehow absent, even though it is a hard dependency.
     */
    protected function registerLivewireComponents(): void
    {
        if (! class_exists(Livewire::class)) {
            return;
        }

        Livewire::component('fasthelp-widget', Widget::class);
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
     * Register the `@fastHelpWidget` Blade directive, a single drop-in
     * line hosts can add to any page to render the widget plus its
     * published CSS/JS assets.
     */
    protected function registerBladeDirectives(): void
    {
        if (! class_exists(Blade::class)) {
            return;
        }

        Blade::directive('fastHelpWidget', fn () => "<?php echo view('fasthelp::embed')->render(); ?>");
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
