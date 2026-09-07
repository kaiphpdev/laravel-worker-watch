<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Kaiphpdev\WorkerWatch\Events\WorkerCapacityBecameInsufficient;
use Kaiphpdev\WorkerWatch\Events\WorkerCapacityRecovered;

final class WorkerCapacityMonitor
{
    public function __construct(
        private readonly WorkerWatchManager $manager,
        private readonly Repository $cache,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @return array<int, WorkerCapacity>
     */
    public function check(): array
    {
        $capacities = $this->manager->capacities();

        foreach ($capacities as $capacity) {
            $this->checkCapacity($capacity);
        }

        return $capacities;
    }

    private function checkCapacity(
        WorkerCapacity $capacity,
    ): void {
        $key = $this->stateKey($capacity);

        $currentSatisfied = $capacity->isSatisfied();

        $previousSatisfied = $this->cache->get($key);

        /*
         * First observation establishes the baseline.
         *
         * We deliberately do not dispatch an event here because
         * there is no previous state to transition from.
         */
        if (! is_bool($previousSatisfied)) {
            $this->cache->put(
                $key,
                $currentSatisfied,
                $this->stateRetention(),
            );

            return;
        }

        if ($previousSatisfied === $currentSatisfied) {
            return;
        }

        $this->cache->put(
            $key,
            $currentSatisfied,
            $this->stateRetention(),
        );

        if (! $currentSatisfied) {
            $this->events->dispatch(
                new WorkerCapacityBecameInsufficient(
                    $capacity
                )
            );

            return;
        }

        $this->events->dispatch(
            new WorkerCapacityRecovered(
                $capacity
            )
        );
    }

    private function stateKey(
        WorkerCapacity $capacity,
    ): string {
        return sprintf(
            'worker-watch:capacity-state:%s:%s',
            $capacity->connection,
            $capacity->queue,
        );
    }

    private function stateRetention(): int
    {
        return max(
            60,
            (int) config(
                'worker-watch.capacity.state_retention',
                86400,
            ),
        );
    }
}
