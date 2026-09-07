# Laravel Worker Watch

Production-ready Laravel queue worker monitoring with heartbeat tracking, stalled-job detection, failure-rate monitoring, expected worker capacity checks, health transition events, and machine-readable status output.

## Features

- Worker heartbeat tracking
- Stale and critical worker detection
- Long-running / stalled job detection
- Job processed and failed counters
- Lifetime failure-rate monitoring
- Rolling recent-failure monitoring
- Expected worker capacity monitoring
- Worker health transition events
- Capacity transition events
- Recovery detection
- Cache-backed storage with Redis support
- Queue, connection, and status filtering
- JSON status output
- Docker / Kubernetes-friendly exit codes
- Worker record pruning
- Laravel package auto-discovery
- Laravel 11 and Laravel 12 support
- PHP 8.2, 8.3, and 8.4 support

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x

## Installation

Install the package with Composer:

```bash
composer require kaiphpdev/laravel-worker-watch
```

Laravel package discovery will automatically register the service provider.

Publish the configuration:

```bash
php artisan vendor:publish --tag=worker-watch-config
```

This creates:

```text
config/worker-watch.php
```

## Basic Usage

Start your Laravel queue worker normally:

```bash
php artisan queue:work
```

Laravel Worker Watch listens to Laravel queue lifecycle events automatically. No changes are required inside your jobs.

Check worker health:

```bash
php artisan worker-watch:status
```

Example summary:

```text
Workers: 3 | Healthy: 2 | Degraded: 1 | Stale: 0 | Critical: 0
```

## JSON Output

For monitoring systems and scripts:

```bash
php artisan worker-watch:status --json
```

Example:

```json
{
    "healthy": true,
    "summary": {
        "total": 2,
        "healthy": 2,
        "degraded": 0,
        "stale": 0,
        "critical": 0,
        "unknown": 0
    },
    "capacity": [],
    "workers": []
}
```

## Filtering

Filter by queue:

```bash
php artisan worker-watch:status --queue=emails
```

Filter by connection:

```bash
php artisan worker-watch:status --connection=redis
```

Filter by status:

```bash
php artisan worker-watch:status --status=critical
```

## Exit Codes

The status command returns:

```text
0 = all matching workers are healthy and expected capacity is satisfied
1 = no workers found, an unhealthy worker exists, or expected capacity is insufficient
```

This makes the command suitable for:

- Docker health checks
- Kubernetes probes
- CI pipelines
- Cron-based monitoring
- External monitoring scripts

## Worker Health States

Laravel Worker Watch reports:

```text
healthy
degraded
stale
critical
unknown
```

### Healthy

The worker heartbeat is fresh, the current job is within runtime limits, and failure rates are acceptable.

### Degraded

A worker may be marked degraded when:

- a running job exceeds the configured long-running threshold;
- lifetime failure rate reaches the degraded threshold;
- recent rolling failure rate reaches the degraded threshold.

### Stale

The worker heartbeat has exceeded the configured stale threshold.

### Critical

A worker may be marked critical when:

- heartbeat age exceeds the critical threshold;
- a running job exceeds the critical runtime;
- lifetime failure rate reaches the critical threshold;
- recent rolling failure rate reaches the critical threshold.

## Heartbeat Configuration

Example:

```php
'heartbeat' => [
    'interval' => 15,
    'stale_after' => 60,
    'critical_after' => 180,
],
```

Meaning:

```text
< 60 seconds   → healthy
>= 60 seconds  → stale
>= 180 seconds → critical
```

## Long-Running Job Detection

Example:

```php
'jobs' => [
    'long_running_after' => 300,
    'critical_after' => 900,
],
```

Meaning:

```text
< 300 seconds   → normal
>= 300 seconds  → degraded
>= 900 seconds  → critical
```

## Lifetime Failure-Rate Monitoring

Example:

```php
'failure_rate' => [
    'enabled' => true,
    'minimum_jobs' => 20,
    'degraded_at' => 10,
    'critical_at' => 25,
],
```

The minimum sample size prevents a very small number of jobs from immediately producing misleading health results.

## Rolling Recent-Failure Monitoring

Lifetime statistics can hide sudden failures. Worker Watch can also evaluate only the most recent job results.

Example:

```php
'failure_rate' => [
    // ...

    'rolling_window' => [
        'enabled' => true,
        'size' => 50,
        'minimum_jobs' => 10,
        'degraded_at' => 20,
        'critical_at' => 40,
    ],
],
```

Example:

```text
Lifetime:
980 successful
20 failed
= 2% failure rate

Recent 10:
5 successful
5 failed
= 50% recent failure rate
```

The worker can therefore be reported as critical even though the lifetime failure rate still looks healthy.

