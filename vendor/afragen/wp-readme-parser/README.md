# WP Readme Parser

A PHP library for parsing WordPress plugin `readme.txt` files into structured data,
with no WordPress dependencies.

[![MIT License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP 8.1–8.5](https://img.shields.io/badge/php-8.1--8.5-8892BF.svg)](https://www.php.net/)

## Requirements

- PHP 8.1 – 8.5
- Composer

## Installation

```bash
composer require fragen/wp-readme-parser
```

## Usage

```php
use Fragen\WP_Readme_Parser\Parser;

// From a file path
$parser = new Parser('/path/to/readme.txt');

// From a URL
$parser = new Parser('https://plugins.svn.wordpress.org/my-plugin/trunk/readme.txt');

// From a raw string
$parser = new Parser($readmeContents);
```

### Parsed properties

The parser exposes the same public properties as the official WordPress.org readme
parser, making it a drop-in replacement for subclassing.

| Property | Type | Description |
|---|---|---|
| `$name` | `string\|false` | Plugin name. `false` if the name header was absent or used the placeholder value. |
| `$tags` | `array` | Up to 5 tags (ignored tags and extras are dropped with warnings). |
| `$requires` | `string` | Minimum WordPress version, e.g. `"6.0"`. |
| `$tested` | `string` | Tested-up-to version. |
| `$requires_php` | `string` | Minimum PHP version. |
| `$contributors` | `string[]` | Contributor slugs (format-validated). |
| `$stable_tag` | `string` | Stable tag / version string. |
| `$donate_link` | `string` | Donation URL (http/https only). |
| `$license` | `string` | License identifier. |
| `$license_uri` | `string` | License URL. |
| `$short_description` | `string` | Plain-text short description, max 150 characters. |
| `$sections` | `array` | Rendered HTML sections keyed by name: `description`, `installation`, `faq`, `changelog`. |
| `$faq` | `array` | FAQ entries keyed by question string, values are rendered HTML. |
| `$screenshots` | `array` | Screenshot captions, 1-based integer keys. |
| `$upgrade_notice` | `array` | Upgrade notices keyed by version string. |
| `$warnings` | `array` | Parsing anomaly flags — see below. |
| `$raw_contents` | `string` | Unmodified input. |

### Warning keys

| Key | Meaning |
|---|---|
| `invalid_plugin_name_header` | Name header was missing or used the `Plugin Name` placeholder. |
| `ignored_tags` | Tags removed because they appear in the ignore list (`plugin`, `wordpress`). |
| `too_many_tags` | More than 5 tags supplied; extras dropped. |
| `contributor_ignored` | One or more contributor slugs had an invalid format and were dropped. |
| `requires_php_header_ignored` | `Requires PHP` value was not a valid `x.y[.z]` version string. |
| `requires_header_ignored` | `Requires at least` value could not be parsed as a version. |
| `tested_header_ignored` | `Tested up to` value could not be parsed as a version. |
| `license_missing` | No `License` header was found. |
| `invalid_license` | License appears to be incompatible with WordPress directory guidelines. |
| `unknown_license` | License could not be identified as compatible or incompatible. |
| `no_short_description_present` | Short description was inferred from the description section body. |
| `trimmed_short_description` | Short description was truncated to 150 characters. |
| `trimmed_section_*` | A section was truncated to its word limit (2 500 general; 5 000 for `changelog` and `faq`). |

## Dependency injection

The sanitizer and Markdown converter are both injectable:

```php
use Fragen\WP_Readme_Parser\Contracts\HtmlSanitizerInterface;
use Fragen\WP_Readme_Parser\Contracts\MarkdownConverterInterface;

$parser = new Parser(
    input: $readmeContents,
    sanitizer: $myCustomSanitizer,   // implements HtmlSanitizerInterface
    markdown:  $myCustomConverter,   // implements MarkdownConverterInterface
);
```

## Differences from a WordPress-native environment

| Area | This library | WordPress-native |
|---|---|---|
| HTML sanitization | Native `DOMDocument` (`NativeHtmlSanitizerAdapter`) | `wp_kses()` |
| Markdown | `erusev/parsedown` | Internal WP.org Markdown class |
| Contributor validation | Slug format check only | Live `get_user_by()` WP DB query |
| `Tested up to` upper bound | Not enforced | Capped at `WP_CORE_STABLE_BRANCH + 0.1` |
| WordPress dependency | None | Required |

## Running tests

```bash
composer install
./vendor/bin/phpunit                     # all tests
./vendor/bin/phpunit --testsuite Unit    # unit tests only
./vendor/bin/phpunit --testsuite Integration  # real-adapter tests only
composer stan                            # PHPStan static analysis
composer cs                              # check code style
composer cs:fix                          # fix code style
```

## License

[MIT](LICENSE) © Andy Fragen
