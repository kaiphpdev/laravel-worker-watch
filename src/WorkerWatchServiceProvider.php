<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStarting;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Kaiphpdev\WorkerWatch\Commands\WorkerWatchStatusCommand;
use Kaiphpdev\WorkerWatch\Contracts\WorkerStore;
use Kaiphpdev\WorkerWatch\Listeners\QueueWorkerListener;
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

        $this->app->singleton(
            WorkerWatchManager::class,
            fn ($app): WorkerWatchManager => new WorkerWatchManager(
                store: $app->make(WorkerStore::class),
                events: $app->make(
                    Dispatcher::class
                ),
            )
        );

        $this->app->singleton(
            QueueWorkerListener::class,
            fn ($app): QueueWorkerListener => new QueueWorkerListener(
                manager: $app->make(WorkerWatchManager::class),
            )
        );

    }

    public function boot(): void
    {
        if (! (bool) config('worker-watch.enabled', true)) {
            return;
        }

        $this->registerQueueListeners();

        if ($this->app->runningInConsole()) {
            $this->commands([
                WorkerWatchStatusCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/worker-watch.php' => config_path('worker-watch.php'),
            ], 'worker-watch-config');
        }
    }

    private function registerQueueListeners(): void
    {
        Event::listen(
            WorkerStarting::class,
            [QueueWorkerListener::class, 'workerStarting'],
        );

        Event::listen(
            Looping::class,
            [QueueWorkerListener::class, 'looping'],
        );

        Event::listen(
            JobProcessing::class,
            [QueueWorkerListener::class, 'jobProcessing'],
        );

        Event::listen(
            JobProcessed::class,
            [QueueWorkerListener::class, 'jobProcessed'],
        );

        Event::listen(
            JobExceptionOccurred::class,
            [QueueWorkerListener::class, 'jobExceptionOccurred'],
        );

        Event::listen(
            WorkerStopping::class,
            [QueueWorkerListener::class, 'workerStopping'],
        );
    }
}
