<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Tests\Unit;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Kaiphpdev\WorkerWatch\Enums\WorkerStatus;
use Kaiphpdev\WorkerWatch\Events\WorkerBecameUnhealthy;
use Kaiphpdev\WorkerWatch\Events\WorkerRecovered;
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
            events: $this->app->make(Dispatcher::class),
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

    public function test_it_dispatches_event_when_worker_becomes_unhealthy(): void
    {
        Event::fake([
            WorkerBecameUnhealthy::class,
        ]);

        $this->recreateManager();

        $worker = $this->snapshot(
            lastHeartbeatAt: 1000,
        );

        $this->store->put($worker);

        $this->manager->evaluateHealth(
            worker: $worker,
            now: 1060,
        );

        Event::assertDispatched(
            WorkerBecameUnhealthy::class,
            function (WorkerBecameUnhealthy $event): bool {
                return $event->worker->status === WorkerStatus::Stale;
            }
        );
    }

    public function test_it_does_not_repeat_unhealthy_event_for_unhealthy_transition(): void
    {
        Event::fake([
            WorkerBecameUnhealthy::class,
        ]);

        $this->recreateManager();

        $worker = $this->snapshot(
            lastHeartbeatAt: 1000,
        );

        $this->store->put($worker);

        $stale = $this->manager->evaluateHealth(
            worker: $worker,
            now: 1060,
        );

        $this->manager->evaluateHealth(
            worker: $stale,
            now: 1180,
        );

        Event::assertDispatchedTimes(
            WorkerBecameUnhealthy::class,
            1,
        );
    }

    public function test_it_dispatches_recovery_event_when_worker_becomes_healthy_again(): void
    {
        Event::fake([
            WorkerRecovered::class,
        ]);

        $this->recreateManager();

        $worker = new WorkerSnapshot(
            id: 'server:1:redis:default',
            hostname: 'server',
            processId: 1,
            connection: 'redis',
            queue: 'default',
            status: WorkerStatus::Stale,
            lastHeartbeatAt: 1190,
            jobsProcessed: 0,
            jobsFailed: 0,
        );

        $this->store->put($worker);

        $this->manager->evaluateHealth(
            worker: $worker,
            now: 1200,
        );

        Event::assertDispatched(
            WorkerRecovered::class,
            function (WorkerRecovered $event): bool {
                return $event->worker->status === WorkerStatus::Healthy;
            }
        );
    }

    private function recreateManager(): void
    {
        $this->manager = new WorkerWatchManager(
            store: $this->store,
            events: $this->app->make(Dispatcher::class),
        );
    }

    public function test_it_detects_missing_worker_capacity(): void
    {
        config()->set(
            'worker-watch.capacity.enabled',
            true,
        );

        config()->set(
            'worker-watch.capacity.expected',
            [
                'redis:default' => 3,
            ],
        );

        $this->store->put(
            $this->snapshot(
                lastHeartbeatAt: time(),
            )
        );

        $this->store->put(
            new WorkerSnapshot(
                id: 'server:2:redis:default',
                hostname: 'server',
                processId: 2,
                connection: 'redis',
                queue: 'default',
                status: WorkerStatus::Healthy,
                lastHeartbeatAt: time(),
                jobsProcessed: 0,
                jobsFailed: 0,
            )
        );

        $capacities = $this->manager->capacities();

        $this->assertCount(1, $capacities);

        $this->assertSame(
            3,
            $capacities[0]->expected,
        );

        $this->assertSame(
            2,
            $capacities[0]->actual,
        );

        $this->assertSame(
            1,
            $capacities[0]->missing(),
        );

        $this->assertFalse(
            $capacities[0]->isSatisfied()
        );

        $this->assertTrue(
            $this->manager->hasCapacityFailure()
        );
    }

    public function test_worker_capacity_passes_when_expected_count_is_met(): void
    {
        config()->set(
            'worker-watch.capacity.enabled',
            true,
        );

        config()->set(
            'worker-watch.capacity.expected',
            [
                'redis:default' => 1,
            ],
        );

        $this->store->put(
            $this->snapshot(
                lastHeartbeatAt: time(),
            )
        );

        $capacities = $this->manager->capacities();

        $this->assertCount(
            1,
            $capacities,
        );

        $capacity = $capacities[0];

        $this->assertTrue(
            $capacity->isSatisfied()
        );

        $this->assertSame(
            0,
            $capacity->missing(),
        );

        $this->assertFalse(
            $this->manager->hasCapacityFailure()
        );
    }

    public function test_critical_workers_do_not_satisfy_capacity(): void
    {
        config()->set(
            'worker-watch.capacity.enabled',
            true,
        );

        config()->set(
            'worker-watch.capacity.expected',
            [
                'redis:default' => 1,
            ],
        );

        $worker = new WorkerSnapshot(
            id: 'server:1:redis:default',
            hostname: 'server',
            processId: 1,
            connection: 'redis',
            queue: 'default',
            status: WorkerStatus::Critical,
            lastHeartbeatAt: time() - 500,
            jobsProcessed: 0,
            jobsFailed: 0,
        );

        $this->store->put($worker);

        $capacities = $this->manager->capacities();

        $this->assertCount(
            1,
            $capacities,
        );

        $capacity = $capacities[0];

        $this->assertSame(
            0,
            $capacity->actual,
        );

        $this->assertSame(
            1,
            $capacity->missing(),
        );

        $this->assertFalse(
            $capacity->isSatisfied()
        );

        $this->assertTrue(
            $this->manager->hasCapacityFailure()
        );
    }

    public function test_worker_is_degraded_when_failure_rate_is_high(): void
    {
        config()->set('worker-watch.failure_rate.enabled', true);

        config()->set('worker-watch.failure_rate.minimum_jobs', 20);

        config()->set('worker-watch.failure_rate.degraded_at', 10);

        config()->set('worker-watch.failure_rate.critical_at', 25);

        $worker = new WorkerSnapshot(
            id: 'server:1:redis:default',
            hostname: 'server',
            processId: 1,
            connection: 'redis',
            queue: 'default',
            status: WorkerStatus::Healthy,
            lastHeartbeatAt: 1990,
            jobsProcessed: 85,
            jobsFailed: 15,
        );

        $this->store->put($worker);

        $evaluated = $this->manager->evaluateHealth(
            worker: $worker,
            now: 2000,
        );

        $this->assertSame(
            WorkerStatus::Degraded,
            $evaluated->status,
        );
    }

    public function test_worker_is_critical_when_failure_rate_is_very_high(): void
    {
        $worker = new WorkerSnapshot(
            id: 'server:1:redis:default',
            hostname: 'server',
            processId: 1,
            connection: 'redis',
            queue: 'default',
            status: WorkerStatus::Healthy,
            lastHeartbeatAt: 1990,
            jobsProcessed: 70,
            jobsFailed: 30,
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

    public function test_failure_rate_is_ignored_until_minimum_sample_is_reached(): void
    {
        config()->set(
            'worker-watch.failure_rate.minimum_jobs',
            20,
        );

        $worker = new WorkerSnapshot(
            id: 'server:1:redis:default',
            hostname: 'server',
            processId: 1,
            connection: 'redis',
            queue: 'default',
            status: WorkerStatus::Healthy,
            lastHeartbeatAt: 1990,
            jobsProcessed: 1,
            jobsFailed: 9,
        );

        $this->store->put($worker);

        $evaluated = $this->manager->evaluateHealth(
            worker: $worker,
            now: 2000,
        );

        $this->assertSame(
            WorkerStatus::Healthy,
            $evaluated->status,
        );
    }

    public function test_failure_rate_degraded_threshold_is_inclusive(): void
    {
        $worker = new WorkerSnapshot(
            id: 'server:1:redis:default',
            hostname: 'server',
            processId: 1,
            connection: 'redis',
            queue: 'default',
            status: WorkerStatus::Healthy,
            lastHeartbeatAt: 1990,
            jobsProcessed: 90,
            jobsFailed: 10,
        );

        $this->store->put($worker);

        $evaluated = $this->manager->evaluateHealth(
            worker: $worker,
            now: 2000,
        );

        $this->assertSame(
            WorkerStatus::Degraded,
            $evaluated->status,
        );
    }

    public function test_failure_rate_critical_threshold_is_inclusive(): void
    {
        $worker = new WorkerSnapshot(
            id: 'server:1:redis:default',
            hostname: 'server',
            processId: 1,
            connection: 'redis',
            queue: 'default',
            status: WorkerStatus::Healthy,
            lastHeartbeatAt: 1990,
            jobsProcessed: 75,
            jobsFailed: 25,
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

    public function test_recent_failures_can_mark_worker_critical_even_when_lifetime_rate_is_low(): void
    {
        config()->set(
            'worker-watch.failure_rate.rolling_window.enabled',
            true,
        );

        config()->set(
            'worker-watch.failure_rate.rolling_window.minimum_jobs',
            10,
        );

        config()->set(
            'worker-watch.failure_rate.rolling_window.degraded_at',
            20,
        );

        config()->set(
            'worker-watch.failure_rate.rolling_window.critical_at',
            40,
        );

        $worker = new WorkerSnapshot(
            id: 'server:1:redis:default',
            hostname: 'server',
            processId: 1,
            connection: 'redis',
            queue: 'default',
            status: WorkerStatus::Healthy,
            lastHeartbeatAt: 1990,
            jobsProcessed: 980,
            jobsFailed: 20,
            recentResults: [
                true,
                false,
                true,
                false,
                true,
                false,
                true,
                false,
                true,
                false,
            ],
        );

        $this->store->put($worker);

        $evaluated = $this->manager->evaluateHealth(
            worker: $worker,
            now: 2000,
        );

        $this->assertSame(
            2.0,
            $worker->failureRate(),
        );

        $this->assertSame(
            50.0,
            $worker->recentFailureRate(),
        );

        $this->assertSame(
            WorkerStatus::Critical,
            $evaluated->status,
        );
    }

    public function test_recent_job_results_are_limited_to_configured_window(): void
    {
        config()->set(
            'worker-watch.failure_rate.rolling_window.size',
            3,
        );

        $worker = $this->startWorker();

        $this->manager->jobProcessed($worker->id);
        $this->manager->jobProcessed($worker->id);
        $this->manager->jobProcessed($worker->id);
        $this->manager->jobFailed($worker->id);

        $stored = $this->store->get($worker->id);

        $this->assertNotNull($stored);

        $this->assertSame(
            [
                true,
                true,
                false,
            ],
            $stored->recentResults,
        );

        $this->assertCount(
            3,
            $stored->recentResults,
        );
    }
}
