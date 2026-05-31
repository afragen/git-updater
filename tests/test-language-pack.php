<?php
/**
 * Tests for Language_Pack class.
 *
 * Covers 100% line coverage of src/Git_Updater/Language_Pack.php:
 * - __construct()
 * - run()
 * - update_site_transient()
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Language_Pack;
use Fragen\Git_Updater\API\Language_Pack_API;
use Fragen\Git_Updater\Base;

// ---------------------------------------------------------------------------
// Shared helper trait
// ---------------------------------------------------------------------------

trait Language_Pack_Helper {

	/**
	 * Build a minimal repo stdClass for Language_Pack / Language_Pack_API.
	 *
	 * @param string|null $languages_url URL for language pack or null.
	 * @param array<string, mixed> $overrides
	 * @return stdClass
	 */
	private function make_lp_repo( ?string $languages_url, array $overrides = [] ): stdClass {
		return (object) array_merge(
			[
				'slug'           => 'test-lp-repo',
				'name'           => 'Test LP Repo',
				'git'            => 'github',
				'type'           => 'plugin',
				'owner'          => 'test-owner',
				'branch'         => 'main',
				'primary_branch' => 'main',
				'enterprise'     => null,
				'enterprise_api' => null,
				'gist_id'        => null,
				'local_version'  => '1.0.0',
				'languages'      => $languages_url,
			],
			$overrides
		);
	}

	/**
	 * Build a plugin repo stdClass with language_packs set.
	 *
	 * @param string               $slug
	 * @param array<string, array<string, string>> $locales Keyed by locale name.
	 * @return stdClass
	 */
	private function make_plugin_repo_with_packs( string $slug, array $locales ): stdClass {
		$repo                 = new stdClass();
		$repo->slug           = $slug;
		$repo->type           = 'plugin';
		$repo->language_packs = new stdClass();
		foreach ( $locales as $locale => $data ) {
			$repo->language_packs->$locale = (object) $data;
		}
		return $repo;
	}

	/**
	 * Build a theme repo stdClass with language_packs set.
	 *
	 * @param string               $slug
	 * @param array<string, array<string, string>> $locales Keyed by locale name.
	 * @return stdClass
	 */
	private function make_theme_repo_with_packs( string $slug, array $locales ): stdClass {
		$repo                 = new stdClass();
		$repo->slug           = $slug;
		$repo->type           = 'theme';
		$repo->language_packs = new stdClass();
		foreach ( $locales as $locale => $data ) {
			$repo->language_packs->$locale = (object) $data;
		}
		return $repo;
	}

	/**
	 * Build a transient stdClass with a translations array.
	 *
	 * @param array<int, mixed> $translations
	 * @return stdClass
	 */
	private function make_transient( array $translations = [] ): stdClass {
		$t               = new stdClass();
		$t->translations = $translations;
		return $t;
	}
}

// ---------------------------------------------------------------------------
// Test_Language_Pack_Constructor_And_Run
// ---------------------------------------------------------------------------

/**
 * Class Test_Language_Pack_Constructor_And_Run
 *
 * Covers __construct() and run() in Language_Pack.
 */
class Test_Language_Pack_Constructor_And_Run extends GU_Test_Case {
	use Language_Pack_Helper;

	/** @var string */
	private string $slug = 'test-lp-cr-plugin';

	/** @var string */
	private string $cache_key;

