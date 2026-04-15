<?php

declare(strict_types=1);

/**
 * @copyright 2026 Andy Fragen
 * @license   MIT
 *
 * @link      https://github.com/afragen/wp-readme-parser
 */

namespace Fragen\WP_Readme_Parser\Contracts;

/**
 * Contract for sanitizing an HTML string against an allowlist.
 */
interface HtmlSanitizerInterface
{
    public function sanitize(string $html): string;
}
