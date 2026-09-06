<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch;

use Kaiphpdev\WorkerWatch\Enums\WorkerStatus;

final readonly class WorkerSnapshot
{
    public function __construct(
        public string $id,
        public string $hostname,
        public int $processId,
        public string $connection,
        public string $queue,
        public WorkerStatus $status,
        public int $lastHeartbeatAt,
        public ?string $currentJob = null,
        public ?int $jobStartedAt = null,
        public ?int $jobsProcessed = null,
        public ?int $jobsFailed = null,
    ) {}

    public function isRunningJob(): bool
    {
        return $this->currentJob !== null
            && $this->jobStartedAt !== null;
    }

    public function jobRuntime(?int $now = null): ?int
    {
        if (! $this->isRunningJob()) {
            return null;
        }

        $now ??= time();

        return max(0, $now - $this->jobStartedAt);
    }

    public function heartbeatAge(?int $now = null): int
    {
        $now ??= time();

        return max(0, $now - $this->lastHeartbeatAt);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'hostname' => $this->hostname,
            'process_id' => $this->processId,
            'connection' => $this->connection,
            'queue' => $this->queue,
            'status' => $this->status->value,
            'last_heartbeat_at' => $this->lastHeartbeatAt,
            'current_job' => $this->currentJob,
            'job_started_at' => $this->jobStartedAt,
            'jobs_processed' => $this->jobsProcessed,
            'jobs_failed' => $this->jobsFailed,
            'jobs_total' => $this->totalCompletedJobs(),
            'failure_rate' => $this->failureRate(),
        ];
    }

    public function totalCompletedJobs(): int
    {
        return ($this->jobsProcessed ?? 0)
            + ($this->jobsFailed ?? 0);
    }

    public function failureRate(): float
    {
        $total = $this->totalCompletedJobs();

        if ($total === 0) {
            return 0.0;
        }

        return (($this->jobsFailed ?? 0) / $total) * 100;
    }
}
