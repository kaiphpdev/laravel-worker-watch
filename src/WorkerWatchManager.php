<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch;

use Illuminate\Contracts\Events\Dispatcher;
use Kaiphpdev\WorkerWatch\Contracts\WorkerStore;
use Kaiphpdev\WorkerWatch\Enums\WorkerStatus;
use Kaiphpdev\WorkerWatch\Events\WorkerBecameUnhealthy;
use Kaiphpdev\WorkerWatch\Events\WorkerRecovered;
use Kaiphpdev\WorkerWatch\Support\WorkerIdentity;

final class WorkerWatchManager
{
    public function __construct(
        private readonly WorkerStore $store,
        private readonly Dispatcher $events,
    ) {}

    public function workerStarted(
        string $connection,
        string $queue,
        ?string $hostname = null,
        ?int $processId = null,
    ): WorkerSnapshot {
        $hostname ??= gethostname() ?: 'unknown-host';
        $processId ??= getmypid() ?: 0;

        $worker = new WorkerSnapshot(
            id: WorkerIdentity::make(
                connection: $connection,
                queue: $queue,
                hostname: $hostname,
                processId: $processId,
            ),
            hostname: $hostname,
            processId: $processId,
            connection: $connection,
            queue: $queue,
            status: WorkerStatus::Healthy,
            lastHeartbeatAt: time(),
            jobsProcessed: 0,
            jobsFailed: 0,
        );

        $this->store->put($worker);

        return $worker;
    }

    public function heartbeat(string $workerId): ?WorkerSnapshot
    {
        $worker = $this->store->get($workerId);

        if ($worker === null) {
            return null;
        }

        $updated = $this->copy(
            worker: $worker,
            lastHeartbeatAt: time(),
        );

        $this->store->put($updated);

        return $this->evaluateHealth($updated);
    }

    public function jobStarted(
        string $workerId,
        string $jobName,
    ): ?WorkerSnapshot {
        $worker = $this->store->get($workerId);

        if ($worker === null) {
            return null;
        }

        $updated = $this->copy(
            worker: $worker,
            status: WorkerStatus::Healthy,
            lastHeartbeatAt: time(),
            currentJob: $jobName,
            jobStartedAt: time(),
        );

        $this->store->put($updated);

        return $updated;
    }

    public function jobProcessed(string $workerId): ?WorkerSnapshot
    {
        $worker = $this->store->get($workerId);

        if ($worker === null) {
            return null;
        }

        $updated = $this->copy(
            worker: $worker,
            status: WorkerStatus::Healthy,
            lastHeartbeatAt: time(),
            currentJob: null,
            jobStartedAt: null,
            jobsProcessed: ($worker->jobsProcessed ?? 0) + 1,
        );

        $this->store->put($updated);

        return $updated;
    }

    public function jobFailed(string $workerId): ?WorkerSnapshot
    {
        $worker = $this->store->get($workerId);

        if ($worker === null) {
            return null;
        }

        $updated = $this->copy(
            worker: $worker,
            status: WorkerStatus::Healthy,
            lastHeartbeatAt: time(),
            currentJob: null,
            jobStartedAt: null,
            jobsFailed: ($worker->jobsFailed ?? 0) + 1,
        );

        $this->store->put($updated);

        return $updated;
    }

    public function workerStopped(string $workerId): void
    {
        $this->store->forget($workerId);
    }

    /**
     * @return array<int, WorkerSnapshot>
     */
    public function workers(): array
    {
        return array_map(
            fn (WorkerSnapshot $worker): WorkerSnapshot => $this->evaluateHealth($worker),
            $this->store->all(),
        );
    }

    public function worker(string $workerId): ?WorkerSnapshot
    {
        $worker = $this->store->get($workerId);

        if ($worker === null) {
            return null;
        }

        return $this->evaluateHealth($worker);
    }

    public function evaluateHealth(
        WorkerSnapshot $worker,
        ?int $now = null,
    ): WorkerSnapshot {
        $now ??= time();

        $previousStatus = $worker->status;

        $newStatus = $this->determineStatus(
            worker: $worker,
            now: $now,
        );

        if ($newStatus === $previousStatus) {
            return $worker;
        }

        $updated = $this->copy(
            worker: $worker,
            status: $newStatus,
        );

        $this->store->put($updated);

        $this->dispatchStatusTransition(
            previousStatus: $previousStatus,
            worker: $updated,
        );

        return $updated;
    }