	/** @var string */
	private string $error_cache_key;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->cache_key       = 'ghu-' . md5( $this->slug );
		$this->error_cache_key = 'ghu-' . md5( $this->slug . '_error' );
		delete_site_option( $this->cache_key );
		delete_site_option( $this->error_cache_key );
	}

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'site_transient_update_plugins' );
		remove_all_filters( 'site_transient_update_themes' );
		delete_site_option( $this->cache_key );
		delete_site_option( $this->error_cache_key );
		parent::tear_down();
	}

	// -----------------------------------------------------------------------
	// __construct tests
	// -----------------------------------------------------------------------

	/**
	 * Null languages triggers early return; $this->repo stays null.
	 */
	public function test_constructor_null_languages_leaves_repo_null(): void {
		$repo = $this->make_lp_repo( null, [ 'slug' => $this->slug ] );
		$api  = new Language_Pack_API( $repo );
		$lp   = new Language_Pack( $repo, $api );

		$ref = new ReflectionProperty( Language_Pack::class, 'repo' );
		$ref->setAccessible( true );
		$this->assertNull( $ref->getValue( $lp ) );
	}

	/**
	 * Non-null languages URL: both $repo and $repo_api are assigned.
	 */
	public function test_constructor_non_null_languages_assigns_properties(): void {
		$repo = $this->make_lp_repo(
			'https://github.com/test-owner/' . $this->slug,
			[ 'slug' => $this->slug ]
		);
		$api = new Language_Pack_API( $repo );
		$lp  = new Language_Pack( $repo, $api );

		$ref_repo = new ReflectionProperty( Language_Pack::class, 'repo' );
		$ref_repo->setAccessible( true );
		$ref_api = new ReflectionProperty( Language_Pack::class, 'repo_api' );
		$ref_api->setAccessible( true );

		$this->assertSame( $repo, $ref_repo->getValue( $lp ) );
		$this->assertSame( $api, $ref_api->getValue( $lp ) );
	}

	// -----------------------------------------------------------------------
	// run() tests
	// -----------------------------------------------------------------------

	/**
	 * run() early-returns when $this->repo is null (constructor skipped assignment).
	 */
	public function test_run_with_null_repo_returns_early_without_registering_filters(): void {
		$repo = $this->make_lp_repo( null, [ 'slug' => $this->slug ] );
		$api  = new Language_Pack_API( $repo );
		$lp   = new Language_Pack( $repo, $api );

		$lp->run();

		$this->assertFalse(
			has_filter( 'site_transient_update_plugins', [ Language_Pack::class, 'update_site_transient' ] )
		);
		$this->assertFalse(
			has_filter( 'site_transient_update_themes', [ Language_Pack::class, 'update_site_transient' ] )
		);
	}

	/**
	 * run() with valid repo: parse_header_uri and get_language_pack execute,
	 * and both site_transient filters are registered at priority 15.
	 * HTTP is short-circuited with a 404 (get_language_pack returns false
	 * but run() does not check the return value).
	 */
	public function test_run_with_valid_repo_registers_both_filters(): void {
		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [ 'code' => 404, 'message' => 'Not Found' ],
					'body'     => wp_json_encode( [ 'message' => 'Not Found' ] ),
					'headers'  => [],
					'cookies'  => [],
				];
			},
			10,
			3
		);

		$repo = $this->make_lp_repo(
			'https://github.com/test-owner/' . $this->slug,
			[ 'slug' => $this->slug ]
		);
		$api = new Language_Pack_API( $repo );
		$lp  = new Language_Pack( $repo, $api );

		$lp->run();

		$this->assertSame(
			15,
			has_filter( 'site_transient_update_plugins', [ Language_Pack::class, 'update_site_transient' ] )
		);
		$this->assertSame(
			15,
			has_filter( 'site_transient_update_themes', [ Language_Pack::class, 'update_site_transient' ] )
		);
	}
}

// ---------------------------------------------------------------------------
// Test_Language_Pack_Update_Site_Transient
// ---------------------------------------------------------------------------

/**
 * Class Test_Language_Pack_Update_Site_Transient
 *
 * Covers update_site_transient() — the static filter callback.
 * Every test drives the method via apply_filters() so current_filter()
 * returns the correct filter name inside the method.
 */
class Test_Language_Pack_Update_Site_Transient extends GU_Test_Case {
	use Language_Pack_Helper;

	public function set_up(): void {
		parent::set_up();
		new Base();

		// Force get_available_languages() to return ['en_US'] so $locales is predictable.
		add_filter( 'get_available_languages', fn() => [ 'en_US' ] );
	}

	public function tear_down(): void {
		remove_all_filters( 'get_available_languages' );
		remove_all_filters( 'locale' );
		parent::tear_down();
	}

	private function inject_plugin_config( array $config ): void {
		$this->set_plugin_config( $config );
	}

	private function inject_theme_config( array $config ): void {
		$this->set_theme_config( $config );
	}

	// -----------------------------------------------------------------------
	// Filter-driving helpers
	// -----------------------------------------------------------------------

	/**
	 * Call update_site_transient() with current_filter() = 'site_transient_update_plugins'.
	 * Uses $GLOBALS['wp_current_filter'] directly so only our code runs — no other
	 * site_transient_update_plugins callbacks fire.
	 */
	private function run_as_plugin_filter( stdClass $transient ): stdClass {
		global $wp_current_filter;
		$wp_current_filter[] = 'site_transient_update_plugins';
		$result              = Language_Pack::update_site_transient( $transient );
		array_pop( $wp_current_filter );
		return $result;
	}

	/**
	 * Call update_site_transient() with current_filter() = 'site_transient_update_themes'.
	 */
	private function run_as_theme_filter( stdClass $transient ): stdClass {
		global $wp_current_filter;
		$wp_current_filter[] = 'site_transient_update_themes';
		$result              = Language_Pack::update_site_transient( $transient );
		array_pop( $wp_current_filter );
		return $result;
	}

