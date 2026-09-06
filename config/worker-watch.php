<?php

declare(strict_types=1);

return [

    /*
    |
    | Globally enable or disable worker monitoring.
    |
    */

    'enabled' => env('WORKER_WATCH_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    */

    'store' => env('WORKER_WATCH_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Heartbeat: Workers should periodically update their heartbeat.
    |--------------------------------------------------------------------------
    */

    'heartbeat' => [

        'interval' => (int) env(
            'WORKER_WATCH_HEARTBEAT_INTERVAL',
            15
        ),

        'stale_after' => (int) env(
            'WORKER_WATCH_STALE_AFTER',
            60
        ),

        'critical_after' => (int) env(
            'WORKER_WATCH_CRITICAL_AFTER',
            180
        ),

    ],

    /*
    |--------------------------------------------------------------------------
    | Long Running Jobs
    |--------------------------------------------------------------------------
    */

    'jobs' => [

        'long_running_after' => (int) env(
            'WORKER_WATCH_LONG_JOB_AFTER',
            300
        ),

        'critical_after' => (int) env(
            'WORKER_WATCH_CRITICAL_JOB_AFTER',
            900
        ),

    ],

    /*
    |--------------------------------------------------------------------------
    | Worker History
    |--------------------------------------------------------------------------
    */

    'retention' => (int) env(
        'WORKER_WATCH_RETENTION',
        3600
    ),

    'capacity' => [

        'enabled' => env(
            'WORKER_WATCH_CAPACITY_ENABLED',
            true
        ),

        'expected' => [

            'redis:default' => (int) env(
                'WORKER_WATCH_DEFAULT_WORKERS',
                1
            ),

        ],

    ],

];
