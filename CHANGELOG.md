# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.0] - 2026-09-07

### Added

- Laravel queue worker heartbeat tracking.
- Unique worker identity generation using hostname, process ID, connection, and queue.
- Cache-backed worker storage with Redis compatibility.
- Worker health states: healthy, degraded, stale, critical, and unknown.
- Stale and critical heartbeat detection.
- Long-running and stalled job detection.
- Job processed and failed counters.
- Lifetime failure-rate monitoring.
- Rolling recent-failure monitoring.
- Expected worker capacity monitoring.
- Worker health transition events.
- Worker recovery events.
- Capacity insufficiency and recovery events.
- `worker-watch:status` Artisan command.
- `worker-watch:health` lightweight health-check command.
- `worker-watch:prune` worker cleanup command.
- JSON health output.
- Queue, connection, and status filters.
- Monitoring-friendly exit codes.
- Worker retention configuration.
- Laravel 11 and Laravel 12 support.
- PHP 8.2, 8.3, and 8.4 support.
- Automated test suite.
- GitHub Actions compatibility matrix.

## [1.0.0] - TBD

Initial public release.
