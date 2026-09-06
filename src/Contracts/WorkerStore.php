<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Contracts;

use Kaiphpdev\WarkerWatch\WorkerSnapshot;

interface WorkerStore
{
    public function put(WorkerSnapshot $worker): void;

    public function get(string $workerId): ?WorkerSnapshot;

    /**
     * @return array<int, WorkerSnapshot>
     */
    public function all(): array;

    public function forget(string $workerId): void;

    public function clear(): void;
}
