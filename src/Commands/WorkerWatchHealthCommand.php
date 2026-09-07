<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Commands;

use Illuminate\Console\Command;
use Kaiphpdev\WorkerWatch\Enums\WorkerStatus;
use Kaiphpdev\WorkerWatch\WorkerWatchManager;

final class WorkerWatchHealthCommand extends Command
{
    protected $signature = 'worker-watch:health
        {--queue= : Check only a specific queue}
        {--connection= : Check only a specific connection}
        {--json : Output JSON}';

    protected $description = 'Perform a lightweight Laravel queue worker health check';

    public function __construct(
        private readonly WorkerWatchManager $manager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $workers = $this->filteredWorkers();

        $healthy = $this->isHealthy($workers);

        if ((bool) $this->option('json')) {
            $this->line(
                json_encode(
                    [
                        'healthy' => $healthy,
                        'workers' => count($workers),
                        'capacity_satisfied' => ! $this->manager->hasCapacityFailure(),
                    ],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
                ) ?: '{}'
            );

            return $healthy
                ? self::SUCCESS
                : self::FAILURE;
        }

        if ($healthy) {
            $this->info('Worker Watch health check passed.');

            return self::SUCCESS;
        }

        $this->error('Worker Watch health check failed.');

        return self::FAILURE;
    }

    /**
     * @return array<int, \Kaiphpdev\WorkerWatch\WorkerSnapshot>
     */
    private function filteredWorkers(): array
    {
        $workers = $this->manager->workers();

        $queue = $this->stringOption('queue');
        $connection = $this->stringOption('connection');

        return array_values(
            array_filter(
                $workers,
                static function ($worker) use (
                    $queue,
                    $connection,
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

                    return true;
                }
            )
        );
    }

    /**
     * @param array<int, \Kaiphpdev\WorkerWatch\WorkerSnapshot> $workers
     */
    private function isHealthy(array $workers): bool
    {
        if ($workers === []) {
            return false;
        }

        foreach ($workers as $worker) {
            if ($worker->status !== WorkerStatus::Healthy) {
                return false;
            }
        }

        return ! $this->manager->hasCapacityFailure();
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