<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Tests;

use Kaiphpdev\WorkerWatch\WorkerWatchServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            WorkerWatchServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set(
            'worker-watch.enabled',
            true,
        );

        $app['config']->set(
            'worker-watch.heartbeat.stale_after',
            60,
        );

        $app['config']->set(
            'worker-watch.heartbeat.critical_after',
            180,
        );

        $app['config']->set(
            'worker-watch.jobs.long_running_after',
            300,
        );

        $app['config']->set(
            'worker-watch.jobs.critical_after',
            900,
        );

        $app['config']->set(
            'worker-watch.retention',
            3600,
        );
    }
}
