<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Tests\Unit;

use Kaiphpdev\WorkerWatch\Support\WorkerIdentity;
use PHPUnit\Framework\TestCase;

final class WorkerIdentityTest extends TestCase
{
    public function test_it_creates_a_worker_identity(): void
    {
        $id = WorkerIdentity::make(
            connection: 'redis',
            queue: 'default',
            hostname: 'server-01',
            processId: 1234,
        );

        $this->assertSame(
            'server-01:1234:redis:default',
            $id,
        );
    }

    public function test_it_normalizes_worker_identity_values(): void
    {
        $id = WorkerIdentity::make(
            connection: 'redis primary',
            queue: 'high:priority',
            hostname: 'app server',
            processId: 999,
        );

        $this->assertSame(
            'app-server:999:redis-primary:high-priority',
            $id,
        );
    }

    public function test_it_handles_empty_values(): void
    {
        $id = WorkerIdentity::make(
            connection: '',
            queue: '',
            hostname: '',
            processId: 100,
        );

        $this->assertSame(
            'unknown:100:unknown:unknown',
            $id,
        );
    }
}
