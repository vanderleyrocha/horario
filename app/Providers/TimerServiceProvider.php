<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class TimerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        app()->instance('app.start_time', microtime(true));
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
