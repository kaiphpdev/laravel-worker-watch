<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Events;

use Kaiphpdev\WorkerWatch\WorkerCapacity;

class WorkerCapacityRecovered
{
    public function __construct(
        public WorkerCapacity $capacity
    ) {}
}
