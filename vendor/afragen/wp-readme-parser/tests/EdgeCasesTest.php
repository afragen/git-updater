<?php

declare(strict_types=1);

namespace Fragen\WP_Readme_Parser\Tests;

use Fragen\WP_Readme_Parser\Parser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for encoding edge cases, file-path loading, degenerate input,
 * and unit-level trimLength behaviour.
 *
 * @covers \Fragen\WP_Readme_Parser\Parser
 */
class EdgeCasesTest extends ParserTestCase
{
    // -------------------------------------------------------------------------
    // Encoding
    // -------------------------------------------------------------------------

    #[Test]
    public function it_strips_utf8_bom_and_parses_plugin_name(): void
    {
        $parser = $this->parseFixture('edge-cases/utf8-bom.txt');
        $this->assertSame('BOM Plugin', $parser->name);
    }

    #[Test]
    public function it_strips_utf8_bom_from_raw_string(): void
    {
        $bom    = "\xEF\xBB\xBF";
        $readme = "{$bom}=== BOM Plugin ===\nLicense: MIT\n\nDesc.";
        $parser = $this->parse($readme);
        $this->assertSame('BOM Plugin', $parser->name);
    }

    // -------------------------------------------------------------------------
    // File-path loading
    // -------------------------------------------------------------------------

    #[Test]
    public function it_parses_from_a_file_path(): void
    {
        $path   = __DIR__ . '/fixtures/valid/standard.txt';
        $parser = new Parser($path);
        $this->assertSame('My Awesome Plugin', $parser->name);
    }

    #[Test]
    public function it_stores_raw_contents_when_parsing_from_file(): void
    {
        $path     = __DIR__ . '/fixtures/valid/minimal.txt';
        $expected = file_get_contents($path);
        $parser   = new Parser($path);
        $this->assertSame($expected, $parser->raw_contents);
    }

    // -------------------------------------------------------------------------
    // Degenerate input
    // -------------------------------------------------------------------------

    #[Test]
    public function it_handles_empty_string_gracefully(): void
    {
        $parser = new Parser('');
        $this->assertSame('', $parser->name);
        $this->assertEmpty($parser->sections);
        $this->assertEmpty($parser->warnings);
    }

    #[Test]
    public function it_handles_whitespace_only_input(): void
    {
        $parser = $this->parse("   \n\n   ");
        // sections['description'] is always present, defaulting to '' when not defined.
        $this->assertSame(['description' => ''], $parser->sections);
    }

    #[Test]
    public function it_handles_a_readme_with_no_sections_at_all(): void
    {
        $readme = "=== My Plugin ===\nLicense: MIT\n\nJust a short description.";
        $parser = $this->parse($readme);
        $this->assertSame('Just a short description.', $parser->short_description);
        // Description section is populated from short description.
        $this->assertArrayHasKey('description', $parser->sections);
    }

    #[Test]
    public function it_preserves_raw_contents_exactly(): void
    {
        $content = "=== P ===\nLicense: MIT\n\nDesc.";
        $parser  = $this->parse($content);
        $this->assertSame($content, $parser->raw_contents);
    }

    #[Test]
    public function it_handles_windows_style_crlf_line_endings(): void
    {
        $readme = "=== P ===\r\nLicense: MIT\r\n\r\nDesc.";
        $parser = $this->parse($readme);
        $this->assertSame('P', $parser->name);
        $this->assertSame('Desc.', $parser->short_description);
    }

    #[Test]
    public function it_handles_classic_mac_style_cr_line_endings(): void
    {
        $readme = "=== P ===\rLicense: MIT\r\rDesc.";
        $parser = $this->parse($readme);
        $this->assertSame('P', $parser->name);
    }

    // -------------------------------------------------------------------------
    // trimLength — character mode unit tests
    // (Accessed via the public API by triggering short_description truncation)
    // -------------------------------------------------------------------------

    #[Test]
    public function it_trims_by_character_count_and_appends_ellipsis(): void
    {
        // Construct a description > 150 chars with no sentence boundary in the last 20%.
        $desc   = str_repeat('x', 200);
        $readme = $this->makeReadme(body: "{$desc}\n\n== Description ==\nFull.");
        $parser = $this->parse($readme);

        $this->assertStringEndsWith('&hellip;', $parser->short_description);
    }

    #[Test]
    public function it_trims_at_sentence_boundary_when_close_to_limit(): void
    {
        // Build a ~170-char description where a sentence ends around the 80% mark.
        // The last sentence pushes it over 150 chars.
        $first  = str_repeat('a', 121) . '. '; // 123 chars, period at position 121
        $second = str_repeat('b', 50);           // pushes total to 173

        $readme = $this->makeReadme(body: "{$first}{$second}\n\n== Description ==\nFull.");
        $parser = $this->parse($readme);

        // Sentence boundary is beyond 80% of 150 (= 120), so we trim to it.
        // Result ends with '.' and has no ellipsis.
        $this->assertStringEndsWith('.', $parser->short_description);
        $this->assertStringNotContainsString('&hellip;', $parser->short_description);
    }
}
