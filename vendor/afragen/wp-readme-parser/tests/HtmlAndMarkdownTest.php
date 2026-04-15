<?php

declare(strict_types=1);

namespace Fragen\WP_Readme_Parser\Tests;

use Fragen\WP_Readme_Parser\Contracts\HtmlSanitizerInterface;
use Fragen\WP_Readme_Parser\Contracts\MarkdownConverterInterface;
use Fragen\WP_Readme_Parser\Parser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for HTML sanitization, Markdown rendering, and dependency injection.
 *
 * These tests use the real adapters (Symfony + Parsedown) because they are
 * testing the integration of those libraries, not just the parser's structure.
 *
 * @covers \Fragen\WP_Readme_Parser\Parser
 * @covers \Fragen\WP_Readme_Parser\Adapters\NativeHtmlSanitizerAdapter
 * @covers \Fragen\WP_Readme_Parser\Adapters\ParsedownAdapter
 */
class HtmlAndMarkdownTest extends ParserTestCase
{
    // -------------------------------------------------------------------------
    // HTML sanitization (real adapter)
    // -------------------------------------------------------------------------

    #[Test]
    public function it_strips_disallowed_html_tags(): void
    {
        $readme = $this->makeReadme(
            body: "Desc.\n\n== Description ==\n<script>alert('xss')</script><p>Safe</p>",
        );
        $parser = $this->parseReal($readme);

        $this->assertStringNotContainsString('<script>', $parser->sections['description']);
        $this->assertStringContainsString('<p>Safe</p>', $parser->sections['description']);
    }

    #[Test]
    public function it_strips_disallowed_attributes(): void
    {
        $readme = $this->makeReadme(
            body: "Desc.\n\n== Description ==\n<p onclick=\"bad()\">Text</p>",
        );
        $parser = $this->parseReal($readme);

        $this->assertStringNotContainsString('onclick', $parser->sections['description']);
        $this->assertStringContainsString('Text', $parser->sections['description']);
    }

    #[Test]
    public function it_allows_anchor_tags_with_permitted_attributes(): void
    {
        $readme = $this->makeReadme(
            body: "Desc.\n\n== Description ==\n<a href=\"https://example.com\" title=\"X\" rel=\"nofollow\">Link</a>",
        );
        $parser = $this->parseReal($readme);

        $this->assertStringContainsString('href=', $parser->sections['description']);
        $this->assertStringContainsString('title=', $parser->sections['description']);
        $this->assertStringContainsString('rel=', $parser->sections['description']);
    }

    #[Test]
    public function it_allows_h3_and_h4_but_strips_h1_and_h2(): void
    {
        $readme = $this->makeReadme(
            body: "Desc.\n\n== Description ==\n<h1>No</h1><h2>No</h2><h3>Yes</h3><h4>Yes</h4>",
        );
        $parser = $this->parseReal($readme);

        $this->assertStringNotContainsString('<h1>', $parser->sections['description']);
        $this->assertStringNotContainsString('<h2>', $parser->sections['description']);
        $this->assertStringContainsString('<h3>Yes</h3>', $parser->sections['description']);
        $this->assertStringContainsString('<h4>Yes</h4>', $parser->sections['description']);
    }

    #[Test]
    public function it_strips_img_tags_produced_by_markdown(): void
    {
        $readme = $this->makeReadme(
            body: "Desc.\n\n== Description ==\n![Alt text](https://example.com/img.png)",
        );
        $parser = $this->parseReal($readme);

        $this->assertStringNotContainsString('<img', $parser->sections['description']);
    }

    // -------------------------------------------------------------------------
    // Markdown rendering (real adapter)
    // -------------------------------------------------------------------------

    #[Test]
    public function it_renders_markdown_bold_in_sections(): void
    {
        $readme = $this->makeReadme(
            body: "Desc.\n\n== Description ==\n**Bold text** here.",
        );
        $parser = $this->parseReal($readme);

        $this->assertStringContainsString('<strong>', $parser->sections['description']);
    }

    #[Test]
    public function it_renders_markdown_links_in_sections(): void
    {
        $readme = $this->makeReadme(
            body: "Desc.\n\n== Description ==\n[Click here](https://example.com)",
        );
        $parser = $this->parseReal($readme);

        $this->assertStringContainsString('<a href=', $parser->sections['description']);
    }

