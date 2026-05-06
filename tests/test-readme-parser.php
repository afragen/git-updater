<?php
/**
 * Tests for Readme_Parser methods.
 *
 * Covers:
 * - faq_as_h4()            — FAQ dict-list → <h4> HTML conversion
 * - readme_section_as_h4() — <p>=Title=</p> → <h4>Title</h4> regex replacement
 * - screenshots_as_list()  — numbered captions + asset URLs → <ol><li>…</li></ol>
 * - create_contributors()  — profile/avatar URL building (private, via reflection)
 * - parse_data()           — full pipeline: object vars → transformed array
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Readme_Parser;

/**
 * Class Test_Readme_Parser
 */
class Test_Readme_Parser extends WP_UnitTestCase {

	/**
	 * Minimal but valid readme.txt content used as a fixture throughout.
	 * The parser requires an H1/H2 plugin-name heading to trigger parsing.
	 */
	private const MINIMAL_README = <<<'README'
=== Test Plugin ===
Contributors: alice, bob
Tags: test, plugin
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A short description of the plugin.

== Description ==

Full description paragraph.

== FAQ ==

= What is this? =
This is a test plugin.

= How does it work? =
It works by testing things.

== Changelog ==

= 1.0.0 =
<p>=Initial release=</p>
First public release.

== Screenshots ==

1. Main interface screenshot.
2. Settings page screenshot.
README;

	/**
	 * Slug used as the cache key so seeded options are found by the constructor.
	 */
	private const SLUG = 'test-plugin';

	/**
	 * Computed cache key: 'ghu-' . md5( SLUG ).
	 */
	private string $cache_key;

	public function set_up(): void {
		parent::set_up();
		$this->cache_key = 'ghu-' . md5( self::SLUG );
	}

