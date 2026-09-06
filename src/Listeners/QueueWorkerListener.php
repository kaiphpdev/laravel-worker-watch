<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Listeners;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStarting;
use Illuminate\Queue\Events\WorkerStopping;
use Kaiphpdev\WorkerWatch\WorkerWatchManager;

final class QueueWorkerListener
{
    /**
     * @var array<string, string>
     */
    private array $workerIds = [];

    /**
     * @var array<string, int>
     */
    private array $lastHeartbeats = [];

    public function __construct(
        private readonly WorkerWatchManager $manager,
    ) {}

    public function workerStarting(WorkerStarting $event): void {}

    public function looping(Looping $event): void
    {
        foreach ($this->workerIds as $workerId) {
            $this->heartbeatIfDue($workerId);
        }
    }

    public function jobProcessing(JobProcessing $event): void
    {
        $workerId = $this->workerId(
            connection: $event->connectionName,
            queue: $event->job->getQueue() ?: 'default',
        );

        $this->manager->jobStarted(
            workerId: $workerId,
            jobName: $this->jobName($event),
        );
    }

    public function jobProcessed(JobProcessed $event): void
    {
        $workerId = $this->workerId(
            connection: $event->connectionName,
            queue: $event->job->getQueue() ?: 'default',
        );

        $this->manager->jobProcessed($workerId);
    }

    public function jobExceptionOccurred(
        JobExceptionOccurred $event,
    ): void {
        $workerId = $this->workerId(
            connection: $event->connectionName,
            queue: $event->job->getQueue() ?: 'default',
        );

        $this->manager->jobFailed($workerId);
    }

    public function workerStopping(WorkerStopping $event): void
    {
        foreach ($this->workerIds as $workerId) {
            $this->manager->workerStopped($workerId);
        }

        $this->workerIds = [];
        $this->lastHeartbeats = [];
    }

    private function workerId(
        string $connection,
        string $queue,
    ): string {
        $key = $connection.'|'.$queue;

        if (isset($this->workerIds[$key])) {
            return $this->workerIds[$key];
        }

        $worker = $this->manager->workerStarted(
            connection: $connection,
            queue: $queue,
        );

        $this->workerIds[$key] = $worker->id;
        $this->lastHeartbeats[$worker->id] = time();

        return $worker->id;
    }

    private function heartbeatIfDue(string $workerId): void
    {
        $interval = max(
            1,
            (int) config(
                'worker-watch.heartbeat.interval',
                15,
            ),
        );

        $lastHeartbeat = $this->lastHeartbeats[$workerId] ?? 0;

        if ((time() - $lastHeartbeat) < $interval) {
            return;
        }

        $this->manager->heartbeat($workerId);

        $this->lastHeartbeats[$workerId] = time();
    }

    private function jobName(JobProcessing $event): string
    {
        $payload = $event->job->payload();

        $displayName = $payload['displayName'] ?? null;

        if (is_string($displayName) && $displayName !== '') {
            return $displayName;
        }

        return $event->job->resolveName();
    }
}
