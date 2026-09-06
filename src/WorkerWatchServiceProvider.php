<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\ServiceProvider;
use Kaiphpdev\WorkerWatch\Contracts\WorkerStore;
use Kaiphpdev\WorkerWatch\Stores\CacheWorkerStore;

final class WorkerWatchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/worker-watch.php',
            'worker-watch'
        );

        $this->app->singleton(
            WorkerStore::class,
            function ($app): WorkerStore {
                /** @var CacheFactory $cache */
                $cache = $app->make(CacheFactory::class);

                $store = config('worker-watch.store');

                $repository = $store !== null
                    ? $cache->store((string) $store)
                    : $cache->store();

                return new CacheWorkerStore(
                    cache: $repository,
                    retention: (int) config(
                        'worker-watch.retention',
                        3600
                    ),
                );
            }
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
