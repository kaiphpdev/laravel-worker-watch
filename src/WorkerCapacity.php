<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch;

final readonly class WorkerCapacity
{
    public function __construct(
        public string $connection,
        public string $queue,
        public int $expected,
        public int $actual,
    ) {}

    public function missing(): int
    {
        return max(
            0,
            $this->expected - $this->actual,
        );
    }

    public function isSatisfied(): bool
    {
        return $this->actual >= $this->expected;
    }

    public function toArray(): array
    {
        return [
            'connection' => $this->connection,
            'queue' => $this->queue,
            'expected' => $this->expected,
            'actual' => $this->actual,
            'missing' => $this->missing(),
            'satisfied' => $this->isSatisfied(),
        ];
    }
}
