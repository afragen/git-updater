# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
composer install              # install dependencies
composer test                 # run all tests
composer stan                 # PHPStan static analysis
composer cs                   # check code style (dry-run)
composer cs:fix               # fix code style
composer check                # run cs + stan + tests in sequence

# Run a single test file
./vendor/bin/phpunit tests/SectionParsingTest.php

# Run a specific test method
./vendor/bin/phpunit --filter testMethodName

# Run a test suite
./vendor/bin/phpunit --testsuite Unit
./vendor/bin/phpunit --testsuite Integration
```

## Architecture

The library has a single entry point: `src/Parser.php`. It accepts input (file path, URL, or raw string) and two optional injectable dependencies.

**Dependency injection contracts** (`src/Contracts/`):
- `HtmlSanitizerInterface` — `sanitize(string $html): string`
- `MarkdownConverterInterface` — `toHtml(string $markdown): string`

**Default adapters** (`src/Adapters/`):
- `SymfonyHtmlSanitizerAdapter` — wraps `symfony/html-sanitizer`
- `ParsedownAdapter` — wraps `erusev/parsedown`

**Parsing flow**: `Parser::__construct()` resolves input → parses headers → parses sections → renders Markdown → sanitizes HTML. All parsed data is stored as public properties on the `Parser` instance.

**`parseData()`** applies a second pipeline on top of raw parsing: contributor expansion (adds profile/avatar URLs), FAQ re-rendered as `<h4>`, changelog headings promoted to `<h4>`, and screenshots rendered as a linked `<ol>`.

## Tests

Tests live in `tests/` and extend `ParserTestCase`, which provides three factory helpers:
- `parse(string)` — stubs both adapters (fastest; for logic/structure tests)
- `parseReal(string)` — uses real adapters (for HTML output tests)
- `parseFixture(string)` / `parseFixtureReal(string)` — loads from `tests/fixtures/`
- `makeReadme(headers, body, name)` — builds a minimal valid readme inline

Test suites are split by concern: `SectionParsingTest`, `NameAndHeaderParsingTest`, `LicenseValidationTest`, `VersionSanitizationTest`, `SecurityTest`, `HtmlAndMarkdownTest`, `EdgeCasesTest`, `GitUpdaterHelpersTest`.

CI runs PHP 8.2–8.5 (matrix), PHPStan, and PHP CS Fixer. Coverage is uploaded from the PHP 8.5 job only.
