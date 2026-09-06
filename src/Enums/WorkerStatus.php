<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Enums;

enum WorkerStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Critical = 'critical';
    case Stale = 'stale';
    case Unknown = 'unknown';
}
