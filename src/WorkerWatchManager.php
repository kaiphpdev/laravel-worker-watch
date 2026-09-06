<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch;

use Kaiphpdev\WorkerWatch\Contracts\WorkerStore;
use Kaiphpdev\WorkerWatch\Enums\WorkerStatus;
use Kaiphpdev\WorkerWatch\Support\WorkerIdentity;

final class WorkerWatchManager
{
    public function __construct(
        private readonly WorkerStore $store,
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
            status: WorkerStatus::Healthy,
            lastHeartbeatAt: time(),
        );

        $this->store->put($updated);

        return $updated;
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

        $status = $this->determineStatus(
            worker: $worker,
            now: $now,
        );

        if ($status === $worker->status) {
            return $worker;
        }

        $updated = $this->copy(
            worker: $worker,
            status: $status,
        );

        $this->store->put($updated);

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
}