	// -----------------------------------------------------------------------
	// Tests
	// -----------------------------------------------------------------------

	/**
	 * Transient without a translations key triggers the early return.
	 * current_filter() is irrelevant — the early return fires before that check.
	 */
	public function test_early_return_when_no_translations_key(): void {
		$transient = new stdClass();
		// No ->translations property.

		$result = Language_Pack::update_site_transient( $transient );

		$this->assertSame( $transient, $result );
		$this->assertFalse( isset( $result->translations ) );
	}

	/**
	 * Plugin filter branch executes get_plugin_configs(); repo without language_packs
	 * is filtered out and no translations are appended.
	 * Covers lines 94-96, 103-107, 127.
	 */
	public function test_plugin_filter_with_repo_missing_language_packs(): void {
		$repo_no_packs = (object) [ 'slug' => 'no-packs-plugin', 'type' => 'plugin' ];
		$this->inject_plugin_config( [ 'no-packs-plugin' => $repo_no_packs ] );

		$transient = $this->make_transient();
		$result    = $this->run_as_plugin_filter( $transient );

		$this->assertIsObject( $result );
		$this->assertSame( [], $result->translations );
	}

	/**
	 * Theme filter branch executes get_theme_configs(); repo without language_packs
	 * is filtered out.
	 * Covers lines 98-100.
	 */
	public function test_theme_filter_with_repo_missing_language_packs(): void {
		$repo_no_packs = (object) [ 'slug' => 'no-packs-theme', 'type' => 'theme' ];
		$this->inject_theme_config( [ 'no-packs-theme' => $repo_no_packs ] );

		$transient = $this->make_transient();
		$result    = $this->run_as_theme_filter( $transient );

		$this->assertIsObject( $result );
		$this->assertSame( [], $result->translations );
	}

	/**
	 * A repo whose type does not appear in the filter name triggers the continue branch.
	 * Injecting a type='theme' repo into Plugin config means str_contains(
	 * 'site_transient_update_plugins', 'theme') = false → continue fires.
	 * Covers lines 111-112.
	 */
	public function test_str_contains_mismatch_continues_without_appending(): void {
		$repo = $this->make_plugin_repo_with_packs(
			'mismatched-theme-slug',
			[
				'en_US' => [
					'updated'  => '2030-01-01 00:00:00',
					'package'  => 'https://example.com/en_US.zip',
					'language' => 'en_US',
					'type'     => 'theme',
					'slug'     => 'mismatched-theme-slug',
					'version'  => '1.0.0',
				],
			]
		);
		$repo->type = 'theme'; // type won't appear in 'site_transient_update_plugins'

		$this->inject_plugin_config( [ 'mismatched-theme-slug' => $repo ] );

		$transient = $this->make_transient();
		$result    = $this->run_as_plugin_filter( $transient );

		$this->assertSame( [], $result->translations );
	}

	/**
	 * When language pack updated timestamp is in the future and no translation is
	 * installed, $lang_pack_mod > $translation_mod (0) → entry is appended.
	 * Covers lines 115-116 (locale exists), 120 (: 0 for translation_mod), 121-122.
	 */
	public function test_newer_language_pack_appended_to_translations(): void {
		$slug = 'newer-lp-plugin';
		$repo = $this->make_plugin_repo_with_packs(
			$slug,
			[
				'en_US' => [
					'updated'  => '2030-01-01 00:00:00',
					'package'  => 'https://example.com/en_US.zip',
					'language' => 'en_US',
					'type'     => 'plugin',
					'slug'     => $slug,
					'version'  => '1.0.0',
				],
			]
		);
		$this->inject_plugin_config( [ $slug => $repo ] );

		$transient = $this->make_transient();
		$result    = $this->run_as_plugin_filter( $transient );

		$this->assertCount( 1, $result->translations );
		$this->assertSame( 'en_US', $result->translations[0]['language'] );
	}

	/**
	 * When the locale is absent from language_packs, $lang_pack_mod = 0;
	 * with no installed translation $translation_mod = 0 too → 0 > 0 is false.
	 * Covers line 117 (: 0 branch for lang_pack_mod), line 121 (false branch).
	 */
	public function test_locale_absent_from_language_packs_does_not_append(): void {
		$slug         = 'no-locale-plugin';
		$repo         = new stdClass();
		$repo->slug   = $slug;
		$repo->type   = 'plugin';
		$repo->language_packs = new stdClass(); // empty — no locales present

		$this->inject_plugin_config( [ $slug => $repo ] );

		$transient = $this->make_transient();
		$result    = $this->run_as_plugin_filter( $transient );

		$this->assertSame( [], $result->translations );
	}

