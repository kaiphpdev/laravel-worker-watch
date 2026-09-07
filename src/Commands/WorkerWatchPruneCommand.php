<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Commands;

use Illuminate\Console\Command;
use Kaiphpdev\WorkerWatch\Contracts\WorkerStore;

final class WorkerWatchPruneCommand extends Command
{
    protected $signature = 'worker-watch:prune
        {--older-than= : Remove workers older than this many seconds}';

    protected $description = 'Remove expired Laravel Worker Watch records';

    public function __construct(
        private readonly WorkerStore $store,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $retention = $this->retention();

        $now = time();

        $removed = 0;

        foreach ($this->store->all() as $worker) {
            if (($now - $worker->lastHeartbeatAt) < $retention) {
                continue;
            }

            $this->store->forget($worker->id);

            $removed++;
        }

        $this->info(
            sprintf(
                'Pruned %d worker record%s.',
                $removed,
                $removed === 1 ? '' : 's',
            )
        );

        return self::SUCCESS;
    }

    private function retention(): int
    {
        $option = $this->option('older-than');

        if (is_string($option) && trim($option) !== '') {
            return max(
                1,
                (int) $option,
            );
        }

        return max(
            1,
            (int) config(
                'worker-watch.retention',
                3600,
            ),
        );
    }
}
