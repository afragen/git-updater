<?php

declare(strict_types=1);

namespace Fragen\WP_Readme_Parser\Tests;

use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for plugin name extraction and the header block (Contributors, Tags, etc.).
 *
 * @covers \Fragen\WP_Readme_Parser\Parser
 */
class NameAndHeaderParsingTest extends ParserTestCase
{
    // -------------------------------------------------------------------------
    // Plugin name
    // -------------------------------------------------------------------------

    #[Test]
    public function it_parses_plugin_name(): void
    {
        $parser = $this->parseFixture('valid/standard.txt');
        $this->assertSame('My Awesome Plugin', $parser->name);
    }

    #[Test]
    public function it_trims_hash_and_equals_from_name(): void
    {
        $parser = $this->parse("=== My Plugin ===\nLicense: MIT\n\nDesc.");
        $this->assertSame('My Plugin', $parser->name);
    }

    #[Test]
    public function it_sets_warning_when_first_line_is_a_header_field(): void
    {
        // No title line — starts with a valid header immediately.
        $parser = $this->parse("License: MIT\nTags: foo\n\nDesc.");
        $this->assertFalse($parser->name);
        $this->assertArrayHasKey('invalid_plugin_name_header', $parser->warnings);
    }

    #[Test]
    public function it_handles_literal_plugin_name_placeholder(): void
    {
        $readme = "=== Plugin Name ===\nMy Real Plugin\nLicense: MIT\n\nDescription.";
        $parser = $this->parse($readme);
        $this->assertSame('My Real Plugin', $parser->name);
        $this->assertArrayHasKey('invalid_plugin_name_header', $parser->warnings);
    }

    #[Test]
    public function it_skips_github_style_underline_after_title(): void
    {
        $readme = "=== My Plugin ===\n==================\nLicense: MIT\n\nDesc.";
        $parser = $this->parse($readme);
        $this->assertSame('My Plugin', $parser->name);
    }

    // -------------------------------------------------------------------------
    // Standard headers
    // -------------------------------------------------------------------------

    #[Test]
    public function it_parses_all_standard_headers(): void
    {
        $parser = $this->parseFixture('valid/standard.txt');

        $this->assertSame('6.0', $parser->requires);
        $this->assertSame('6.5', $parser->tested);
        $this->assertSame('8.1', $parser->requires_php);
        $this->assertSame('1.2.3', $parser->stable_tag);
        $this->assertSame('GPLv2 or later', $parser->license);
        $this->assertSame('https://www.gnu.org/licenses/gpl-2.0.html', $parser->license_uri);
        $this->assertSame('https://example.com/donate', $parser->donate_link);
    }

    #[Test]
    public function it_accepts_tested_up_to_as_alias_for_tested(): void
    {
        $parser = $this->parse($this->makeReadme(['Tested up to' => '6.4']));
        $this->assertSame('6.4', $parser->tested);
    }

    #[Test]
    public function it_accepts_requires_at_least_as_alias_for_requires(): void
    {
        $parser = $this->parse($this->makeReadme(['Requires at least' => '6.1']));
        $this->assertSame('6.1', $parser->requires);
    }

    #[Test]
    public function it_accepts_requires_php_header(): void
    {
        $parser = $this->parse($this->makeReadme(['Requires PHP' => '8.2']));
        $this->assertSame('8.2', $parser->requires_php);
    }

    #[Test]
    public function it_parses_donate_link(): void
    {
        $parser = $this->parseFixture('valid/standard.txt');
        $this->assertSame('https://example.com/donate', $parser->donate_link);
    }

    #[Test]
    public function it_leaves_donate_link_empty_when_absent(): void
    {
        $parser = $this->parse($this->makeReadme());
        $this->assertSame('', $parser->donate_link);
    }

    #[Test]
    public function it_rejects_javascript_donate_link(): void
    {
        $parser = $this->parse($this->makeReadme(['Donate link' => 'javascript:alert(1)']));
        $this->assertSame('', $parser->donate_link);
    }

    #[Test]
    public function it_rejects_data_uri_donate_link(): void
    {
        $parser = $this->parse($this->makeReadme(['Donate link' => 'data:text/html,<script>alert(1)</script>']));
        $this->assertSame('', $parser->donate_link);
    }

    #[Test]
    public function it_tolerates_blank_lines_within_the_header_block(): void
    {
        $readme = "=== P ===\nLicense: MIT\n\nRequires at least: 6.0\n\nDesc.";
        $parser = $this->parse($readme);
        // Both headers must be captured despite the blank line between them.
        $this->assertSame('6.0', $parser->requires);
    }

    // -------------------------------------------------------------------------
    // Contributors
    // -------------------------------------------------------------------------

    #[Test]
    public function it_parses_contributors(): void
    {
        $parser = $this->parseFixture('valid/standard.txt');
        $this->assertSame(['jsmith', 'jane-doe'], $parser->contributors);
    }

    #[Test]
    public function it_strips_leading_at_from_contributor_slugs(): void
    {
        $parser = $this->parse($this->makeReadme(['Contributors' => '@alice, @bob']));
        $this->assertSame(['alice', 'bob'], $parser->contributors);
    }

    #[Test]
    public function it_normalises_contributor_slugs_to_lowercase(): void
    {
        $parser = $this->parse($this->makeReadme(['Contributors' => 'Alice, Bob']));
        $this->assertSame(['alice', 'bob'], $parser->contributors);
    }

    #[Test]
    public function it_warns_and_drops_contributors_with_invalid_slugs(): void
    {
        // Spaces and parentheses are not valid in a WP.org nicename.
        $parser = $this->parse($this->makeReadme(['Contributors' => 'valid-user, Joe Bloggs (AU)']));
        $this->assertSame(['valid-user'], $parser->contributors);
        $this->assertArrayHasKey('contributor_ignored', $parser->warnings);
        $this->assertContains('Joe Bloggs (AU)', $parser->warnings['contributor_ignored']);
    }

    // -------------------------------------------------------------------------
    // Tags
    // -------------------------------------------------------------------------

    #[Test]
    public function it_parses_tags(): void
    {
        $parser = $this->parseFixture('valid/standard.txt');
        $this->assertSame(['widgets', 'api', 'rest'], $parser->tags);
    }

    #[Test]
    public function it_removes_ignored_tags(): void
    {
        $parser = $this->parseFixture('edge-cases/too-many-tags.txt');
        $this->assertNotContains('plugin', $parser->tags);
        $this->assertNotContains('wordpress', $parser->tags);
        $this->assertArrayHasKey('ignored_tags', $parser->warnings);
        $this->assertContains('plugin', $parser->warnings['ignored_tags']);
        $this->assertContains('wordpress', $parser->warnings['ignored_tags']);
    }

    #[Test]
    public function it_enforces_five_tag_limit(): void
    {
        $parser = $this->parseFixture('edge-cases/too-many-tags.txt');
        $this->assertCount(5, $parser->tags);
        $this->assertArrayHasKey('too_many_tags', $parser->warnings);
    }

    #[Test]
    public function it_records_the_dropped_tags_in_the_warning(): void
    {
        // 7 tags → 5 kept, 2 in the warning (minus the 2 ignored ones first).
        $parser = $this->parseFixture('edge-cases/too-many-tags.txt');
        $this->assertNotEmpty($parser->warnings['too_many_tags']);
    }
}