## Expected Worker Capacity

Worker Watch can detect missing workers even when all currently running workers are healthy.

Example:

```php
'capacity' => [
    'enabled' => true,

    'expected' => [
        'redis:default' => 3,
        'redis:emails' => 2,
    ],
],
```

If `redis:default` has only two usable workers:

```text
Expected: 3
Actual:   2
Missing:  1
```

The health command returns a failure exit code.

## Health Transition Events

Worker Watch dispatches events when worker health changes.

```php
use Kaiphpdev\WorkerWatch\Events\WorkerBecameUnhealthy;
use Kaiphpdev\WorkerWatch\Events\WorkerRecovered;
```

You may listen to these events from your Laravel application and send Slack, email, PagerDuty, or custom alerts.

The package is transition-aware, so it does not continuously emit the same unhealthy event on every health evaluation.

## Capacity Transition Events

The package also provides:

```php
use Kaiphpdev\WorkerWatch\Events\WorkerCapacityBecameInsufficient;
use Kaiphpdev\WorkerWatch\Events\WorkerCapacityRecovered;
```

These events can be used to alert when the number of usable workers falls below the configured expectation and when capacity later recovers.

## Storage

Worker Watch uses Laravel's cache abstraction.

You can select a cache store in:

```php
'store' => env('WORKER_WATCH_STORE'),
```

For production environments, Redis is recommended.

Example:

```env
WORKER_WATCH_STORE=redis
```

The package does not require a database table by default.

## Pruning Worker Records

Remove old worker records using:

```bash
php artisan worker-watch:prune
```

The default threshold comes from:

```php
'retention' => 3600,
```

You can override it for one run:

```bash
php artisan worker-watch:prune --older-than=7200
```

## Configuration Environment Variables

Common options include:

```env
WORKER_WATCH_ENABLED=true
WORKER_WATCH_STORE=redis

WORKER_WATCH_HEARTBEAT_INTERVAL=15
WORKER_WATCH_STALE_AFTER=60
WORKER_WATCH_CRITICAL_AFTER=180

WORKER_WATCH_LONG_JOB_AFTER=300
WORKER_WATCH_CRITICAL_JOB_AFTER=900

WORKER_WATCH_FAILURE_RATE_ENABLED=true
WORKER_WATCH_FAILURE_MINIMUM_JOBS=20
WORKER_WATCH_FAILURE_DEGRADED_AT=10
WORKER_WATCH_FAILURE_CRITICAL_AT=25

WORKER_WATCH_ROLLING_FAILURE_ENABLED=true
WORKER_WATCH_ROLLING_FAILURE_WINDOW=50
WORKER_WATCH_ROLLING_FAILURE_MINIMUM_JOBS=10
WORKER_WATCH_ROLLING_FAILURE_DEGRADED_AT=20
WORKER_WATCH_ROLLING_FAILURE_CRITICAL_AT=40

WORKER_WATCH_CAPACITY_ENABLED=true
WORKER_WATCH_DEFAULT_WORKERS=1

WORKER_WATCH_RETENTION=3600
```

## Docker Health Check Example

```dockerfile
HEALTHCHECK --interval=30s --timeout=10s --retries=3 \
    CMD php artisan worker-watch:status --json || exit 1
```

## Kubernetes Probe Example

A lightweight pattern is to invoke the status command from an `exec` probe:

```yaml
livenessProbe:
  exec:
    command:
      - php
      - artisan
      - worker-watch:status
      - --json
  initialDelaySeconds: 30
  periodSeconds: 30
```

Adjust probe behavior to match your deployment architecture and queue topology.

## Supervisor

Worker Watch does not replace Supervisor. Continue managing queue workers with Supervisor as usual:

```ini
command=php /path/to/artisan queue:work redis --queue=default --sleep=3 --tries=3
```

Worker Watch adds application-level worker health visibility on top of your process manager.

## Development

Install dependencies:

```bash
composer install
```

Run tests:

```bash
composer test
```

Check formatting:

```bash
composer format-check
```

Apply formatting:

```bash
composer format
```

## Supported Versions

The intended compatibility matrix is:

| PHP | Laravel |
|---|---|
| 8.2 | 11.x, 12.x |
| 8.3 | 11.x, 12.x |
| 8.4 | 11.x, 12.x |

Compatibility is verified by the project's GitHub Actions test matrix.

## Security

Please do not report security vulnerabilities through public GitHub issues.

See [SECURITY.md](SECURITY.md).

## Contributing

Contributions are welcome.

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Code of Conduct

See [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).

## License

Laravel Worker Watch is open-source software licensed under the MIT License.

See [LICENSE](LICENSE).