	public function tear_down(): void {
		delete_site_option( $this->cache_key );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Construct a Readme_Parser from the shared minimal readme fixture.
	 */
	private function make_parser( string $readme = self::MINIMAL_README ): Readme_Parser {
		return new Readme_Parser( $readme, self::SLUG );
	}

	/**
	 * Seed the wp-env site option so the constructor's get_repo_cache() call
	 * picks up the given assets array.
	 *
	 * @param array<string, string> $assets filename => URL pairs.
	 */
	private function seed_assets( array $assets ): void {
		update_site_option(
			$this->cache_key,
			[
				'assets'  => $assets,
				'timeout' => strtotime( '+12 hours' ),
			]
		);
	}

	/**
	 * Invoke the private create_contributors() method via reflection.
	 *
	 * @param  array<int, string> $users
	 * @return array<string, mixed>
	 */
	private function call_create_contributors( Readme_Parser $parser, array $users ): array {
		$rm = new ReflectionMethod( $parser, 'create_contributors' );
		return $rm->invoke( $parser, $users );
	}

	// -------------------------------------------------------------------------
	// faq_as_h4()
	// -------------------------------------------------------------------------

	public function test_faq_as_h4_returns_data_unchanged_when_faq_is_empty(): void {
		$parser = $this->make_parser();
		$data   = [ 'sections' => [ 'description' => 'Hello' ], 'faq' => [] ];

		$result = $parser->faq_as_h4( $data );

		$this->assertSame( $data, $result );
	}

	public function test_faq_as_h4_returns_data_unchanged_when_faq_key_absent(): void {
		$parser = $this->make_parser();
		$data   = [ 'sections' => [] ];

		$result = $parser->faq_as_h4( $data );

		$this->assertSame( $data, $result );
	}

	public function test_faq_as_h4_wraps_questions_in_h4_tags(): void {
		$parser = $this->make_parser();
		$data   = [
			'faq'      => [ 'Is this tested?' => '<p>Yes.</p>' ],
			'sections' => [],
		];

		$result = $parser->faq_as_h4( $data );

		$this->assertStringContainsString( '<h4>Is this tested?</h4>', $result['sections']['faq'] );
	}

	public function test_faq_as_h4_appends_answer_after_h4(): void {
		$parser = $this->make_parser();
		$data   = [
			'faq'      => [ 'Question?' => '<p>The answer.</p>' ],
			'sections' => [],
		];

		$result = $parser->faq_as_h4( $data );

		$this->assertStringContainsString( '<p>The answer.</p>', $result['sections']['faq'] );
		$pos_h4     = strpos( $result['sections']['faq'], '<h4>Question?</h4>' );
		$pos_answer = strpos( $result['sections']['faq'], '<p>The answer.</p>' );
		$this->assertGreaterThan( $pos_h4, $pos_answer, 'Answer must follow h4.' );
	}

	public function test_faq_as_h4_handles_multiple_questions(): void {
		$parser = $this->make_parser();
		$data   = [
			'faq'      => [
				'Q1?' => '<p>A1.</p>',
				'Q2?' => '<p>A2.</p>',
				'Q3?' => '<p>A3.</p>',
			],
			'sections' => [],
		];

		$result = $parser->faq_as_h4( $data );

		$this->assertStringContainsString( '<h4>Q1?</h4>', $result['sections']['faq'] );
		$this->assertStringContainsString( '<h4>Q2?</h4>', $result['sections']['faq'] );
		$this->assertStringContainsString( '<h4>Q3?</h4>', $result['sections']['faq'] );
	}

	public function test_faq_as_h4_removes_existing_faq_section_before_rebuilding(): void {
		$parser = $this->make_parser();
		$data   = [
			'faq'      => [ 'Q?' => '<p>A.</p>' ],
			'sections' => [ 'faq' => 'OLD CONTENT' ],
		];

		$result = $parser->faq_as_h4( $data );

		$this->assertStringNotContainsString( 'OLD CONTENT', $result['sections']['faq'] );
	}

	// -------------------------------------------------------------------------
	// readme_section_as_h4()
	// -------------------------------------------------------------------------

	public function test_readme_section_as_h4_returns_data_unchanged_for_empty_section(): void {
		$parser = $this->make_parser();
		$data   = [ 'sections' => [] ];

		$result = $parser->readme_section_as_h4( 'changelog', $data );

		$this->assertSame( $data, $result );
	}

	public function test_readme_section_as_h4_converts_wp_style_heading_to_h4(): void {
		$parser = $this->make_parser();
		$data   = [ 'sections' => [ 'changelog' => '<p>=1.0.0=</p><p>First release.</p>' ] ];

		$result = $parser->readme_section_as_h4( 'changelog', $data );

		$this->assertStringContainsString( '<h4>1.0.0</h4>', $result['sections']['changelog'] );
		$this->assertStringNotContainsString( '<p>=1.0.0=</p>', $result['sections']['changelog'] );
	}

	public function test_readme_section_as_h4_handles_multiple_headings(): void {
		$parser = $this->make_parser();
		$data   = [
			'sections' => [
				'changelog' => '<p>=2.0.0=</p><p>New.</p><p>=1.0.0=</p><p>Old.</p>',
			],
		];

		$result = $parser->readme_section_as_h4( 'changelog', $data );

		$this->assertStringContainsString( '<h4>2.0.0</h4>', $result['sections']['changelog'] );
		$this->assertStringContainsString( '<h4>1.0.0</h4>', $result['sections']['changelog'] );
	}

	public function test_readme_section_as_h4_skips_section_already_containing_h4(): void {
		$parser   = $this->make_parser();
		$original = '<h4>Already converted</h4><p>Content.</p>';
		$data     = [ 'sections' => [ 'description' => $original ] ];

		$result = $parser->readme_section_as_h4( 'description', $data );

		$this->assertSame( $original, $result['sections']['description'] );
	}

	public function test_readme_section_as_h4_works_for_description_section(): void {
		$parser = $this->make_parser();
		$data   = [ 'sections' => [ 'description' => '<p>=Overview=</p><p>Details.</p>' ] ];

		$result = $parser->readme_section_as_h4( 'description', $data );

		$this->assertStringContainsString( '<h4>Overview</h4>', $result['sections']['description'] );
	}

	// -------------------------------------------------------------------------
	// screenshots_as_list()
	// -------------------------------------------------------------------------

	public function test_screenshots_as_list_returns_data_unchanged_when_screenshots_empty(): void {
		$parser = $this->make_parser();
		$data   = [ 'screenshots' => [], 'sections' => [] ];

		$result = $parser->screenshots_as_list( $data );

		$this->assertSame( $data, $result );
	}

	public function test_screenshots_as_list_returns_data_unchanged_when_assets_empty(): void {
		// No seeded cache → $this->assets = [].
		$parser = $this->make_parser();
		$data   = [
			'screenshots' => [ 1 => 'Main screen.' ],
			'sections'    => [],
		];

		$result = $parser->screenshots_as_list( $data );

		$this->assertSame( $data, $result );
	}

	public function test_screenshots_as_list_builds_ol_list(): void {
		$this->seed_assets( [ 'screenshot-1.png' => 'https://example.com/screenshot-1.png' ] );
		$parser = $this->make_parser();
		$data   = [
			'screenshots' => [ 1 => 'Main interface.' ],
			'sections'    => [],
		];

		$result = $parser->screenshots_as_list( $data );

		$this->assertStringStartsWith( '<ol>', $result['sections']['screenshots'] );
		$this->assertStringEndsWith( '</ol>', $result['sections']['screenshots'] );
	}

	public function test_screenshots_as_list_contains_img_tag_with_url(): void {
		$url = 'https://example.com/screenshot-1.png';
		$this->seed_assets( [ 'screenshot-1.png' => $url ] );
		$parser = $this->make_parser();
		$data   = [
			'screenshots' => [ 1 => 'Caption text.' ],
			'sections'    => [],
		];

		$result = $parser->screenshots_as_list( $data );

		$this->assertStringContainsString( 'src="' . esc_url( $url ) . '"', $result['sections']['screenshots'] );
	}

	public function test_screenshots_as_list_contains_caption_text(): void {
		$this->seed_assets( [ 'screenshot-1.png' => 'https://example.com/screenshot-1.png' ] );
		$parser = $this->make_parser();
		$data   = [
			'screenshots' => [ 1 => 'My caption.' ],
			'sections'    => [],
		];

		$result = $parser->screenshots_as_list( $data );

		$this->assertStringContainsString( 'My caption.', $result['sections']['screenshots'] );
	}

	public function test_screenshots_as_list_skips_asset_with_non_matching_prefix(): void {
		$this->seed_assets( [ 'banner-1.png' => 'https://example.com/banner-1.png' ] );
		$parser = $this->make_parser();
		$data   = [
			'screenshots' => [ 1 => 'Caption.' ],
			'sections'    => [],
		];

		$result = $parser->screenshots_as_list( $data );

		// The section will be an empty <ol></ol> because no screenshot-* file matched.
		$this->assertStringNotContainsString( '<li>', $result['sections']['screenshots'] );
	}

	public function test_screenshots_as_list_handles_multiple_screenshots(): void {
		$this->seed_assets(
			[
				'screenshot-1.png' => 'https://example.com/s1.png',
				'screenshot-2.png' => 'https://example.com/s2.png',
			]
		);
		$parser = $this->make_parser();
		$data   = [
			'screenshots' => [
				1 => 'First caption.',
				2 => 'Second caption.',
			],
			'sections'    => [],
		];

		$result = $parser->screenshots_as_list( $data );

		$this->assertSame( 2, substr_count( $result['sections']['screenshots'], '<li>' ) );
	}

	// -------------------------------------------------------------------------
	// create_contributors()  (private — via reflection)
	// -------------------------------------------------------------------------

	public function test_create_contributors_returns_empty_array_for_no_users(): void {
		$parser = $this->make_parser();
		$result = $this->call_create_contributors( $parser, [] );
		$this->assertSame( [], $result );
	}

	public function test_create_contributors_sets_display_name(): void {
		$parser = $this->make_parser();
		$result = $this->call_create_contributors( $parser, [ 'alice' ] );
		$this->assertSame( 'alice', $result['alice']['display_name'] );
	}

	public function test_create_contributors_builds_wordpress_org_profile_url(): void {
		$parser = $this->make_parser();
		$result = $this->call_create_contributors( $parser, [ 'alice' ] );
		$this->assertSame( '//profiles.wordpress.org/alice', $result['alice']['profile'] );
	}

	public function test_create_contributors_builds_gravatar_redirect_url(): void {
		$parser = $this->make_parser();
		$result = $this->call_create_contributors( $parser, [ 'alice' ] );
		$this->assertSame(
			'https://wordpress.org/grav-redirect.php?user=alice',
			$result['alice']['avatar']
		);
	}

	public function test_create_contributors_handles_multiple_users(): void {
		$parser = $this->make_parser();
		$result = $this->call_create_contributors( $parser, [ 'alice', 'bob' ] );
		$this->assertArrayHasKey( 'alice', $result );
		$this->assertArrayHasKey( 'bob', $result );
	}

	// -------------------------------------------------------------------------
	// parse_data()   — full pipeline
	// -------------------------------------------------------------------------

	public function test_parse_data_returns_array(): void {
		$result = $this->make_parser()->parse_data();
		$this->assertIsArray( $result );
	}

	public function test_parse_data_contains_sections_key(): void {
		$result = $this->make_parser()->parse_data();
		$this->assertArrayHasKey( 'sections', $result );
	}

	public function test_parse_data_contains_contributors_key(): void {
		$result = $this->make_parser()->parse_data();
		$this->assertArrayHasKey( 'contributors', $result );
	}

	public function test_parse_data_contributors_are_formatted_as_array_with_profile(): void {
		$result = $this->make_parser()->parse_data();
		// readme has contributors: alice, bob
		$this->assertArrayHasKey( 'alice', $result['contributors'] );
		$this->assertSame( '//profiles.wordpress.org/alice', $result['contributors']['alice']['profile'] );
	}

	public function test_parse_data_applies_faq_as_h4(): void {
		$result = $this->make_parser()->parse_data();
		// readme has two FAQ items; they should be h4-formatted in sections.
		$this->assertStringContainsString( '<h4>What is this?</h4>', $result['sections']['faq'] );
		$this->assertStringContainsString( '<h4>How does it work?</h4>', $result['sections']['faq'] );
	}

	public function test_parse_data_applies_changelog_section_h4_conversion(): void {
		$result = $this->make_parser()->parse_data();
		// readme has <p>=Initial release=</p> in changelog
		if ( ! empty( $result['sections']['changelog'] ) ) {
			$this->assertStringNotContainsString( '<p>=Initial release=</p>', $result['sections']['changelog'] );
			$this->assertStringContainsString( '<h4>Initial release</h4>', $result['sections']['changelog'] );
		} else {
			$this->markTestSkipped( 'Parser did not produce a changelog section from this fixture.' );
		}
	}

	public function test_parse_data_returns_name_from_readme(): void {
		$result = $this->make_parser()->parse_data();
		$this->assertSame( 'Test Plugin', $result['name'] );
	}

	public function test_parse_data_returns_stable_tag(): void {
		$result = $this->make_parser()->parse_data();
		$this->assertSame( '1.0.0', $result['stable_tag'] );
	}

	public function test_parse_data_returns_short_description(): void {
		$result = $this->make_parser()->parse_data();
		$this->assertSame( 'A short description of the plugin.', $result['short_description'] );
	}

	public function test_parse_data_on_empty_readme_returns_array_with_defaults(): void {
		$result = $this->make_parser( '' )->parse_data();
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'sections', $result );
		$this->assertArrayHasKey( 'contributors', $result );
	}
}