    private function determineStatus(
        WorkerSnapshot $worker,
        int $now,
    ): WorkerStatus {
        $heartbeatAge = $worker->heartbeatAge($now);

        $staleAfter = (int) config(
            'worker-watch.heartbeat.stale_after',
            60,
        );

        $criticalHeartbeatAfter = (int) config(
            'worker-watch.heartbeat.critical_after',
            180,
        );

        if ($heartbeatAge >= $criticalHeartbeatAfter) {
            return WorkerStatus::Critical;
        }

        if ($heartbeatAge >= $staleAfter) {
            return WorkerStatus::Stale;
        }

        $runtime = $worker->jobRuntime($now);

        if ($runtime === null) {

            $failureStatus = $this->failureRateStatus($worker);

            if ($failureStatus !== null) {
                return $failureStatus;
            }

            return WorkerStatus::Healthy;
        }

        $longRunningAfter = (int) config(
            'worker-watch.jobs.long_running_after',
            300,
        );

        $criticalJobAfter = (int) config(
            'worker-watch.jobs.critical_after',
            900,
        );

        if ($runtime >= $criticalJobAfter) {
            return WorkerStatus::Critical;
        }

        if ($runtime >= $longRunningAfter) {
            return WorkerStatus::Degraded;
        }

        $failureStatus = $this->failureRateStatus($worker);

        if ($failureStatus !== null) {
            return $failureStatus;
        }

        return WorkerStatus::Healthy;
    }

    private function copy(
        WorkerSnapshot $worker,
        ?WorkerStatus $status = null,
        ?int $lastHeartbeatAt = null,
        string|false|null $currentJob = false,
        int|false|null $jobStartedAt = false,
        ?int $jobsProcessed = null,
        ?int $jobsFailed = null,
    ): WorkerSnapshot {
        return new WorkerSnapshot(
            id: $worker->id,
            hostname: $worker->hostname,
            processId: $worker->processId,
            connection: $worker->connection,
            queue: $worker->queue,
            status: $status ?? $worker->status,
            lastHeartbeatAt: $lastHeartbeatAt ?? $worker->lastHeartbeatAt,
            currentJob: $currentJob === false
                ? $worker->currentJob
                : $currentJob,
            jobStartedAt: $jobStartedAt === false
                ? $worker->jobStartedAt
                : $jobStartedAt,
            jobsProcessed: $jobsProcessed ?? $worker->jobsProcessed,
            jobsFailed: $jobsFailed ?? $worker->jobsFailed,
        );
    }

    private function dispatchStatusTransition(
        WorkerStatus $previousStatus,
        WorkerSnapshot $worker,
    ): void {
        $wasHealthy = $previousStatus === WorkerStatus::Healthy;

        $isHealthy = $worker->status === WorkerStatus::Healthy;

        if ($wasHealthy && ! $isHealthy) {
            $this->events->dispatch(
                new WorkerBecameUnhealthy($worker)
            );

            return;
        }

        if (! $wasHealthy && $isHealthy) {
            $this->events->dispatch(
                new WorkerRecovered($worker)
            );
        }
    }

    /**
     * @return array<int, WorkerCapacity>
     */
    public function capacities(): array
    {
        if (! (bool) config(
            'worker-watch.capacity.enabled',
            true,
        )) {
            return [];
        }

        $expected = config(
            'worker-watch.capacity.expected',
            [],
        );

        if (! is_array($expected)) {
            return [];
        }

        $workers = $this->workers();

        $capacities = [];

        foreach ($expected as $key => $required) {
            if (! is_string($key)) {
                continue;
            }

            [$connection, $queue] = $this->parseCapacityKey(
                $key
            );

            $actual = count(
                array_filter(
                    $workers,
                    static fn (WorkerSnapshot $worker): bool => $worker->connection === $connection
                        && $worker->queue === $queue
                        && $worker->status !== WorkerStatus::Critical,
                )
            );

            $capacities[] = new WorkerCapacity(
                connection: $connection,
                queue: $queue,
                expected: max(0, (int) $required),
                actual: $actual,
            );
        }

        return $capacities;
    }

    public function hasCapacityFailure(): bool
    {
        foreach ($this->capacities() as $capacity) {
            if (! $capacity->isSatisfied()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseCapacityKey(string $key): array
    {
        $parts = explode(':', $key, 2);

        $connection = trim(
            $parts[0] ?? ''
        );

        $queue = trim(
            $parts[1] ?? ''
        );

        return [
            $connection !== ''
                ? $connection
                : 'unknown',

            $queue !== ''
                ? $queue
                : 'default',
        ];
    }

    private function failureRateStatus(
        WorkerSnapshot $worker,
    ): ?WorkerStatus {
        if (! (bool) config(
            'worker-watch.failure_rate.enabled',
            true,
        )) {
            return null;
        }

        $minimumJobs = max(
            1,
            (int) config(
                'worker-watch.failure_rate.minimum_jobs',
                20,
            ),
        );

        if ($worker->totalCompletedJobs() < $minimumJobs) {
            return null;
        }

        $failureRate = $worker->failureRate();

        $criticalAt = (float) config(
            'worker-watch.failure_rate.critical_at',
            25,
        );

        $degradedAt = (float) config(
            'worker-watch.failure_rate.degraded_at',
            10,
        );

        if ($failureRate >= $criticalAt) {
            return WorkerStatus::Critical;
        }

        if ($failureRate >= $degradedAt) {
            return WorkerStatus::Degraded;
        }

        return null;
    }
}
