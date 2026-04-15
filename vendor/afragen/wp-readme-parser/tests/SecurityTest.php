<?php

declare(strict_types=1);

namespace Fragen\WP_Readme_Parser\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Security-focused tests for the Parser.
 *
 * Covers: URL scheme validation, HTML injection, XSS vectors in
 * output attributes and content, type correctness of $name, and
 * size/encoding guard-rails.
 *
 * @covers \Fragen\WP_Readme_Parser\Parser
 */
class SecurityTest extends ParserTestCase
{
    // -------------------------------------------------------------------------
    // donate_link — URL scheme allowlist
    // -------------------------------------------------------------------------

    #[Test]
    #[DataProvider('unsafeDonateUrlProvider')]
    public function it_rejects_unsafe_donate_link_schemes(string $url): void
    {
        $parser = $this->parse($this->makeReadme(['Donate link' => $url]));
        $this->assertSame(
            '',
            $parser->donate_link,
            "Expected donate_link to be empty for unsafe URL: {$url}",
        );
    }

    public static function unsafeDonateUrlProvider(): array
    {
        return [
            'javascript scheme' => ['javascript:alert(document.cookie)'],
            'vbscript scheme'   => ['vbscript:msgbox("xss")'],
            'data URI HTML'     => ['data:text/html,<script>alert(1)</script>'],
            'data URI base64'   => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='],
            'protocol-relative' => ['//evil.example.com/steal'],
            'no scheme'         => ['evil.example.com'],
            'ftp scheme'        => ['ftp://files.example.com/readme.txt'],
            'file scheme'       => ['file:///etc/passwd'],
        ];
    }

    #[Test]
    #[DataProvider('safeDonateUrlProvider')]
    public function it_accepts_safe_donate_link_schemes(string $url): void
    {
        $parser = $this->parse($this->makeReadme(['Donate link' => $url]));
        $this->assertSame($url, $parser->donate_link);
    }

    public static function safeDonateUrlProvider(): array
    {
        return [
            'http'  => ['http://example.com/donate'],
            'https' => ['https://example.com/donate'],
        ];
    }

    // -------------------------------------------------------------------------
    // HTML injection in section content
    // -------------------------------------------------------------------------
    // Tests that the real Symfony sanitizer strips dangerous HTML from rendered
    // section output live in HtmlAndMarkdownTest (Integration suite), where all
    // real-adapter tests are co-located.
    // -------------------------------------------------------------------------

    // -------------------------------------------------------------------------
    // XSS via plugin name / short description
    // -------------------------------------------------------------------------

    #[Test]
    public function it_html_encodes_plugin_name(): void
    {
        $parser = $this->parse("=== <script>alert(1)</script> ===\nLicense: MIT\n\nDesc.");
        // name goes through sanitizeText which strips tags then HTML-encodes.
        $this->assertStringNotContainsString('<script>', (string) $parser->name);
    }

    #[Test]
    public function it_html_encodes_short_description(): void
    {
        $parser = $this->parse(
            $this->makeReadme(body: '<script>alert(1)</script> A short description.'),
        );
        $this->assertStringNotContainsString('<script>', $parser->short_description);
    }

    #[Test]
    public function it_html_encodes_upgrade_notice_values(): void
    {
        $readme = $this->makeReadme(
            body: "Desc.\n\n== Upgrade Notice ==\n\n= 1.0.0 =\n<script>alert(1)</script> Upgrade now.",
        );
        $parser = $this->parse($readme);

        foreach ($parser->upgrade_notice as $notice) {
            $this->assertStringNotContainsString('<script>', $notice);
        }
    }

    // -------------------------------------------------------------------------
    // $name type correctness
    // -------------------------------------------------------------------------

    #[Test]
    public function name_is_false_when_no_plugin_name_header_is_present(): void
    {
        $parser = $this->parse("License: MIT\nTags: foo\n\nDesc.");
        $this->assertFalse($parser->name);
    }

    #[Test]
    public function name_is_a_string_when_correctly_parsed(): void
    {
        $parser = $this->parse($this->makeReadme());
        $this->assertIsString($parser->name);
    }

    // -------------------------------------------------------------------------
    // contributor slug sanitization
    // -------------------------------------------------------------------------

    #[Test]
    public function it_rejects_contributor_slugs_containing_path_traversal(): void
    {
        $parser = $this->parse($this->makeReadme(['Contributors' => '../../../etc/passwd']));
        $this->assertEmpty($parser->contributors);
        $this->assertArrayHasKey('contributor_ignored', $parser->warnings);
    }

    #[Test]
    public function it_rejects_contributor_slugs_with_html(): void
    {
        $parser = $this->parse($this->makeReadme(['Contributors' => '<script>alert(1)</script>']));
        $this->assertEmpty($parser->contributors);
    }

    #[Test]
    public function it_rejects_contributor_slugs_with_spaces(): void
    {
        $parser = $this->parse($this->makeReadme(['Contributors' => 'joe bloggs']));
        $this->assertEmpty($parser->contributors);
    }

    // -------------------------------------------------------------------------
    // Raw content preservation — ensure raw_contents is never processed
    // -------------------------------------------------------------------------

    #[Test]
    public function raw_contents_is_the_exact_unmodified_input(): void
    {
        $raw    = "=== P ===\nLicense: MIT\n\n<script>alert(1)</script> Desc.";
        $parser = $this->parse($raw);

        // raw_contents should retain the script tag unchanged.
        $this->assertSame($raw, $parser->raw_contents);
        $this->assertStringContainsString('<script>', $parser->raw_contents);
    }
}