	/**
	 * When $lang_pack_mod equals $translation_mod (both 0), the pack is not appended.
	 * Use an epoch-adjacent updated time so strtotime returns 0 (or 1), keeping
	 * the comparison false against $translation_mod = 0.
	 * Covers line 121 (false branch) via the $lang_pack_mod path.
	 */
	public function test_epoch_lang_pack_updated_is_not_appended(): void {
		$slug = 'epoch-lp-plugin';
		$repo = $this->make_plugin_repo_with_packs(
			$slug,
			[
				'en_US' => [
					'updated'  => '1970-01-01 00:00:00+00:00', // strtotime → 0
					'package'  => 'https://example.com/en_US.zip',
					'language' => 'en_US',
					'type'     => 'plugin',
					'slug'     => $slug,
					'version'  => '1.0.0',
				],
			]
		);
		$this->inject_plugin_config( [ $slug => $repo ] );

		$transient = $this->make_transient();
		$result    = $this->run_as_plugin_filter( $transient );

		// strtotime('1970-01-01 00:00:00+00:00') = 0; 0 > 0 is false → not appended.
		$this->assertSame( [], $result->translations );
	}

	/**
	 * When wp_get_installed_translations() returns data for the slug+locale,
	 * $translation_mod is set from strtotime(PO-Revision-Date), covering line 119.
	 * Uses the pre-seeded 'internationalized-plugin' fixture (de_DE, updated 2020)
	 * so no .po file writing is needed.
	 * The language pack's updated time (2030) is newer than the installed (2020),
	 * so the entry is still appended.
	 */
	public function test_installed_translation_sets_translation_mod(): void {
		$slug   = 'internationalized-plugin';
		$locale = 'de_DE';

		// Override available languages so the loop iterates ['de_DE'].
		// (set_up already added ['en_US']; this callback wins as the last registered.)
		add_filter( 'get_available_languages', fn() => [ $locale ] );

		$repo = $this->make_plugin_repo_with_packs(
			$slug,
			[
				$locale => [
					'updated'  => '2030-01-01 00:00:00',
					'package'  => 'https://example.com/de_DE.zip',
					'language' => $locale,
					'type'     => 'plugin',
					'slug'     => $slug,
					'version'  => '1.0.0',
				],
			]
		);
		$this->inject_plugin_config( [ $slug => $repo ] );

		$transient = $this->make_transient();
		$result    = $this->run_as_plugin_filter( $transient );

		// lang_pack_mod (2030) > translation_mod (2020 from pre-seeded fixture) → appended.
		$this->assertCount( 1, $result->translations );
		$this->assertSame( $locale, $result->translations[0]['language'] );
	}

	/**
	 * Pre-seeding an identical entry in translations and then having the filter
	 * append the same entry again results in array_unique deduplicating to one.
	 * Covers line 127.
	 */
	public function test_duplicate_translation_entries_are_deduplicated(): void {
		$slug        = 'dedup-lp-plugin';
		$locale_data = [
			'updated'  => '2030-01-01 00:00:00',
			'package'  => 'https://example.com/en_US.zip',
			'language' => 'en_US',
			'type'     => 'plugin',
			'slug'     => $slug,
			'version'  => '1.0.0',
		];
		$repo = $this->make_plugin_repo_with_packs( $slug, [ 'en_US' => $locale_data ] );
		$this->inject_plugin_config( [ $slug => $repo ] );

		// Pre-seed the transient with an identical entry.
		$existing  = (array) $repo->language_packs->en_US;
		$transient = $this->make_transient( [ $existing ] );
		$result    = $this->run_as_plugin_filter( $transient );

		// Despite two identical entries (pre-seeded + appended), array_unique leaves one.
		$this->assertCount( 1, $result->translations );
	}

	/**
	 * Theme filter path: language pack is newer than installed translation (none
	 * installed), so the entry is appended.
	 * Covers lines 98-100 plus the full loop for themes.
	 */
	public function test_theme_filter_appends_translation_when_lang_pack_is_newer(): void {
		$slug = 'newer-lp-theme';
		$repo = $this->make_theme_repo_with_packs(
			$slug,
			[
				'en_US' => [
					'updated'  => '2030-01-01 00:00:00',
					'package'  => 'https://example.com/en_US.zip',
					'language' => 'en_US',
					'type'     => 'theme',
					'slug'     => $slug,
					'version'  => '1.0.0',
				],
			]
		);
		$this->inject_theme_config( [ $slug => $repo ] );

		$transient = $this->make_transient();
		$result    = $this->run_as_theme_filter( $transient );

		$this->assertCount( 1, $result->translations );
		$this->assertSame( 'en_US', $result->translations[0]['language'] );
	}
}
