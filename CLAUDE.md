# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## General Instructions

Do not make any changes until you have 95% confidence in what you need to build. Ask me follow-up questions until you reach that confidence.

## Commands

```sh
# Install PHP dependencies
composer install

# Install JS dependencies (required for wp-env)
npm install

# Lint (PHPCS)
composer lint

# Auto-fix linting issues (PHPCBF)
composer format

# Static analysis
composer phpstan

# Regenerate PHPStan baseline (after intentional changes that add new errors)
composer phpstan-baseline

# Run PHPUnit tests via wp-env (single site)
composer test          # delegates to: npm test
npm test

# Run PHPUnit tests via wp-env (multisite)
composer test-ms       # delegates to: npm run test:multisite
npm run test:multisite

# Run PHPUnit tests with code coverage (requires Xdebug — installed automatically on wp-env start)
npm run test:coverage

# Start/stop wp-env Docker environment
# Note: afterStart lifecycle script installs Xdebug into the tests-cli container
npm run wp-env start
npm run wp-env stop

# Run a single test class or method
# Use npm test with --filter so WP_TESTS_PHPUNIT_POLYFILLS_PATH is set automatically.
# Direct wp-env invocations omit this env var and will fail with a polyfills error.
npm test -- --filter=Test_API
```

## Testing Environment

Tests use `@wordpress/env` (wp-env) — a Docker-based WordPress environment. The plugin is mounted inside the `tests-cli` container at `/var/www/html/wp-content/plugins/git-updater/`. The WordPress test library is pre-provisioned by wp-env at `/tmp/wordpress-tests-lib` inside the container. `tests/bootstrap.php` falls back to that path automatically when `WP_TESTS_DIR` is unset.

The `WP_TESTS_PHPUNIT_POLYFILLS_PATH` is passed explicitly in the npm scripts to point to the vendored `yoast/phpunit-polyfills`.

PHPStan is configured at level 6 (`phpstan.neon`) with pre-existing errors tracked in `phpstan-baseline.neon`. The baseline should be regenerated with `composer phpstan-baseline` when intentional changes alter the error set.

All `missingType.iterableValue` and `missingType.return` errors have been resolved across the codebase. When adding new methods or properties, follow the established PHPDoc conventions:
- Use specific array value types: `array<string, mixed>`, `array<int, string>`, `array<string, stdClass>`, etc. — never bare `array`
- Add `@return void` to every method that returns nothing
- Repo config collections are typed `array<string, stdClass>`; option arrays are `array<string, mixed>`

## Architecture
For architecture hints see docs/claude-architecture.md

## Testing
When writing tests always check for passing in both single site, multisite, and PHPStan.
Ensure that current tests are uneffected by new tests.
When running tests, no HTML should be echoed in the test results.
For testing hints see docs/claude-testing-gotchas.md
