<?php

declare(strict_types=1);

namespace Fragen\WP_Readme_Parser\Tests;

use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for body section parsing: description, installation, FAQ, screenshots,
 * upgrade notices, section aliases, other_notes merging, and word-limit trimming.
 *
 * @covers \Fragen\WP_Readme_Parser\Parser
 */
class SectionParsingTest extends ParserTestCase
{
    // -------------------------------------------------------------------------
    // Short description
    // -------------------------------------------------------------------------

    #[Test]
    public function it_parses_short_description(): void
    {
        $parser = $this->parseFixture('valid/standard.txt');
        $this->assertSame('A concise description of what this plugin does.', $parser->short_description);
    }

    #[Test]
    public function it_falls_back_to_description_section_for_short_description(): void
    {
        $readme = $this->makeReadme(body: "== Description ==\nThis is the description.");
        $parser = $this->parse($readme);

        $this->assertNotEmpty($parser->short_description);
        $this->assertArrayHasKey('no_short_description_present', $parser->warnings);
    }

    #[Test]
    public function it_truncates_short_description_to_150_chars(): void
    {
        $long   = str_repeat('word ', 50); // well over 150 chars
        $readme = $this->makeReadme(body: "{$long}\n\n== Description ==\nFull desc.");
        $parser = $this->parse($readme);

        $this->assertStringEndsWith('&hellip;', $parser->short_description);
        $this->assertArrayHasKey('trimmed_short_description', $parser->warnings);
    }

    // -------------------------------------------------------------------------
    // Standard named sections
    // -------------------------------------------------------------------------

    #[Test]
    public function it_parses_description_section(): void
    {
        $parser = $this->parseFixture('valid/standard.txt');
        $this->assertArrayHasKey('description', $parser->sections);
        $this->assertStringContainsString('REST API', $parser->sections['description']);
    }

    #[Test]
    public function it_parses_installation_section(): void
    {
        $parser = $this->parseFixture('valid/standard.txt');
        $this->assertArrayHasKey('installation', $parser->sections);
        $this->assertStringContainsString('wp-content/plugins', $parser->sections['installation']);
    }

    #[Test]
    public function it_parses_changelog_section(): void
    {
        $parser = $this->parseFixture('valid/standard.txt');
        $this->assertArrayHasKey('changelog', $parser->sections);
        $this->assertStringContainsString('1.2.3', $parser->sections['changelog']);
    }

    #[Test]
    public function it_uses_short_description_as_description_when_section_absent(): void
    {
        $parser = $this->parseFixture('valid/minimal.txt');
        $this->assertArrayHasKey('description', $parser->sections);
        $this->assertStringContainsString('short description', $parser->sections['description']);
    }

    #[Test]
    public function it_omits_empty_sections_from_output(): void
    {
        // Minimal readme has no installation, faq, screenshots, changelog sections.
        $parser = $this->parseFixture('valid/minimal.txt');
        $this->assertArrayNotHasKey('installation', $parser->sections);
        $this->assertArrayNotHasKey('screenshots', $parser->sections);
        $this->assertArrayNotHasKey('changelog', $parser->sections);
    }

    // -------------------------------------------------------------------------
    // Markdown-style headings
    // -------------------------------------------------------------------------

    #[Test]
    public function it_accepts_markdown_h2_section_headings(): void
    {
        $parser = $this->parseFixture('edge-cases/markdown-headings.txt');
        $this->assertArrayHasKey('description', $parser->sections);
        $this->assertStringContainsString('Markdown headings', $parser->sections['description']);
    }

    #[Test]
    public function it_does_not_treat_h3_as_a_new_section(): void
    {
        $readme = $this->makeReadme(body: "Desc.\n\n== Description ==\n### Sub\nSub content.");
        $parser = $this->parse($readme);
        // ### should remain inside the description, not spawn a new section.
        $this->assertStringContainsString('Sub content', $parser->sections['description']);
        $this->assertArrayNotHasKey('sub', $parser->sections);
    }

    // -------------------------------------------------------------------------
    // Section aliases
    // -------------------------------------------------------------------------

    #[Test]
    public function it_resolves_frequently_asked_questions_alias(): void
    {
        $parser = $this->parseFixture('edge-cases/section-aliases.txt');
        // The alias should resolve so the FAQ data is populated.
        $this->assertNotEmpty($parser->faq);
    }

    #[Test]
    public function it_resolves_change_log_alias(): void
    {
        $parser = $this->parseFixture('edge-cases/section-aliases.txt');
        $this->assertArrayHasKey('changelog', $parser->sections);
        $this->assertStringContainsString('Initial release', $parser->sections['changelog']);
    }

    #[Test]
    public function it_resolves_screenshot_alias(): void
    {
        $readme = $this->makeReadme(
            body: "Desc.\n\n== Screenshot ==\n1. The main view.",
        );
        $parser = $this->parse($readme);
        // 'screenshot' is an alias for 'screenshots' → captured into $screenshots array.
        $this->assertNotEmpty($parser->screenshots);
    }

