<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Commands;

use Illuminate\Console\Command;
use Kaiphpdev\WorkerWatch\Enums\WorkerStatus;
use Kaiphpdev\WorkerWatch\WorkerCapacity;
use Kaiphpdev\WorkerWatch\WorkerSnapshot;
use Kaiphpdev\WorkerWatch\WorkerWatchManager;

final class WorkerWatchStatusCommand extends Command
{
    protected $signature = 'worker-watch:status
        {--queue= : Filter workers by queue}
        {--connection= : Filter workers by connection}
        {--status= : Filter workers by status}
        {--json : Output worker information as JSON}';

    protected $description = 'Display the health status of Laravel queue workers';

    public function __construct(
        private readonly WorkerWatchManager $manager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $workers = $this->filteredWorkers();

        if ($this->option('json')) {
            $this->outputJson($workers);

            return $this->exitCode($workers);
        }

        if ($workers === []) {
            $this->warn('No worker records found.');

            return self::FAILURE;
        }

        $this->table(
            [
                'Worker',
                'Connection',
                'Queue',
                'Status',
                'Heartbeat',
                'Current Job',
                'Job Runtime',
                'Processed',
                'Failed',
            ],
            array_map(
                fn (WorkerSnapshot $worker): array => [
                    $worker->id,
                    $worker->connection,
                    $worker->queue,
                    strtoupper($worker->status->value),
                    $this->formatDuration(
                        $worker->heartbeatAge()
                    ),
                    $worker->currentJob ?? '-',
                    $this->formatNullableDuration(
                        $worker->jobRuntime()
                    ),
                    $worker->jobsProcessed ?? 0,
                    $worker->jobsFailed ?? 0,
                ],
                $workers,
            ),
        );

        $this->displaySummary($workers);
        $this->displayCapacity();

        return $this->exitCode($workers);
    }

    private function displayCapacity(): void
    {
        $capacities = $this->manager->capacities();

        if ($capacities === []) {
            return;
        }

        $this->newLine();

        $this->line('Expected Worker Capacity');

        $this->table(
            [
                'Connection',
                'Queue',
                'Expected',
                'Actual',
                'Missing',
                'Status',
            ],
            array_map(
                static fn (WorkerCapacity $capacity): array => [
                    $capacity->connection,
                    $capacity->queue,
                    $capacity->expected,
                    $capacity->actual,
                    $capacity->missing(),
                    $capacity->isSatisfied()
                        ? 'OK'
                        : 'MISSING',
                ],
                $capacities,
            ),
        );
    }

    /**
     * @return array<int, WorkerSnapshot>
     */
    private function filteredWorkers(): array
    {
        $workers = $this->manager->workers();

        $queue = $this->stringOption('queue');
        $connection = $this->stringOption('connection');
        $status = $this->stringOption('status');

        return array_values(
            array_filter(
                $workers,
                static function (WorkerSnapshot $worker) use (
                    $queue,
                    $connection,
                    $status,
                ): bool {
                    if (
                        $queue !== null
                        && $worker->queue !== $queue
                    ) {
                        return false;
                    }

                    if (
                        $connection !== null
                        && $worker->connection !== $connection
                    ) {
                        return false;
                    }

                    if (
                        $status !== null
                        && $worker->status->value !== $status
                    ) {
                        return false;
                    }

                    return true;
                },
            )
        );
    }

    /**
     * @param  array<int, WorkerSnapshot>  $workers
     */
    private function outputJson(array $workers): void
    {
        $capacities = $this->manager->capacities();

        $payload = [
            'healthy' => $this->exitCode($workers) === self::SUCCESS,

            'summary' => $this->summary($workers),

            'capacity' => array_map(
                static fn (WorkerCapacity $capacity): array => $capacity->toArray(),
                $capacities,
            ),

            'workers' => array_map(
                fn (WorkerSnapshot $worker): array => [
                    ...$worker->toArray(),
                    'heartbeat_age' => $worker->heartbeatAge(),
                    'job_runtime' => $worker->jobRuntime(),
                ],
                $workers,
            ),
        ];

        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        $this->line(
            $json === false ? '{}' : $json
        );
    }

    /**
     * @param  array<int, WorkerSnapshot>  $workers
     */
    private function displaySummary(array $workers): void
    {
        $summary = $this->summary($workers);

        $this->newLine();

        $this->line(
            sprintf(
                'Workers: %d | Healthy: %d | Degraded: %d | Stale: %d | Critical: %d',
                $summary['total'],
                $summary['healthy'],
                $summary['degraded'],
                $summary['stale'],
                $summary['critical'],
            )
        );
    }

    /**
     * @param  array<int, WorkerSnapshot>  $workers
     * @return array{
     *     total: int,
     *     healthy: int,
     *     degraded: int,
     *     stale: int,
     *     critical: int,
     *     unknown: int
     * }
     */
    private function summary(array $workers): array
    {
        $summary = [
            'total' => count($workers),
            'healthy' => 0,
            'degraded' => 0,
            'stale' => 0,
            'critical' => 0,
            'unknown' => 0,
        ];

        foreach ($workers as $worker) {
            $summary[$worker->status->value]++;
        }

        return $summary;
    }

    /**
     * @param  array<int, WorkerSnapshot>  $workers
     */
    private function exitCode(array $workers): int
    {
        if ($workers === []) {
            return self::FAILURE;
        }

        foreach ($workers as $worker) {
            if ($worker->status !== WorkerStatus::Healthy) {
                return self::FAILURE;
            }
        }

        if ($this->manager->hasCapacityFailure()) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function formatNullableDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '-';
        }

        return $this->formatDuration($seconds);
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }

        if ($seconds < 3600) {
            return intdiv($seconds, 60).'m '.($seconds % 60).'s';
        }

        $hours = intdiv($seconds, 3600);
        $remaining = $seconds % 3600;

        return $hours.'h '.intdiv($remaining, 60).'m';
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : strtolower($value);
    }
}