    #[Test]
    public function it_renders_markdown_code_blocks(): void
    {
        $readme = $this->makeReadme(
            body: "Desc.\n\n== Description ==\n```\nsome_code();\n```",
        );
        $parser = $this->parseReal($readme);

        $this->assertStringContainsString('<code>', $parser->sections['description']);
    }

    // -------------------------------------------------------------------------
    // Dependency injection
    // -------------------------------------------------------------------------

    #[Test]
    public function it_uses_a_custom_html_sanitizer(): void
    {
        $marker    = '<!--CUSTOM_SANITIZER-->';
        $sanitizer = new class ($marker) implements HtmlSanitizerInterface {
            public function __construct(private string $marker) {}

            public function sanitize(string $html): string
            {
                return $this->marker . $html;
            }
        };

        $readme = $this->makeReadme(body: "Desc.\n\n== Description ==\nHello.");
        $parser = new Parser($readme, $sanitizer, $this->passThroughMarkdown());

        $this->assertStringContainsString($marker, $parser->sections['description']);
    }

    #[Test]
    public function it_uses_a_custom_markdown_converter(): void
    {
        $marker   = '<!--CUSTOM_MD-->';
        $markdown = new class ($marker) implements MarkdownConverterInterface {
            public function __construct(private string $marker) {}

            public function toHtml(string $md): string
            {
                return $this->marker . $md;
            }
        };

        $readme = $this->makeReadme(body: "Desc.\n\n== Description ==\nHello.");
        $parser = new Parser($readme, $this->passThroughSanitizer(), $markdown);

        $this->assertStringContainsString($marker, $parser->sections['description']);
    }

    #[Test]
    public function it_uses_default_adapters_when_none_are_injected(): void
    {
        // Smoke-test: real adapters boot without error and produce non-empty output.
        $parser = $this->parseReal($this->makeReadme(
            body: "Desc.\n\n== Description ==\n**Bold**",
        ));

        $this->assertNotEmpty($parser->sections['description']);
        $this->assertStringContainsString('<strong>', $parser->sections['description']);
    }

    // -------------------------------------------------------------------------
    // HTML injection — sanitizer must strip dangerous content (security)
    // -------------------------------------------------------------------------

    #[Test]
    public function it_strips_script_tags_from_section_content(): void
    {
        $readme = $this->makeReadme(
            body: "Desc.\n\n== Description ==\n<script>document.location='https://evil.example'</script>Safe content.",
        );
        $parser = $this->parseReal($readme);

        $this->assertStringNotContainsString('<script>', $parser->sections['description']);
        $this->assertStringNotContainsString('document.location', $parser->sections['description']);
    }

    #[Test]
    public function it_strips_event_handler_attributes(): void
    {
        $readme = $this->makeReadme(
            body: "Desc.\n\n== Description ==\n<p onmouseover=\"alert(1)\">Hover me</p>",
        );
        $parser = $this->parseReal($readme);

        $this->assertStringNotContainsString('onmouseover', $parser->sections['description']);
        $this->assertStringContainsString('Hover me', $parser->sections['description']);
    }

    #[Test]
    public function it_strips_iframe_tags(): void
    {
        $readme = $this->makeReadme(
            body: "Desc.\n\n== Description ==\n<iframe src=\"https://evil.example\"></iframe>",
        );
        $parser = $this->parseReal($readme);

        $this->assertStringNotContainsString('<iframe', $parser->sections['description']);
    }

    #[Test]
    public function it_strips_object_and_embed_tags(): void
    {
        $readme = $this->makeReadme(
            body: "Desc.\n\n== Description ==\n<object data=\"evil.swf\"></object><embed src=\"evil.swf\">",
        );
        $parser = $this->parseReal($readme);

        $this->assertStringNotContainsString('<object', $parser->sections['description']);
        $this->assertStringNotContainsString('<embed', $parser->sections['description']);
    }

    #[Test]
    public function it_strips_style_attributes(): void
    {
        $readme = $this->makeReadme(
            body: "Desc.\n\n== Description ==\n<p style=\"background:url(javascript:alert(1))\">Styled</p>",
        );
        $parser = $this->parseReal($readme);

        $this->assertStringNotContainsString('style=', $parser->sections['description']);
        $this->assertStringNotContainsString('javascript:alert', $parser->sections['description']);
    }
}
