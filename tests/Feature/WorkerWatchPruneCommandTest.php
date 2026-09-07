<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Tests\Feature;

use Kaiphpdev\WorkerWatch\Contracts\WorkerStore;
use Kaiphpdev\WorkerWatch\Enums\WorkerStatus;
use Kaiphpdev\WorkerWatch\Tests\Fakes\FakeWorkerStore;
use Kaiphpdev\WorkerWatch\Tests\TestCase;
use Kaiphpdev\WorkerWatch\WorkerSnapshot;

final class WorkerWatchPruneCommandTest extends TestCase
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

    public function test_it_prunes_expired_worker_records(): void
    {
        config()->set(
            'worker-watch.retention',
            3600,
        );

        $expired = $this->worker(
            id: 'expired-worker',
            lastHeartbeatAt: time() - 4000,
        );

        $active = $this->worker(
            id: 'active-worker',
            lastHeartbeatAt: time() - 100,
        );

        $this->store->put($expired);
        $this->store->put($active);

        $this->artisan('worker-watch:prune')
            ->expectsOutput('Pruned 1 worker record.')
            ->assertExitCode(0);

        $this->assertNull(
            $this->store->get('expired-worker')
        );

        $this->assertNotNull(
            $this->store->get('active-worker')
        );
    }

    public function test_it_supports_custom_retention_threshold(): void
    {
        $worker = $this->worker(
            id: 'worker-one',
            lastHeartbeatAt: time() - 120,
        );

        $this->store->put($worker);

        $this->artisan(
            'worker-watch:prune',
            [
                '--older-than' => '60',
            ],
        )
            ->expectsOutput('Pruned 1 worker record.')
            ->assertExitCode(0);

        $this->assertNull(
            $this->store->get('worker-one')
        );
    }

    public function test_it_does_not_remove_recent_workers(): void
    {
        $worker = $this->worker(
            id: 'recent-worker',
            lastHeartbeatAt: time() - 30,
        );

        $this->store->put($worker);

        $this->artisan(
            'worker-watch:prune',
            [
                '--older-than' => '60',
            ],
        )
            ->expectsOutput('Pruned 0 worker records.')
            ->assertExitCode(0);

        $this->assertNotNull(
            $this->store->get('recent-worker')
        );
    }

    private function worker(
        string $id,
        int $lastHeartbeatAt,
    ): WorkerSnapshot {
        return new WorkerSnapshot(
            id: $id,
            hostname: 'server',
            processId: 1,
            connection: 'redis',
            queue: 'default',
            status: WorkerStatus::Healthy,
            lastHeartbeatAt: $lastHeartbeatAt,
            jobsProcessed: 0,
            jobsFailed: 0,
        );
    }
}
