<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Tests\Feature;

use Kaiphpdev\WorkerWatch\Contracts\WorkerStore;
use Kaiphpdev\WorkerWatch\Enums\WorkerStatus;
use Kaiphpdev\WorkerWatch\Tests\Fakes\FakeWorkerStore;
use Kaiphpdev\WorkerWatch\Tests\TestCase;
use Kaiphpdev\WorkerWatch\WorkerSnapshot;

final class WorkerWatchStatusCommandTest extends TestCase
{
    private FakeWorkerStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new FakeWorkerStore;

        $this->app->instance(
            WorkerStore::class,
            $this->store,
        );
    }

    public function test_command_succeeds_when_all_workers_are_healthy(): void
    {
        $this->store->put(
            $this->worker(
                status: WorkerStatus::Healthy,
            )
        );

        $this->artisan('worker-watch:status')
            ->assertExitCode(0);
    }

    public function test_command_fails_when_worker_is_unhealthy(): void
    {
        $this->store->put(
            $this->worker(
                status: WorkerStatus::Critical,
                lastHeartbeatAt: time() - 200,
            )
        );

        $this->artisan('worker-watch:status')
            ->assertExitCode(1);
    }

    public function test_command_fails_when_no_workers_exist(): void
    {
        $this->artisan('worker-watch:status')
            ->expectsOutput('No worker records found.')
            ->assertExitCode(1);
    }

    public function test_it_can_filter_workers_by_queue(): void
    {
        $this->store->put(
            $this->worker(
                id: 'server:1:redis:default',
                queue: 'default',
            )
        );

        $this->store->put(
            $this->worker(
                id: 'server:2:redis:emails',
                queue: 'emails',
                processId: 2,
            )
        );

        $this->artisan(
            'worker-watch:status',
            [
                '--queue' => 'emails',
            ],
        )->assertExitCode(0);
    }

    public function test_json_output_is_available(): void
    {
        $this->store->put(
            $this->worker()
        );

        $this->artisan(
            'worker-watch:status',
            [
                '--json' => true,
            ],
        )->assertExitCode(0);
    }

    private function worker(
        string $id = 'server:1:redis:default',
        WorkerStatus $status = WorkerStatus::Healthy,
        string $queue = 'default',
        int $processId = 1,
        ?int $lastHeartbeatAt = null,
    ): WorkerSnapshot {
        return new WorkerSnapshot(
            id: $id,
            hostname: 'server',
            processId: $processId,
            connection: 'redis',
            queue: $queue,
            status: $status,
            lastHeartbeatAt: $lastHeartbeatAt ?? time(),
            jobsProcessed: 10,
            jobsFailed: 0,
        );
    }
}
