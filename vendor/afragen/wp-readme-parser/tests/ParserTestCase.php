<?php

declare(strict_types=1);

namespace Fragen\WP_Readme_Parser\Tests;

use Fragen\WP_Readme_Parser\Contracts\HtmlSanitizerInterface;
use Fragen\WP_Readme_Parser\Contracts\MarkdownConverterInterface;
use Fragen\WP_Readme_Parser\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Shared helpers for all Parser test cases.
 */
abstract class ParserTestCase extends TestCase
{
    // -------------------------------------------------------------------------
    // Stubs
    // -------------------------------------------------------------------------

    /**
     * A no-op HTML sanitizer that returns its input unchanged.
     * Use this when a test cares about structure, not sanitization behaviour.
     */
    protected function passThroughSanitizer(): HtmlSanitizerInterface
    {
        return new class implements HtmlSanitizerInterface {
            public function sanitize(string $html): string
            {
                return $html;
            }
        };
    }

    /**
     * A no-op Markdown converter that returns its input unchanged.
     * Use this when a test cares about structure, not Markdown rendering.
     */
    protected function passThroughMarkdown(): MarkdownConverterInterface
    {
        return new class implements MarkdownConverterInterface {
            public function toHtml(string $markdown): string
            {
                return $markdown;
            }
        };
    }

    // -------------------------------------------------------------------------
    // Factory helpers
    // -------------------------------------------------------------------------

    /**
     * Build a Parser from a raw string with both dependencies stubbed out.
     * The fastest option for structural/logic tests.
     */
    protected function parse(string $content): Parser
    {
        return new Parser($content, $this->passThroughSanitizer(), $this->passThroughMarkdown());
    }

    /**
     * Build a Parser from a raw string using the real Symfony sanitizer and Parsedown.
     * Use only for integration-level tests that care about the actual HTML output.
     */
    protected function parseReal(string $content): Parser
    {
        return new Parser($content);
    }

    /**
     * Build a Parser from a fixture file path.
     *
     * @param string $relative Path relative to tests/fixtures/, e.g. 'valid/standard.txt'.
     */
    protected function parseFixture(string $relative): Parser
    {
        $path = __DIR__ . '/fixtures/' . $relative;
        self::assertFileExists($path, "Fixture not found: {$path}");

        return $this->parse(file_get_contents($path));
    }

    /**
     * Build a Parser from a fixture file using real adapters.
     */
    protected function parseFixtureReal(string $relative): Parser
    {
        $path = __DIR__ . '/fixtures/' . $relative;
        self::assertFileExists($path, "Fixture not found: {$path}");

        return $this->parseReal(file_get_contents($path));
    }

    // -------------------------------------------------------------------------
    // Inline readme builder
    // -------------------------------------------------------------------------

    /**
     * Quickly build a minimal valid readme string with optional overrides.
     *
     * @param array<string, string> $headers Extra header lines (e.g. ['Stable tag' => '1.0.0']).
     * @param string $body Everything after the header block.
     * @param string $name Plugin name in the title line.
     */
    protected function makeReadme(
        array  $headers = [],
        string $body = 'Short description.',
        string $name = 'Test Plugin',
    ): string {
        $defaults = [
            'License' => 'MIT',
        ];

        $merged = array_merge($defaults, $headers);

        $headerLines = '';

        foreach ($merged as $key => $value) {
            $headerLines .= "{$key}: {$value}\n";
        }

        return "=== {$name} ===\n{$headerLines}\n{$body}";
    }
}
