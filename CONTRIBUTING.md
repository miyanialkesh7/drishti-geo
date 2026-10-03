# Contributing to Drishti GEO

Thanks for your interest in contributing! This document covers how to set up the project locally and the standards your pull request is expected to meet.

## Development setup

```bash
composer install
```

This installs the dev-only tooling used for linting, static analysis, and tests. The plugin itself has no runtime dependencies (the optional "reuse an existing WordPress AI Client connection" feature detects another already-installed plugin's AI Client library at runtime; it is never required).

## Available commands

| Command | Purpose |
|---|---|
| `composer lint` | Check code against WordPress Coding Standards (PHPCS) |
| `composer lint:fix` | Auto-fix what PHPCS can fix |
| `composer analyse` | Run PHPStan static analysis |
| `composer test` | Run the PHPUnit test suite |
| `composer compat` | Check PHP 7.4+ compatibility (PHPCompatibilityWP) |

## Before opening a pull request

- Run `composer lint`, `composer analyse`, and `composer test` locally — the same checks run in CI on every push and PR.
- Update `readme.txt` and `CHANGELOG.md` if your change is user-facing.
- Keep pull requests focused on a single change; unrelated cleanups should be a separate PR.

## Coding standards

- Follow the [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/).
- Prefix all globally-scoped functions, classes, and constants with `DRISHTI_GEO`/`Drishti_GEO`/`drishti_geo`.
- Escape output, sanitize input, and use nonces per the [WordPress Plugin Security guidelines](https://developer.wordpress.org/plugins/security/).

## Reporting bugs / requesting features

Please use the issue templates when opening a new issue on GitHub.
