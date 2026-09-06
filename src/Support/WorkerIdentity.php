<?php

declare(strict_types=1);

namespace Kaiphpdev\WorkerWatch\Support;

final class WorkerIdentity
{
    public static function make(string $connection, string $queue, ?string $hostname = null, ?int $processId = null): string
    {

        $hostname ??= gethostname() ?: 'unknown-host';
        $processId ??= getmypid() ?: 0;

        return implode(':', [
            self::normalize($hostname),
            $processId,
            self::normalize($connection),
            self::normalize($queue),
        ]);
    }

    private static function normalize(string $value)
    {
        $value = trim($value);
        if ($value === '') {
            return 'unknown';
        }

        return str_replace([' ', ':'], '-', $value);
    }
}
