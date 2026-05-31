<?php

declare(strict_types=1);

/**
 * @copyright 2026 Andy Fragen
 * @license   MIT
 *
 * @link      https://github.com/afragen/wp-readme-parser
 */

namespace Fragen\WP_Readme_Parser\Adapters;

use Fragen\WP_Readme_Parser\Contracts\MarkdownConverterInterface;
use Parsedown;

/**
 * Wraps erusev/parsedown to satisfy MarkdownConverterInterface.
 */
class ParsedownAdapter implements MarkdownConverterInterface
{
    private Parsedown $parsedown;

    public function __construct()
    {
        $this->parsedown = new Parsedown();
        $this->parsedown->setSafeMode(false);
    }

    public function toHtml(string $markdown): string
    {
        return $this->parsedown->text($markdown);
    }
}
