<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Events;

use Kaiphpdev\WorkerWatch\WorkerCapacity;

class WorkerCapacityBecameInsufficient
{
    public function __construct(
        public WorkerCapacity $capacity,
    ) {}
}
