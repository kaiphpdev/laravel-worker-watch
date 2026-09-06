<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch;

use Illuminate\Support\ServiceProvider;

final class WorkerWatchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/worker-watch.php',
            'worker-watch'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/worker-watch.php' => config_path('worker-watch.php'),
            ], 'worker-watch-config');
        }
    }
}
