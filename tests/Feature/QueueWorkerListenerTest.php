<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Tests\Feature;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Kaiphpdev\WorkerWatch\Listeners\QueueWorkerListener;
use Kaiphpdev\WorkerWatch\Tests\Fakes\FakeWorkerStore;
use Kaiphpdev\WorkerWatch\Tests\TestCase;
use Kaiphpdev\WorkerWatch\WorkerWatchManager;
use Mockery;

final class QueueWorkerListenerTest extends TestCase
{
    private FakeWorkerStore $store;

    private WorkerWatchManager $manager;

    private QueueWorkerListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new FakeWorkerStore;

        $this->manager = new WorkerWatchManager(
            store: $this->store,
            events: $this->app->make(
                Dispatcher::class
            ),
        );

        $this->listener = new QueueWorkerListener(
            manager: $this->manager,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_job_processing_registers_worker_and_current_job(): void
    {
        $job = Mockery::mock();

        $job->shouldReceive('getQueue')
            ->andReturn('default');

        $job->shouldReceive('payload')
            ->andReturn([
                'displayName' => 'App\\Jobs\\SendEmail',
            ]);

        $job->shouldReceive('resolveName')
            ->andReturn('App\\Jobs\\SendEmail');

        $event = new JobProcessing(
            connectionName: 'redis',
            job: $job,
        );

        $this->listener->jobProcessing($event);

        $workers = $this->store->all();

        $this->assertCount(
            1,
            $workers,
        );

        $worker = $workers[0];

        $this->assertSame(
            'redis',
            $worker->connection,
        );

        $this->assertSame(
            'default',
            $worker->queue,
        );

        $this->assertSame(
            'App\\Jobs\\SendEmail',
            $worker->currentJob,
        );

        $this->assertNotNull(
            $worker->jobStartedAt,
        );
    }

    public function test_job_processed_clears_current_job_and_increments_processed_count(): void
    {
        $job = Mockery::mock();

        $job->shouldReceive('getQueue')
            ->andReturn('default');

        $job->shouldReceive('payload')
            ->andReturn([
                'displayName' => 'App\\Jobs\\SendEmail',
            ]);

        $job->shouldReceive('resolveName')
            ->andReturn('App\\Jobs\\SendEmail');

        $processing = new JobProcessing(
            connectionName: 'redis',
            job: $job,
        );

        $this->listener->jobProcessing($processing);

        $processed = new JobProcessed(
            connectionName: 'redis',
            job: $job,
        );

        $this->listener->jobProcessed($processed);

        $worker = $this->store->all()[0];

        $this->assertNull(
            $worker->currentJob,
        );

        $this->assertNull(
            $worker->jobStartedAt,
        );

        $this->assertSame(
            1,
            $worker->jobsProcessed,
        );

        $this->assertSame(
            [
                true,
            ],
            $worker->recentResults,
        );
    }

    public function test_job_exception_clears_job_and_increments_failure_count(): void
    {
        $job = Mockery::mock();

        $job->shouldReceive('getQueue')
            ->andReturn('default');

        $job->shouldReceive('payload')
            ->andReturn([
                'displayName' => 'App\\Jobs\\BrokenJob',
            ]);

        $job->shouldReceive('resolveName')
            ->andReturn('App\\Jobs\\BrokenJob');

        $processing = new JobProcessing(
            connectionName: 'redis',
            job: $job,
        );

        $this->listener->jobProcessing($processing);

        $exception = new \RuntimeException(
            'Job failed'
        );

        $failed = new JobExceptionOccurred(
            connectionName: 'redis',
            job: $job,
            exception: $exception,
        );

        $this->listener->jobExceptionOccurred(
            $failed
        );

        $worker = $this->store->all()[0];

        $this->assertNull(
            $worker->currentJob,
        );

        $this->assertSame(
            1,
            $worker->jobsFailed,
        );

        $this->assertSame(
            [
                false,
            ],
            $worker->recentResults,
        );
    }

    public function test_same_queue_reuses_same_worker_record(): void
    {
        $job = Mockery::mock();

        $job->shouldReceive('getQueue')
            ->andReturn('default');

        $job->shouldReceive('payload')
            ->andReturn([
                'displayName' => 'App\\Jobs\\One',
            ]);

        $job->shouldReceive('resolveName')
            ->andReturn('App\\Jobs\\One');

        $event = new JobProcessing(
            connectionName: 'redis',
            job: $job,
        );

        $this->listener->jobProcessing($event);
        $this->listener->jobProcessing($event);

        $this->assertCount(
            1,
            $this->store->all(),
        );
    }

    public function test_different_queues_create_distinct_worker_records(): void
    {
        $defaultJob = Mockery::mock();

        $defaultJob->shouldReceive('getQueue')
            ->andReturn('default');

        $defaultJob->shouldReceive('payload')
            ->andReturn([
                'displayName' => 'DefaultJob',
            ]);

        $defaultJob->shouldReceive('resolveName')
            ->andReturn('DefaultJob');

        $emailJob = Mockery::mock();

        $emailJob->shouldReceive('getQueue')
            ->andReturn('emails');

        $emailJob->shouldReceive('payload')
            ->andReturn([
                'displayName' => 'EmailJob',
            ]);

        $emailJob->shouldReceive('resolveName')
            ->andReturn('EmailJob');

        $this->listener->jobProcessing(
            new JobProcessing(
                connectionName: 'redis',
                job: $defaultJob,
            )
        );

        $this->listener->jobProcessing(
            new JobProcessing(
                connectionName: 'redis',
                job: $emailJob,
            )
        );

        $this->assertCount(
            2,
            $this->store->all(),
        );
    }
}
