<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Tests\Fakes;

use Kaiphpdev\WorkerWatch\Contracts\WorkerStore;
use Kaiphpdev\WorkerWatch\WorkerSnapshot;

final class FakeWorkerStore implements WorkerStore
{
    /**
     * @var array<string, WorkerSnapshot>
     */
    private array $workers = [];

    public function put(WorkerSnapshot $worker): void
    {
        $this->workers[$worker->id] = $worker;
    }

    public function get(string $workerId): ?WorkerSnapshot
    {
        return $this->workers[$workerId] ?? null;
    }

    public function all(): array
    {
        return array_values($this->workers);
    }

    public function forget(string $workerId): void
    {
        unset($this->workers[$workerId]);
    }

    public function clear(): void
    {
        $this->workers = [];
    }
}
