<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Events;

use Kaiphpdev\WorkerWatch\WorkerSnapshot;

final readonly class WorkerBecameUnhealthy
{
    public function __construct(
        public WorkerSnapshot $worker,
    ) {}
}
