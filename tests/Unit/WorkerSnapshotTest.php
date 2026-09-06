<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Tests\Unit;

use Kaiphpdev\WorkerWatch\Enums\WorkerStatus;
use Kaiphpdev\WorkerWatch\WorkerSnapshot;
use PHPUnit\Framework\TestCase;

final class WorkerSnapshotTest extends TestCase
{
    public function test_it_detects_when_worker_is_running_a_job(): void
    {
        $worker = $this->worker(
            currentJob: 'App\\Jobs\\SendEmail',
            jobStartedAt: 1000,
        );

        $this->assertTrue(
            $worker->isRunningJob()
        );
    }

    public function test_it_returns_job_runtime(): void
    {
        $worker = $this->worker(
            currentJob: 'App\\Jobs\\SendEmail',
            jobStartedAt: 1000,
        );

        $this->assertSame(
            500,
            $worker->jobRuntime(1500),
        );
    }

    public function test_it_returns_null_runtime_when_no_job_is_running(): void
    {
        $worker = $this->worker();

        $this->assertNull(
            $worker->jobRuntime(1500)
        );
    }

    public function test_it_calculates_heartbeat_age(): void
    {
        $worker = $this->worker(
            lastHeartbeatAt: 1000,
        );

        $this->assertSame(
            60,
            $worker->heartbeatAge(1060),
        );
    }

    private function worker(
        int $lastHeartbeatAt = 1000,
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
