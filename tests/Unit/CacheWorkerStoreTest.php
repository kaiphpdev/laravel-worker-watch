<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Kaiphpdev\WorkerWatch\Enums\WorkerStatus;
use Kaiphpdev\WorkerWatch\Stores\CacheWorkerStore;
use Kaiphpdev\WorkerWatch\Tests\TestCase;
use Kaiphpdev\WorkerWatch\WorkerSnapshot;

final class CacheWorkerStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
    }

    public function test_it_stores_and_retrieves_a_worker(): void
    {
        $store = $this->store();

        $worker = $this->worker();

        $store->put($worker);

        $stored = $store->get($worker->id);

        $this->assertNotNull($stored);

        $this->assertSame(
            $worker->id,
            $stored->id,
        );

        $this->assertSame(
            $worker->queue,
            $stored->queue,
        );

        $this->assertSame(
            WorkerStatus::Healthy,
            $stored->status,
        );
    }

    public function test_it_returns_all_workers(): void
    {
        $store = $this->store();

        $store->put(
            $this->worker(
                id: 'server:1:redis:default',
                processId: 1,
            )
        );

        $store->put(
            $this->worker(
                id: 'server:2:redis:default',
                processId: 2,
            )
        );

        $this->assertCount(
            2,
            $store->all(),
        );
    }

    public function test_it_does_not_duplicate_worker_in_index(): void
    {
        $store = $this->store();

        $worker = $this->worker();

        $store->put($worker);
        $store->put($worker);
        $store->put($worker);

        $this->assertCount(
            1,
            $store->all(),
        );
    }

    public function test_it_forgets_a_worker(): void
    {
        $store = $this->store();

        $worker = $this->worker();

        $store->put($worker);

        $store->forget($worker->id);

        $this->assertNull(
            $store->get($worker->id)
        );

        $this->assertSame(
            [],
            $store->all(),
        );
    }

    public function test_it_clears_all_workers(): void
    {
        $store = $this->store();

        $store->put(
            $this->worker(
                id: 'worker-one',
                processId: 1,
            )
        );

        $store->put(
            $this->worker(
                id: 'worker-two',
                processId: 2,
            )
        );

        $store->clear();

        $this->assertSame(
            [],
            $store->all(),
        );
    }

    private function store(): CacheWorkerStore
    {
        return new CacheWorkerStore(
            cache: Cache::store(),
            retention: 3600,
        );
    }

    private function worker(
        string $id = 'server:1:redis:default',
        int $processId = 1,
    ): WorkerSnapshot {
        return new WorkerSnapshot(
            id: $id,
            hostname: 'server',
            processId: $processId,
            connection: 'redis',
            queue: 'default',
            status: WorkerStatus::Healthy,
            lastHeartbeatAt: time(),
            jobsProcessed: 0,
            jobsFailed: 0,
        );
    }
}
