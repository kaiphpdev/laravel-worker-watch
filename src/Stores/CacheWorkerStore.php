<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Stores;

use Illuminate\Contracts\Cache\Repository;
use Kaiphpdev\WorkerWatch\Contracts\WorkerStore;
use Kaiphpdev\WorkerWatch\Enums\WorkerStatus;
use Kaiphpdev\WorkerWatch\WorkerSnapshot;

final class CacheWorkerStore implements WorkerStore
{
    private const INDEX_KEY = 'worker-watch:workers';

    public function __construct(
        private readonly Repository $cache,
        private readonly int $retention = 3600,
    ) {}

    public function put(WorkerSnapshot $worker): void
    {
        $this->cache->put(
            $this->workerKey($worker->id),
            $worker->toArray(),
            $this->retention,
        );

        $workers = $this->workerIds();

        if (! in_array($worker->id, $workers, true)) {
            $workers[] = $worker->id;

            $this->cache->put(
                self::INDEX_KEY,
                $workers,
                $this->retention,
            );
        }
    }

    public function get(string $workerId): ?WorkerSnapshot
    {
        $data = $this->cache->get(
            $this->workerKey($workerId)
        );

        if (! is_array($data)) {
            return null;
        }

        return $this->hydrate($data);
    }

    public function all(): array
    {
        $workers = [];

        foreach ($this->workerIds() as $workerId) {
            $worker = $this->get($workerId);

            if ($worker === null) {
                continue;
            }

            $workers[] = $worker;
        }

        return $workers;
    }

    public function forget(string $workerId): void
    {
        $this->cache->forget(
            $this->workerKey($workerId)
        );

        $workers = array_values(
            array_filter(
                $this->workerIds(),
                static fn (string $id): bool => $id !== $workerId,
            )
        );

        $this->cache->put(
            self::INDEX_KEY,
            $workers,
            $this->retention,
        );
    }

    public function clear(): void
    {
        foreach ($this->workerIds() as $workerId) {
            $this->cache->forget(
                $this->workerKey($workerId)
            );
        }

        $this->cache->forget(self::INDEX_KEY);
    }

    /**
     * @return array<int, string>
     */
    private function workerIds(): array
    {
        $workers = $this->cache->get(
            self::INDEX_KEY,
            []
        );

        if (! is_array($workers)) {
            return [];
        }

        return array_values(
            array_filter(
                $workers,
                'is_string'
            )
        );
    }

    private function workerKey(string $workerId): string
    {
        return 'worker-watch:worker:'.$workerId;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hydrate(array $data): WorkerSnapshot
    {
        return new WorkerSnapshot(
            id: (string) $data['id'],
            hostname: (string) $data['hostname'],
            processId: (int) $data['process_id'],
            connection: (string) $data['connection'],
            queue: (string) $data['queue'],
            status: WorkerStatus::from(
                (string) $data['status']
            ),
            lastHeartbeatAt: (int) $data['last_heartbeat_at'],
            currentJob: isset($data['current_job'])
                ? (string) $data['current_job']
                : null,
            jobStartedAt: isset($data['job_started_at'])
                ? (int) $data['job_started_at']
                : null,
            jobsProcessed: isset($data['jobs_processed'])
                ? (int) $data['jobs_processed']
                : null,
            jobsFailed: isset($data['jobs_failed'])
                ? (int) $data['jobs_failed']
                : null,
            recentResults: isset($data['recent_results'])
                && is_array($data['recent_results'])
                    ? array_values(
                        array_map(
                            static fn (mixed $result): bool => (bool) $result,
                            $data['recent_results'],
                        )
                    )
                    : [],
        );
    }
}
