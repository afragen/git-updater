# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## General Instructions

Do not make any changes until you have 95% confidence in what you need to build. Ask me follow-up questions until you reach that confidence.

## Commands

```sh
# Install PHP dependencies
composer install

# Install JS dependencies (required for wp-env-opossum / mac-env)
npm install

# Lint (PHPCS)
composer lint

# Auto-fix linting issues (PHPCBF)
composer format

# Static analysis
composer phpstan

# Regenerate PHPStan baseline (after intentional changes that add new errors)
composer phpstan-baseline

# Run PHPUnit tests via mac-env / opossum (single site)
composer test          # delegates to: npm test
npm test

# Run PHPUnit tests via mac-env / opossum (multisite)
composer test-ms       # delegates to: npm run test:multisite
npm run test:multisite

# Run PHPUnit tests with code coverage (requires Xdebug — compiled into the CLI image by mac-env)
npm run test:coverage

# Start/stop the dev/test stack (Apple container via opossum; Xdebug baked into the image)
npm run env:start
npm run env:stop

# Run a single test class or method
# Use npm test with --filter so WP_TESTS_PHPUNIT_POLYFILLS_PATH is set automatically.
# Direct mac-env invocations omit this env var and will fail with a polyfills error.
npm test -- --filter=Test_API
```

## Testing Environment

**CI (GitHub Actions):** uses the standard WordPress PHPUnit setup — `bin/install-wp-tests.sh` provisions the test library at `/tmp/wordpress-tests-lib` inside the container, and `tests/bootstrap.php` falls back to that path automatically when `WP_TESTS_DIR` is unset.

**Local dev:** the test stack runs on Apple's native `container` runtime via [wp-env-opossum](https://www.npmjs.com/package/wp-env-opossum) (`mac-env`), which brings up the same two-site layout (dev + tests) on Apple silicon macOS 26+. Seed the cache once with `mac-env install-wp-tests`, then `npm run env:start`. The plugin is mounted inside the `tests-cli` container at `/var/www/html/wp-content/plugins/git-updater/`.

The `WP_TESTS_PHPUNIT_POLYFILLS_PATH` is passed explicitly in the npm scripts to point to the vendored `yoast/phpunit-polyfills`.

PHPStan is configured at level 6 (`phpstan.neon`) with pre-existing errors tracked in `phpstan-baseline.neon`. The baseline should be regenerated with `composer phpstan-baseline` when intentional changes alter the error set.

When removing dead code (unused static properties, intermediate writes), PHPStan may reveal type-narrowing errors (`booleanNot.alwaysTrue`, `function.alreadyNarrowedType`) in nearby conditions that were previously obscured. Fix the underlying redundancy — simplify the condition rather than suppressing the error.

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
