# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Laravel queue worker heartbeat tracking.
- Unique worker identity generation using hostname, process ID, connection, and queue.
- Cache-backed worker storage with Redis compatibility.
- Worker health states: healthy, degraded, stale, critical, and unknown.
- Stale and critical heartbeat detection.
- Long-running and critical job runtime detection.
- Job processed and failed counters.
- Lifetime failure-rate monitoring.
- Rolling recent-failure monitoring.
- Expected worker capacity monitoring.
- Worker health transition events.
- Worker recovery events.
- Capacity insufficiency and recovery events.
- `worker-watch:status` Artisan command.
- JSON health output.
- Queue, connection, and status filters.
- Monitoring-friendly exit codes.
- `worker-watch:prune` Artisan command.
- Worker retention configuration.
- Automated test suite.
- Laravel 11 and Laravel 12 compatibility target.
- PHP 8.2, 8.3, and 8.4 compatibility target.
- GitHub Actions compatibility matrix.

## [1.0.0] - TBD

Initial public release.
