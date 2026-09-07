<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Kaiphpdev\WorkerWatch\Contracts\WorkerStore;
use Kaiphpdev\WorkerWatch\Enums\WorkerStatus;
use Kaiphpdev\WorkerWatch\Tests\Fakes\FakeWorkerStore;
use Kaiphpdev\WorkerWatch\Tests\TestCase;
use Kaiphpdev\WorkerWatch\WorkerSnapshot;
use Kaiphpdev\WorkerWatch\WorkerWatchManager;

final class WorkerWatchHealthCommandTest extends TestCase
{
    private FakeWorkerStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Keep capacity disabled by default for health-command tests.
         * Individual tests can enable it explicitly when needed.
         */
        config()->set(
            'worker-watch.capacity.enabled',
            false,
        );

        $this->store = new FakeWorkerStore;

        $this->app->forgetInstance(
            WorkerStore::class
        );

        $this->app->instance(
            WorkerStore::class,
            $this->store,
        );

        /*
         * WorkerWatchManager may already have been resolved by the
         * application container, so remove the existing singleton
         * to ensure it receives our fake WorkerStore.
         */
        $this->app->forgetInstance(
            WorkerWatchManager::class
        );
    }

    public function test_health_command_passes_when_worker_is_healthy(): void
    {
        $this->store->put(
            $this->worker(
                status: WorkerStatus::Healthy,
            )
        );

        $this->artisan('worker-watch:health')
            ->expectsOutput(
                'Worker Watch health check passed.'
            )
            ->assertExitCode(0);
    }

    public function test_health_command_fails_when_worker_is_critical(): void
    {
        $this->store->put(
            $this->worker(
                status: WorkerStatus::Critical,
                lastHeartbeatAt: time() - 500,
            )
        );

        $this->artisan('worker-watch:health')
            ->expectsOutput(
                'Worker Watch health check failed.'
            )
            ->assertExitCode(1);
    }

    public function test_health_command_fails_when_worker_is_stale(): void
    {
        $this->store->put(
            $this->worker(
                status: WorkerStatus::Stale,
                lastHeartbeatAt: time() - 120,
            )
        );

        $this->artisan('worker-watch:health')
            ->expectsOutput(
                'Worker Watch health check failed.'
            )
            ->assertExitCode(1);
    }

    public function test_health_command_fails_when_worker_is_degraded(): void
    {
        $this->store->put(
            new WorkerSnapshot(
                id: 'server:1:redis:default',
                hostname: 'server',
                processId: 1,
                connection: 'redis',
                queue: 'default',
                status: WorkerStatus::Healthy,
                lastHeartbeatAt: time(),
                currentJob: 'App\\Jobs\\LongRunningJob',
                jobStartedAt: time() - 400,
                jobsProcessed: 10,
                jobsFailed: 0,
            )
        );

        $this->artisan('worker-watch:health')
            ->expectsOutput(
                'Worker Watch health check failed.'
            )
            ->assertExitCode(1);
    }

    public function test_health_command_fails_when_no_workers_exist(): void
    {
        $this->artisan('worker-watch:health')
            ->expectsOutput(
                'Worker Watch health check failed.'
            )
            ->assertExitCode(1);
    }

    public function test_health_command_can_filter_by_queue(): void
    {
        $this->store->put(
            $this->worker(
                id: 'server:1:redis:default',
                queue: 'default',
                status: WorkerStatus::Critical,
                lastHeartbeatAt: time() - 500,
            )
        );

        $this->store->put(
            $this->worker(
                id: 'server:2:redis:emails',
                queue: 'emails',
                processId: 2,
                status: WorkerStatus::Healthy,
            )
        );

        $this->artisan(
            'worker-watch:health',
            [
                '--queue' => 'emails',
            ],
        )
            ->expectsOutput(
                'Worker Watch health check passed.'
            )
            ->assertExitCode(0);
    }

    public function test_health_command_can_filter_by_connection(): void
    {
        $this->store->put(
            $this->worker(
                id: 'server:1:database:default',
                connection: 'database',
                status: WorkerStatus::Critical,
                lastHeartbeatAt: time() - 500,
            )
        );

        $this->store->put(
            $this->worker(
                id: 'server:2:redis:default',
                connection: 'redis',
                processId: 2,
                status: WorkerStatus::Healthy,
            )
        );

        $this->artisan(
            'worker-watch:health',
            [
                '--connection' => 'redis',
            ],
        )
            ->expectsOutput(
                'Worker Watch health check passed.'
            )
            ->assertExitCode(0);
    }

    public function test_health_command_fails_when_filtered_queue_has_no_workers(): void
    {
        $this->store->put(
            $this->worker(
                queue: 'default',
            )
        );

        $this->artisan(
            'worker-watch:health',
            [
                '--queue' => 'emails',
            ],
        )
            ->expectsOutput(
                'Worker Watch health check failed.'
            )
            ->assertExitCode(1);
    }

    public function test_health_command_outputs_json(): void
    {
        $this->store->put(
            $this->worker()
        );

        $exitCode = Artisan::call(
            'worker-watch:health',
            [
                '--json' => true,
            ],
        );

        $output = trim(
            Artisan::output()
        );

        $payload = json_decode(
            $output,
            true,
        );

        $this->assertSame(
            0,
            $exitCode,
        );

        $this->assertIsArray(
            $payload,
        );

        $this->assertTrue(
            $payload['healthy'],
        );

        $this->assertSame(
            1,
            $payload['workers'],
        );

        $this->assertTrue(
            $payload['capacity_satisfied'],
        );
    }

    public function test_json_output_reports_unhealthy_worker(): void
    {
        $this->store->put(
            $this->worker(
                status: WorkerStatus::Critical,
                lastHeartbeatAt: time() - 500,
            )
        );

        $exitCode = Artisan::call(
            'worker-watch:health',
            [
                '--json' => true,
            ],
        );

        $output = trim(
            Artisan::output()
        );

        $payload = json_decode(
            $output,
            true,
        );

        $this->assertSame(
            1,
            $exitCode,
        );

        $this->assertIsArray(
            $payload,
        );

        $this->assertFalse(
            $payload['healthy'],
        );

        $this->assertSame(
            1,
            $payload['workers'],
        );

        $this->assertTrue(
            $payload['capacity_satisfied'],
        );
    }

    public function test_health_command_fails_when_expected_capacity_is_not_met(): void
    {
        config()->set(
            'worker-watch.capacity.enabled',
            true,
        );

        config()->set(
            'worker-watch.capacity.expected',
            [
                'redis:default' => 2,
            ],
        );

        $this->store->put(
            $this->worker(
                id: 'server:1:redis:default',
            )
        );

        $this->artisan('worker-watch:health')
            ->expectsOutput(
                'Worker Watch health check failed.'
            )
            ->assertExitCode(1);
    }

    public function test_health_command_passes_when_expected_capacity_is_met(): void
    {
        config()->set(
            'worker-watch.capacity.enabled',
            true,
        );

        config()->set(
            'worker-watch.capacity.expected',
            [
                'redis:default' => 2,
            ],
        );

        $this->store->put(
            $this->worker(
                id: 'server:1:redis:default',
                processId: 1,
            )
        );

        $this->store->put(
            $this->worker(
                id: 'server:2:redis:default',
                processId: 2,
            )
        );

        $this->artisan('worker-watch:health')
            ->expectsOutput(
                'Worker Watch health check passed.'
            )
            ->assertExitCode(0);
    }

    private function worker(
        string $id = 'server:1:redis:default',
        string $connection = 'redis',
        string $queue = 'default',
        int $processId = 1,
        WorkerStatus $status = WorkerStatus::Healthy,
        ?int $lastHeartbeatAt = null,
    ): WorkerSnapshot {
        return new WorkerSnapshot(
            id: $id,
            hostname: 'server',
            processId: $processId,
            connection: $connection,
            queue: $queue,
            status: $status,
            lastHeartbeatAt: $lastHeartbeatAt ?? time(),
            jobsProcessed: 10,
            jobsFailed: 0,
        );
    }
}
