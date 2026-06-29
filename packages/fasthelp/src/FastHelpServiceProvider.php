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
    }
}