    // -------------------------------------------------------------------------
    // other_notes
    // -------------------------------------------------------------------------

    #[Test]
    public function it_merges_other_notes_into_description(): void
    {
        $parser = $this->parseFixture('edge-cases/other-notes.txt');
        $this->assertStringContainsString('Main description content', $parser->sections['description']);
        // The custom-section heading is rendered as an <h3> inside description.
        $this->assertStringContainsString('<h3>', $parser->sections['description']);
    }

    #[Test]
    public function it_does_not_expose_other_notes_as_a_standalone_section(): void
    {
        $parser = $this->parseFixture('edge-cases/other-notes.txt');
        $this->assertArrayNotHasKey('other_notes', $parser->sections);
    }

    // -------------------------------------------------------------------------
    // FAQ
    // -------------------------------------------------------------------------

    #[Test]
    public function it_parses_faq_into_associative_array(): void
    {
        $parser = $this->parseFixture('valid/standard.txt');
        $this->assertArrayHasKey('Does it work with multisite?', $parser->faq);
    }

    #[Test]
    public function it_renders_faq_as_dl_in_sections(): void
    {
        $parser = $this->parseFixture('valid/standard.txt');
        $this->assertStringContainsString('<dl>', $parser->sections['faq']);
        $this->assertStringContainsString('<dt', $parser->sections['faq']);
        $this->assertStringContainsString('<dd>', $parser->sections['faq']);
    }

    #[Test]
    public function it_slugifies_faq_question_as_dt_id(): void
    {
        $parser = $this->parseFixture('valid/standard.txt');
        // "does it work with multisite?" → rawurlencode → "does%20it%20work%20with%20multisite%3F"
        $this->assertStringContainsString("id='does%20it%20work%20with%20multisite%3F'", $parser->sections['faq']);
    }

    #[Test]
    public function it_parses_bold_style_faq_headings(): void
    {
        $parser = $this->parseFixture('edge-cases/bold-faq.txt');
        $this->assertArrayHasKey('Does this use bold headings?', $parser->faq);
        $this->assertArrayHasKey('What about multiple questions?', $parser->faq);
    }

    #[Test]
    public function it_captures_bold_faq_answer_content(): void
    {
        $parser = $this->parseFixture('edge-cases/bold-faq.txt');
        $this->assertStringContainsString(
            'double asterisks',
            $parser->faq['Does this use bold headings?'],
        );
    }

    // -------------------------------------------------------------------------
    // Screenshots
    // -------------------------------------------------------------------------

    #[Test]
    public function it_parses_screenshots_into_indexed_array(): void
    {
        $parser = $this->parseFixture('valid/standard.txt');
        $this->assertArrayHasKey(1, $parser->screenshots);
        $this->assertArrayHasKey(2, $parser->screenshots);
    }

    #[Test]
    public function it_starts_screenshot_index_at_one(): void
    {
        $parser = $this->parseFixture('valid/standard.txt');
        $this->assertArrayNotHasKey(0, $parser->screenshots);
        $this->assertStringContainsString('settings page', $parser->screenshots[1]);
    }

    #[Test]
    public function it_removes_screenshots_from_sections_after_parsing(): void
    {
        $parser = $this->parseFixture('valid/standard.txt');
        $this->assertArrayNotHasKey('screenshots', $parser->sections);
    }

    // -------------------------------------------------------------------------
    // Upgrade notice
    // -------------------------------------------------------------------------

    #[Test]
    public function it_parses_upgrade_notice_into_versioned_array(): void
    {
        $parser = $this->parseFixture('valid/standard.txt');
        $this->assertArrayHasKey('1.2.3', $parser->upgrade_notice);
    }

    #[Test]
    public function it_removes_upgrade_notice_from_sections_after_parsing(): void
    {
        $parser = $this->parseFixture('valid/standard.txt');
        $this->assertArrayNotHasKey('upgrade_notice', $parser->sections);
    }

    // -------------------------------------------------------------------------
    // Word-limit trimming
    // -------------------------------------------------------------------------

    #[Test]
    public function it_trims_description_section_at_2500_words_and_sets_warning(): void
    {
        $parser = $this->parseFixture('edge-cases/long-changelog.txt');
        // The long-changelog fixture has a changelog that exceeds 5000 words.
        $this->assertArrayHasKey('trimmed_section_changelog', $parser->warnings);
    }

    #[Test]
    public function it_appends_hellip_when_section_is_trimmed(): void
    {
        $parser = $this->parseFixture('edge-cases/long-changelog.txt');
        $this->assertStringContainsString('&hellip;', $parser->sections['changelog']);
    }

    #[Test]
    public function it_does_not_warn_when_section_is_within_limit(): void
    {
        $parser = $this->parseFixture('valid/standard.txt');
        $this->assertArrayNotHasKey('trimmed_section_description', $parser->warnings);
        $this->assertArrayNotHasKey('trimmed_section_changelog', $parser->warnings);
    }
}
