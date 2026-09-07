<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Tests\Unit;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Kaiphpdev\WorkerWatch\Enums\WorkerStatus;
use Kaiphpdev\WorkerWatch\Events\WorkerCapacityBecameInsufficient;
use Kaiphpdev\WorkerWatch\Events\WorkerCapacityRecovered;
use Kaiphpdev\WorkerWatch\Tests\Fakes\FakeWorkerStore;
use Kaiphpdev\WorkerWatch\Tests\TestCase;
use Kaiphpdev\WorkerWatch\WorkerCapacityMonitor;
use Kaiphpdev\WorkerWatch\WorkerSnapshot;
use Kaiphpdev\WorkerWatch\WorkerWatchManager;

final class WorkerCapacityMonitorTest extends TestCase
{
    private FakeWorkerStore $store;

    private WorkerWatchManager $manager;

    private Repository $cache;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'worker-watch.capacity.enabled',
            true,
        );

        config()->set(
            'worker-watch.capacity.expected',
            [
                'redis:default' => 2,
            ],
        );

        $this->store = new FakeWorkerStore;

        $this->cache = $this->app
            ->make('cache')
            ->store();

        $this->cache->clear();

        $this->manager = new WorkerWatchManager(
            store: $this->store,
            events: $this->app->make(
                Dispatcher::class
            ),
        );
    }

    public function test_first_capacity_check_establishes_baseline_without_event(): void
    {
        Event::fake([
            WorkerCapacityBecameInsufficient::class,
            WorkerCapacityRecovered::class,
        ]);

        $monitor = $this->monitor();

        $monitor->check();

        Event::assertNotDispatched(
            WorkerCapacityBecameInsufficient::class
        );

        Event::assertNotDispatched(
            WorkerCapacityRecovered::class
        );
    }

    public function test_it_dispatches_event_when_capacity_becomes_insufficient(): void
    {
        Event::fake([
            WorkerCapacityBecameInsufficient::class,
        ]);

        $monitor = $this->monitor();

        $this->addWorker(
            id: 'worker-one',
            processId: 1,
        );

        $this->addWorker(
            id: 'worker-two',
            processId: 2,
        );

        /*
         * Establish healthy baseline.
         */
        $monitor->check();

        $this->store->forget('worker-two');

        $monitor->check();

        Event::assertDispatched(
            WorkerCapacityBecameInsufficient::class,
            function (
                WorkerCapacityBecameInsufficient $event
            ): bool {
                return $event->capacity->expected === 2
                    && $event->capacity->actual === 1
                    && $event->capacity->missing() === 1;
            }
        );
    }

    public function test_it_does_not_repeat_capacity_failure_event(): void
    {
        Event::fake([
            WorkerCapacityBecameInsufficient::class,
        ]);

        $monitor = $this->monitor();

        $this->addWorker(
            id: 'worker-one',
            processId: 1,
        );

        $this->addWorker(
            id: 'worker-two',
            processId: 2,
        );

        $monitor->check();

        $this->store->forget('worker-two');

        $monitor->check();
        $monitor->check();
        $monitor->check();

        Event::assertDispatchedTimes(
            WorkerCapacityBecameInsufficient::class,
            1,
        );
    }

    public function test_it_dispatches_recovery_when_capacity_returns(): void
    {
        Event::fake([
            WorkerCapacityRecovered::class,
        ]);

        $monitor = $this->monitor();

        $this->addWorker(
            id: 'worker-one',
            processId: 1,
        );

        /*
         * First check establishes insufficient baseline.
         */
        $monitor->check();

        $this->addWorker(
            id: 'worker-two',
            processId: 2,
        );

        $monitor->check();

        Event::assertDispatched(
            WorkerCapacityRecovered::class,
            function (
                WorkerCapacityRecovered $event
            ): bool {
                return $event->capacity->isSatisfied();
            }
        );
    }

    private function monitor(): WorkerCapacityMonitor
    {
        return new WorkerCapacityMonitor(
            manager: $this->manager,
            cache: $this->cache,
            events: $this->app->make(
                Dispatcher::class
            ),
        );
    }

    private function addWorker(
        string $id,
        int $processId,
    ): void {
        $this->store->put(
            new WorkerSnapshot(
                id: $id,
                hostname: 'server',
                processId: $processId,
                connection: 'redis',
                queue: 'default',
                status: WorkerStatus::Healthy,
                lastHeartbeatAt: time(),
                jobsProcessed: 0,
                jobsFailed: 0,
            )
        );
    }
}
