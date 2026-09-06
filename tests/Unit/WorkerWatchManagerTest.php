<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Tests\Unit;

use Kaiphpdev\WorkerWatch\Enums\WorkerStatus;
use Kaiphpdev\WorkerWatch\Tests\Fakes\FakeWorkerStore;
use Kaiphpdev\WorkerWatch\Tests\TestCase;
use Kaiphpdev\WorkerWatch\WorkerSnapshot;
use Kaiphpdev\WorkerWatch\WorkerWatchManager;

final class WorkerWatchManagerTest extends TestCase
{
    private FakeWorkerStore $store;

    private WorkerWatchManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new FakeWorkerStore;

        $this->manager = new WorkerWatchManager(
            store: $this->store,
        );
    }

    public function test_it_registers_a_worker(): void
    {
        $worker = $this->manager->workerStarted(
            connection: 'redis',
            queue: 'default',
            hostname: 'server-01',
            processId: 1234,
        );

        $this->assertSame(
            'server-01:1234:redis:default',
            $worker->id,
        );

        $this->assertSame(
            WorkerStatus::Healthy,
            $worker->status,
        );

        $this->assertSame(
            0,
            $worker->jobsProcessed,
        );

        $this->assertSame(
            0,
            $worker->jobsFailed,
        );

        $this->assertNotNull(
            $this->store->get($worker->id)
        );
    }

    public function test_it_updates_worker_heartbeat(): void
    {
        $worker = $this->manager->workerStarted(
            connection: 'redis',
            queue: 'default',
            hostname: 'server',
            processId: 1,
        );

        $oldHeartbeat = $worker->lastHeartbeatAt;

        $updated = $this->manager->heartbeat(
            $worker->id
        );

        $this->assertNotNull($updated);

        $this->assertGreaterThanOrEqual(
            $oldHeartbeat,
            $updated->lastHeartbeatAt,
        );

        $this->assertSame(
            WorkerStatus::Healthy,
            $updated->status,
        );
    }

    public function test_it_records_a_started_job(): void
    {
        $worker = $this->startWorker();

        $updated = $this->manager->jobStarted(
            workerId: $worker->id,
            jobName: 'App\\Jobs\\SendInvoice',
        );

        $this->assertNotNull($updated);

        $this->assertSame(
            'App\\Jobs\\SendInvoice',
            $updated->currentJob,
        );

        $this->assertNotNull(
            $updated->jobStartedAt
        );

        $this->assertTrue(
            $updated->isRunningJob()
        );
    }

    public function test_it_records_a_successfully_processed_job(): void
    {
        $worker = $this->startWorker();

        $this->manager->jobStarted(
            $worker->id,
            'App\\Jobs\\SendInvoice',
        );

        $updated = $this->manager->jobProcessed(
            $worker->id
        );

        $this->assertNotNull($updated);

        $this->assertNull(
            $updated->currentJob
        );

        $this->assertNull(
            $updated->jobStartedAt
        );

        $this->assertSame(
            1,
            $updated->jobsProcessed,
        );

        $this->assertSame(
            0,
            $updated->jobsFailed,
        );
    }

    public function test_it_records_a_failed_job(): void
    {
        $worker = $this->startWorker();

        $this->manager->jobStarted(
            $worker->id,
            'App\\Jobs\\BrokenJob',
        );

        $updated = $this->manager->jobFailed(
            $worker->id
        );

        $this->assertNotNull($updated);

        $this->assertNull(
            $updated->currentJob
        );

        $this->assertNull(
            $updated->jobStartedAt
        );

        $this->assertSame(
            1,
            $updated->jobsFailed,
        );

        $this->assertSame(
            0,
            $updated->jobsProcessed,
        );
    }

    public function test_it_marks_worker_stale_when_heartbeat_is_old(): void
    {
        $worker = $this->snapshot(
            lastHeartbeatAt: 1000,
        );

        $this->store->put($worker);

        $evaluated = $this->manager->evaluateHealth(
            worker: $worker,
            now: 1060,
        );

        $this->assertSame(
            WorkerStatus::Stale,
            $evaluated->status,
        );
    }

    public function test_it_marks_worker_critical_when_heartbeat_is_too_old(): void
    {
        $worker = $this->snapshot(
            lastHeartbeatAt: 1000,
        );

        $this->store->put($worker);

        $evaluated = $this->manager->evaluateHealth(
            worker: $worker,
            now: 1180,
        );

        $this->assertSame(
            WorkerStatus::Critical,
            $evaluated->status,
        );
    }

    public function test_it_marks_long_running_job_as_degraded(): void
    {
        $worker = $this->snapshot(
            lastHeartbeatAt: 1390,
            currentJob: 'App\\Jobs\\ImportProducts',
            jobStartedAt: 1000,
        );

        $this->store->put($worker);

        $evaluated = $this->manager->evaluateHealth(
            worker: $worker,
            now: 1400,
        );

        $this->assertSame(
            WorkerStatus::Degraded,
            $evaluated->status,
        );
    }

    public function test_it_marks_extremely_long_running_job_as_critical(): void
    {
        $worker = $this->snapshot(
            lastHeartbeatAt: 1990,
            currentJob: 'App\\Jobs\\ImportProducts',
            jobStartedAt: 1000,
        );

        $this->store->put($worker);

        $evaluated = $this->manager->evaluateHealth(
            worker: $worker,
            now: 2000,
        );

        $this->assertSame(
            WorkerStatus::Critical,
            $evaluated->status,
        );
    }

    public function test_heartbeat_failure_has_priority_over_job_runtime(): void
    {
        $worker = $this->snapshot(
            lastHeartbeatAt: 1000,
            currentJob: 'App\\Jobs\\ImportProducts',
            jobStartedAt: 900,
        );

        $this->store->put($worker);

        $evaluated = $this->manager->evaluateHealth(
            worker: $worker,
            now: 1200,
        );

        $this->assertSame(
            WorkerStatus::Critical,
            $evaluated->status,
        );
    }

    public function test_worker_remains_healthy_for_normal_job_runtime(): void
    {
        $worker = $this->snapshot(
            lastHeartbeatAt: 1190,
            currentJob: 'App\\Jobs\\SendEmail',
            jobStartedAt: 1000,
        );

        $this->store->put($worker);

        $evaluated = $this->manager->evaluateHealth(
            worker: $worker,
            now: 1200,
        );

        $this->assertSame(
            WorkerStatus::Healthy,
            $evaluated->status,
        );
    }

    public function test_it_removes_stopped_worker(): void
    {
        $worker = $this->startWorker();

        $this->manager->workerStopped(
            $worker->id
        );

        $this->assertNull(
            $this->store->get($worker->id)
        );
    }

    public function test_unknown_worker_operations_return_null(): void
    {
        $this->assertNull(
            $this->manager->heartbeat('missing-worker')
        );

        $this->assertNull(
            $this->manager->jobStarted(
                'missing-worker',
                'App\\Jobs\\TestJob',
            )
        );

        $this->assertNull(
            $this->manager->jobProcessed(
                'missing-worker'
            )
        );

        $this->assertNull(
            $this->manager->jobFailed(
                'missing-worker'
            )
        );
    }

    private function startWorker(): WorkerSnapshot
    {
        return $this->manager->workerStarted(
            connection: 'redis',
            queue: 'default',
            hostname: 'server',
            processId: 1,
        );
    }

    private function snapshot(
        int $lastHeartbeatAt,
        ?string $currentJob = null,
        ?int $jobStartedAt = null,
    ): WorkerSnapshot {
        return new WorkerSnapshot(
            id: 'server:1:redis:default',
            hostname: 'server',
            processId: 1,
            connection: 'redis',
            queue: 'default',
            status: WorkerStatus::Healthy,
            lastHeartbeatAt: $lastHeartbeatAt,
            currentJob: $currentJob,
            jobStartedAt: $jobStartedAt,
            jobsProcessed: 0,
            jobsFailed: 0,
        );
    }
}
