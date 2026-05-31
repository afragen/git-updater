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
 * Contract for converting a Markdown string to HTML.
 */
interface MarkdownConverterInterface
{
    public function toHtml(string $markdown): string;
}
