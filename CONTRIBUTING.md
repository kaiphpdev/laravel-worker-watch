# Contributing

Thank you for considering contributing to Laravel Worker Watch.

## Development Setup

Fork and clone the repository, then install dependencies:

```bash
composer install
```

Run the test suite:

```bash
composer test
```

Check code formatting:

```bash
composer format-check
```

Apply formatting:

```bash
composer format
```

## Pull Requests

Before opening a pull request:

1. Keep the change focused on one concern.
2. Add or update tests for behavioral changes.
3. Run the full test suite.
4. Run the formatting check.
5. Update documentation when user-facing behavior changes.
6. Avoid committing generated files, `vendor/`, PHPUnit cache files, or local IDE files.

## Coding Style

The project uses Laravel Pint.

Run:

```bash
composer format
```

before submitting changes.

## Tests

New features and bug fixes should include tests whenever practical.

Run:

```bash
composer test
```

The package aims to remain compatible with the supported Laravel and PHP versions documented in the README.

## Commit Messages

Prefer clear conventional-style commit messages, for example:

```text
feat: add worker capacity monitoring
fix: preserve failed result in rolling window
test: cover critical worker capacity
docs: document Kubernetes health checks
chore: ignore PHPUnit cache files
```

## Backward Compatibility

Changes intended for a stable release should avoid unnecessary breaking changes.

If a breaking change is required, document it clearly in the pull request and changelog.

## Security Issues

Do not disclose security vulnerabilities in public issues.

Please follow the process in [SECURITY.md](SECURITY.md).

## Code of Conduct

Participation in this project is governed by [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).
