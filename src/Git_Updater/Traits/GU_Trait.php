<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  GPL-3.0-or-later
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 *
 * @phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedIf
 */

namespace Fragen\Git_Updater\Traits;

use Fragen\Singleton;
use Fragen\Git_Updater\Base;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;
use stdClass;
use WP_Error;

/**
 * Trait GU_Trait
 */
trait GU_Trait {

	/**
	 * Holds the Base class instance.
	 *
	 * @var Base
	 */
	protected $base;

	/**
	 * Holds the repo type object.
	 *
	 * @var stdClass
	 */
	public $type;

	/**
	 * Cache keys that must all be present in a repo's 'ran' list for the
	 * background fetch cycle to be considered complete.
	 *
	 * Declared as a method (not a trait constant) because PHPStan flags
	 * `final` constants inside traits as PHP 8.2-only (classConstant.inTrait /
	 * classConstant.finalNotSupported), and this plugin supports PHP 8.0.
	 *
	 * @return array<int, string>
	 */
	final protected static function expected_ran_steps(): array {
		return [ 'contents', 'assets', 'readme', 'changes', 'tags', 'branches', 'meta' ];
	}

	/**
	 * Holds the plugin basename.
	 *
	 * @return string
	 */
	final public function gu_plugin_name(): string {
		return is_plugin_active( 'git-updater/git-updater.php' ) ? 'git-updater/git-updater.php' : 'git-updater-f27e06/git-updater.php';
	}

	/**
	 * Checks to see if a heartbeat is resulting in activity.
	 *
	 * @return bool
	 */
	final public static function is_heartbeat() {
		if ( isset( $_POST['action'], $_POST['_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_nonce'] ) ), 'heartbeat-nonce' ) ) {
			return 'heartbeat' === $_POST['action'];
		}
			return false;
	}

	/**
	 * Checks to see if WP_CLI.
	 *
	 * @return bool
	 */
	final public static function is_wp_cli() {
		return defined( 'WP_CLI' ) && \WP_CLI;
	}

	/**
	 * Load site options.
	 *
	 * @return void
	 */
	final public function load_options() {
		Singleton::get_instance( 'Fragen\Git_Updater\GU_Upgrade', $this )->convert_ghu_options_to_gu_options();
		$base           = Singleton::get_instance( 'Fragen\Git_Updater\Base', $this );
		$base::$options = get_site_option( 'git_updater', [] );
		$base::$options = $this->modify_options( $base::$options );
	}

	/**
	 * Check current page.
	 *
	 * @param  string[] $pages Array of pages.
	 * @return bool
	 */
	final public function is_current_page( array $pages ) {
		global $pagenow;

		return in_array( $pagenow, $pages, true );
	}

	/**
	 * Check if we should run on the current page.
	 *
	 * @return bool
	 */
	final public static function should_run_on_current_page(): bool {
		global $pagenow;

		$pages            = [ 'update-core.php', 'update.php', 'plugins.php', 'themes.php' ];
		$view_details     = [ 'plugin-install.php', 'theme-install.php' ];
		$autoupdate_pages = [ 'admin-ajax.php', 'index.php', 'wp-cron.php' ];
		$settings_pages   = is_multisite() ? [ 'settings.php', 'edit.php' ] : [ 'options.php', 'options-general.php' ];

		if ( ! in_array( $pagenow, array_merge( $pages, $view_details, $autoupdate_pages, $settings_pages ), true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get cache key (slug normalizer).
	 *
	 * The repository cache now lives in a dedicated table indexed on `slug`, so
	 * the `ghu-` + md5 prefix is no longer used. This returns the repo slug.
	 *
	 * @param  string|bool $repo Repo name or false.
	 *
	 * @return string
	 */
	final public function get_cache_key( $repo = false ) {
		if ( ! $repo ) {
			$repo = $this->type->slug ?? 'ghu';
		}
		return (string) $repo;
	}

	/**
	 * Returns repo cached data.
	 *
	 * @access protected
	 *
	 * @param string|bool               $repo Repo name or false.
	 * @param bool                      $timeout false to always return cache, true to use timeout.
	 * @param array<string>|string|null $column  Columns to project. null = full row.
	 *
	 * @return array<string, mixed>|mixed|false The repo cache (full or partial row),
	 *                                          or a single unserialized value, or false
	 *                                          if expired/missing.
	 */
	final public function get_repo_cache( $repo = false, $timeout = true, $column = null ) {
		$slug  = $this->get_cache_key( $repo );
		$table = \Fragen\Git_Updater\DB\Repo_Cache_Table::instance();

		if ( null !== $column ) {
			// A projected read has no timeout of its own; honor the row timeout
			// unless the caller opted out. The timeout is surfaced in object cache
			// so a warm entry avoids a DB query for the freshness gate.
			if ( $timeout ) {
				$row_timeout = $table->get_repo_timeout( $slug );
				if ( ! $this->is_cache_timeout_valid( $row_timeout ) ) {
					return false;
				}
			}
			$value = $table->get_repo( $slug, $column );
			if ( null === $value ) {
				return false;
			}

			return $value;
		}

		$row = $table->get_repo( $slug );

		if ( null === $row ) {
			return false;
		}

		if ( $timeout && ! $this->is_cache_timeout_valid( (int) ( $row['timeout'] ?? 0 ) ) ) {
			return false;
		}

		// Expose the row timeout for callers that gate on it.
		$row['timeout'] = (int) ( $row['timeout'] ?? 0 );

		return $row;
	}

	/**
	 * Sets repo data for cache in the cache table.
	 *
	 * @access protected
	 *
	 * @param string      $id       Column name / data identifier.
	 * @param mixed       $response Data to be stored.
	 * @param string|bool $repo     Repo name or false.
	 * @param string|bool $timeout  Timeout for cache.
	 *                              Default is $hours (12 hours).
	 *
	 * @return bool
	 */
	final public function set_repo_cache( $id, $response, $repo = false, $timeout = false ) {
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$slug  = $this->get_cache_key( $repo );
		$table = \Fragen\Git_Updater\DB\Repo_Cache_Table::instance();

		// Read the target column and the row timeout for the diff guard. The
		// scalar read and the object-cached timeout are both served from the
		// object cache (the timeout is surfaced via the repo_timeout: key), so
		// no separate [$id, 'timeout'] projection query is needed.
		$existing_value   = $table->get_repo( $slug, $id );
		$existing_timeout = $table->get_repo_timeout( $slug );
		// Normalize a SQL-NULL column to '' so the skip-write comparison agrees
		// with the projection read (null and '' are stored as SQL NULL → '').
		$existing = ( null === $existing_value && 0 === $existing_timeout )
			? null
			: [
				$id       => null === $existing_value ? '' : $existing_value,
				'timeout' => $existing_timeout,
			];

		// Skip the write when the new value matches the cached value for this
		// column. Serialized comparison is robust against null vs '', array
		// key reordering, and float precision edge cases. error_cache is
		// excluded because it manages its own short timeout and must refresh
		// on every retry.
		if ( null !== $existing
			&& 'error_cache' !== $id
			&& array_key_exists( $id, $existing )
			&& maybe_serialize( $existing[ $id ] ) === maybe_serialize( $response )
		) {
			return true;
		}

		$int_timeout = 0;
		if ( $timeout ) {
			$hours = $this->get_class_vars( 'API\API', 'hours' );

			/**
			 * Allow filtering of cache timeout for repo information.
			 *
			 * @since 10.0.0
			 *
			 * @param string      $timeout  Timeout value used with strtotime().
			 * @param string      $id       Data Identifier.
			 * @param mixed       $response Data to be stored.
			 * @param string|bool $repo     Repo name or false.
			 */
			$timeout = apply_filters( 'gu_repo_cache_timeout', $timeout, $id, $response, $repo );

			$int_timeout = strtotime( $timeout );
		}

		if ( 'error_cache' === $id ) {
			$error_timeout = $int_timeout > 0 ? $int_timeout - time() : 0;

			return $table->set_error_cache( $slug, $response, max( $error_timeout, 0 ) );
		}

		// When $timeout is false, preserve an existing still-valid row timeout so
		// repeated per-step writes don't bump the expiry. But refresh a dead one
		// (0 after a reset via delete_repo_api_data/delete_all_api_data, or naturally
		// expired): otherwise the row stays unreadable via get_repo_cache($timeout=true).
		if ( 0 === $int_timeout ) {
			if ( null === $existing || ! $this->is_cache_timeout_valid( (int) ( $existing['timeout'] ?? 0 ) ) ) {
				$int_timeout = (int) ( $this->get_class_vars( 'API\API', 'hours' ) * HOUR_IN_SECONDS + time() );
			} else {
				$int_timeout = (int) ( $existing['timeout'] ?? 0 );
			}
		}

		return $table->add_entry( $slug, $id, $response, $int_timeout );
	}

	/**
	 * Refresh the repo cache timeout to the default `$hours` after a complete fetch cycle.
	 *
	 * Companion to set_repo_cache(): per-entry writes preserve an existing 'timeout'
	 * via `??`, so without this an expired timeout from the prior cycle would linger
	 * and force the next pass to re-fetch.
	 *
	 * When the 'ran' bookkeeping is incomplete (e.g. a transient API failure), a
	 * fallback timeout is applied so the cache remains valid for a short interval
	 * instead of triggering an immediate re-fetch on the next page load.
	 *
	 * @param string $slug Repo slug.
	 *
	 * @return void
	 */
	final public function set_repo_cache_timeout( string $slug ): void {
		$table = \Fragen\Git_Updater\DB\Repo_Cache_Table::instance();

		if ( ! $this->is_fetch_complete( $slug ) ) {
			/**
			 * Filter the fallback cache timeout duration (in hours) for an
			 * incomplete fetch cycle.  Prevents infinite re-fetching by setting
			 * a short timeout so the next retry happens after this interval
			 * instead of on every page load.
			 *
			 * @since 11.0.0
			 *
			 * @param int    $hours Number of hours. Default 1.
			 * @param string $slug  Repository slug.
			 */
			$fallback_hours = (int) apply_filters( 'gu_repo_cache_timeout_fallback', 1, $slug );
			$table->set_repo_timeout( $slug, strtotime( "+{$fallback_hours} hours" ) );
			return;
		}

		$ran   = $table->get_repo( $slug, 'ran' );
		$hours = $this->get_class_vars( 'API\\API', 'hours' );
		$table->set_repo_timeout(
			$slug,
			strtotime( apply_filters( 'gu_repo_cache_timeout', '+' . $hours . ' hours', 'ran', $ran, $slug ) )
		);
	}

	/**
	 * Check if current cache timeout is valid.
	 *
	 * @param int $timestamp Cache timeout timestamp.
	 *
	 * @return bool true if cache timeout is valid, false if expired.
	 */
	final public function is_cache_timeout_valid( int $timestamp ): bool {
		return ! empty( $timestamp ) && time() < $timestamp;
	}

	/**
	 * Determine if a repo's full fetch cycle has completed by inspecting the
	 * `ran` column in the cache table.
	 *
	 * Each fetch step appends its key to `ran` only when it returns successfully,
	 * so a complete `ran` set means all API calls executed. A missing row, a
	 * missing `ran` column, or a partial `ran` set all indicate an incomplete cycle.
	 *
	 * @param string $slug Repository slug.
	 *
	 * @return bool
	 */
	final public function is_fetch_complete( string $slug ): bool {
		$ran = \Fragen\Git_Updater\DB\Repo_Cache_Table::instance()->get_repo( $slug, 'ran' );

		return null !== $ran
			&& [] === array_diff( self::expected_ran_steps(), (array) $ran );
	}

	/**
	 * Maybe extend API cached data and set new timeout if remote version
	 * is same as cached remote version?
	 *
	 * Uses is_fetch_complete() (which inspects the `ran` column) to determine if
	 * all API calls have executed. If not complete, do not extend or set timeout.
	 *
	 * @param array<string, string> $remote_headers Remote headers data array.
	 * @param stdClass              $repo           Repo data object.
	 * @param string                $old_version    Previously cached remote version to compare against.
	 *
	 * @return bool
	 */
	final public function maybe_extend_repo_cache( $remote_headers, $repo, string $old_version = '' ): bool {
		$return  = false;
		$slug    = $this->get_cache_key( $repo->slug ?? false );
		$table   = \Fragen\Git_Updater\DB\Repo_Cache_Table::instance();
		$timeout = (int) ( $table->get_repo( $slug, 'timeout' ) ?? 0 );

		if ( $this->is_fetch_complete( $slug ) ) {
			if ( version_compare( $remote_headers['Version'], $old_version, '==' ) ) {
				if ( ! $this->is_cache_timeout_valid( $timeout ) ) {
					$table->set_repo_timeout( $slug, strtotime( '+6 hours' ) );
				}
				$return = true;
			} else {
				/*
				 * Remote version changed. Release-asset data is only fetched lazily
				 * and keyed on the cached asset versions, so drop the stale
				 * release-asset columns now; get_api_release_assets() rebuilds them
				 * from the new remote during this same fetch cycle.
				 */
				$table->delete_entry( $slug, 'release_assets' );
				$table->delete_entry( $slug, 'release_asset' );
				$table->delete_entry( $slug, 'release_asset_download' );
			}
		}

		return $return;
	}

	/**
	 * Populate API data from cache.
	 *
	 * Data now populates via cache even when API is not available.
	 *
	 * @param  stdClass $repo     Repository object.
	 * @param  stdClass $repo_api Repository API object.
	 *
	 * @return stdClass
	 */
	final public function populate_api_data( $repo, $repo_api ) {
		$cache             = $this->get_repo_cache(
			$repo->slug,
			false,
			[ 'tags', 'changes', 'readme', 'meta', 'branches', 'release_asset', 'release_assets' ]
		);
		$validate_response = $this->get_reflection_method( $repo_api, 'validate_response' );
		$cached_data       = [
			'tags'           => $cache['tags'] ?? false,
			'changes'        => $cache['changes'] ?? false,
			'readme'         => $cache['readme'] ?? false,
			'meta'           => $cache['meta'] ?? false,
			'branches'       => $cache['branches'] ?? false,
			'release_asset'  => $cache['release_asset'] ?? false,
			'release_assets' => $cache['release_assets'] ?? false,
		];
		foreach ( $cached_data as $key => $value ) {
			switch ( $key ) {
				case 'tags':
					if ( $validate_response->invoke( $repo_api, $value ) ) {
						break;
					}
					$return_repo_type = $this->get_reflection_method( $repo_api, 'return_repo_type' );
					$repo_type        = $return_repo_type->invoke( $repo_api );

					$parse_tags = $this->get_reflection_method( $repo_api, 'parse_tags' );
					$tags       = $parse_tags->invoke( $repo_api, $value, $repo_type );
					$repo->tags = $tags;

					// newest_tag is persisted as a named cache entry at fetch time;
					// read it back so cache-only requests see the correct value.
					$repo->newest_tag = $this->get_newest_tag_from_cache( $repo->slug );
					break;
				case 'changes':
					if ( $validate_response->invoke( $repo_api, $value ) ) {
						break;
					}
					$repo->sections['changelog'] = $value;
					break;
				case 'readme':
					if ( $validate_response->invoke( $repo_api, $value ) ) {
						break;
					}
					$set_readme_info = $this->get_reflection_method( $repo_api, 'set_readme_info' );
					$set_readme_info->invoke( $repo_api, $value );
					break;
				case 'meta':
					if ( $validate_response->invoke( $repo_api, $value ) ) {
						break;
					}
					$repo->repo_meta      = $value;
					$add_repo_meta_object = $this->get_reflection_method( $repo_api, 'add_meta_repo_object' );
					$add_repo_meta_object->invoke( $repo_api );
					break;
				case 'branches':
					$repo->branches = ! $value ? [] : (array) $value;
					break;
				case 'release_asset':
					if ( $validate_response->invoke( $repo_api, $value ) ) {
						break;
					}
					$repo->release_assets[ $repo->newest_tag ] = $value;
					break;
				case 'release_assets':
					if ( $validate_response->invoke( $repo_api, $value ) ) {
						break;
					}
					if ( ! array_key_exists( $repo->newest_tag, $value['assets'] ) ) {
						$value['assets'] = array_merge( [ $repo->newest_tag => '' ], $value['assets'] );
					}
					$repo->release_assets     = $value['assets'] ?? $value;
					$repo->created_at         = $value['created_at'] ?? [];
					$repo->dev_release_assets = $value['dev_assets'] ?? [];
					$repo->dev_created_at     = $value['dev_created_at'] ?? [];
					break;
			}
		}

		return $repo;
	}

	/**
	 * Get the newest tag from the repo cache.
	 *
	 * On develop the cache is the `git_updater_cache` DB table; `newest_tag` is
	 * stored as a named column by `get_remote_api_tag()` at fetch time.
	 *
	 * @param string $slug Repo slug.
	 *
	 * @return string
	 */
	final protected function get_newest_tag_from_cache( $slug ): string {
		$value = $this->get_repo_cache( $slug, false, 'newest_tag' );

		return ! empty( $value ) ? (string) $value : '0.0.0';
	}

	/**
	 * Get reflection method.
	 *
	 * @param object|string $obj    Class object or name.
	 * @param string|null   $method Method name.
	 *
	 * @return ReflectionMethod
	 */
	final public function get_reflection_method( $obj, $method ): ReflectionMethod {
		$reflection_method = new ReflectionMethod( $obj, $method );
		PHP_VERSION_ID < 80100 && $reflection_method->setAccessible( true );

		return $reflection_method;
	}

	/**
	 * Getter for class variables.
	 *
	 * @param string $class_name Name of class.
	 * @param string $name       Name of variable.
	 *
	 * @return mixed
	 */
	final public function get_class_vars( $class_name, $name ) {
		static $reflection_cache = [];
		$cache_key               = $class_name . '.' . $name;
		if ( ! isset( $reflection_cache[ $cache_key ] ) ) {
			$class          = Singleton::get_instance( $class_name, $this );
			$reflection_obj = new ReflectionObject( $class );
			if ( ! $reflection_obj->hasProperty( $name ) ) {
				$reflection_cache[ $cache_key ] = [
					'instance' => $class,
					'property' => false,
				];
			} else {
				$property = $reflection_obj->getProperty( $name );
				PHP_VERSION_ID < 80100 && $property->setAccessible( true );
				$reflection_cache[ $cache_key ] = [
					'instance' => $class,
					'property' => $property,
				];
			}
		}
		$cached = $reflection_cache[ $cache_key ];
		if ( ! $cached['property'] ) {
			return false;
		}
		return $cached['property']->getValue( $cached['instance'] );
	}

	/**
	 * Function to check if plugin or theme object is able to be updated.
	 *
	 * @param stdClass $type Repo object.
	 *
	 * @return bool
	 */
	final public function can_update_repo( $type ) {
		if ( isset( $type->dev_release_assets ) && apply_filters( 'gu_dev_release_asset', false, $type ) ) {
			$release_asset_version = array_key_first( $type->dev_release_assets ) ?? '';
			$release_asset_version = ltrim( $release_asset_version, 'v' );

			// If the dev release asset version is the same as the remote version, use the remote version instead.
			$release_asset_version = $release_asset_version === $type->remote_version ? $release_asset_version : $type->remote_version;
		}
		$wp_version_ok   = ! empty( $type->requires )
			? is_wp_version_compatible( $type->requires )
			: true;
		$php_version_ok  = ! empty( $type->requires_php )
			? is_php_version_compatible( $type->requires_php )
			: true;
		$remote_is_newer = isset( $type->remote_version, $type->local_version )
			? version_compare( $type->remote_version, $type->local_version, '>' )
			: false;

		/**
		 * Filter $remote_is_newer if you use another method to test for updates.
		 *
		 * @since 10.0.0
		 * @param bool     $remote_is_newer
		 * @param stdClass $type            Plugin/Theme data.
		 */
		$remote_is_newer = apply_filters( 'gu_remote_is_newer', $remote_is_newer, $type );

		return $remote_is_newer && $wp_version_ok && $php_version_ok;
	}

	/**
	 * Delete all cached repository API data from the cache table.
	 *
	 * Preserves each repo's `current_branch` selection so a refresh or upgrade
	 * re-collects API data without resetting the user's active branch.
	 *
	 * @return bool
	 */
	final public function delete_all_cached_data() {
		$table = \Fragen\Git_Updater\DB\Repo_Cache_Table::instance();
		$table->delete_all_api_data();

		if ( ! is_multisite() || is_main_site() ) {
			wp_cron();
		}

		return true;
	}

	/**
	 * Is this a private repo with a token/checked or needing token/checked?
	 * Test for whether remote_version is set ( default = 0.0.0 ) or
	 * a repo option is set/not empty.
	 *
	 * @param stdClass $repo Repository.
	 *
	 * @return bool
	 */
	final public function is_private( $repo ) {
		if ( ! isset( $repo->remote_version ) && ! wp_doing_ajax() ) {
			return true;
		}
		if ( isset( $repo->remote_version ) && ! wp_doing_ajax() ) {
			return ( '0.0.0' === $repo->remote_version ) || ! empty( Base::$options[ $repo->slug ] );
		}

		return false;
	}

	/**
	 * Do we override dot org updates?
	 *
	 * @param string                       $type (plugin|theme).
	 * @param array<string,mixed>|stdClass $repo Repository object.
	 *
	 * @return bool
	 */
	final public function override_dot_org( $type, $repo ) {
		// Correctly account for dashicon in Settings page.
		$icon           = is_array( $repo );
		$repo           = is_array( $repo ) ? (object) $repo : $repo;
		$dot_org_master = ! $icon ? property_exists( $repo, 'dot_org' ) && $repo->dot_org && $repo->primary_branch === $repo->branch : true;

		$transient_key = 'plugin' === $type ? $repo->file : null;
		$transient_key = 'theme' === $type ? $repo->slug : $transient_key;

		$overrides = apply_filters( 'gu_override_dot_org', [] );
		$override  = in_array( $transient_key, $overrides, true );

		// Set $override if set in Skip Updates plugin.
		if ( ! $override && class_exists( '\\Fragen\\Skip_Updates\\Bootstrap' ) ) {
			$skip_updates = get_site_option( 'skip_updates', [] );
			foreach ( $skip_updates as $skip ) {
				if ( $repo->file === $skip['slug'] ) {
					$override = true;
					break;
				}
			}
		}

		/**
		 * Filter hook to completely ignore any updates from dot org when using Git Updater.
		 *
		 * @since 12.6.0
		 * @param bool $return_default Default is false. Do not ignore updates from dot org.
		 */
		return ! $dot_org_master || $override || apply_filters( 'gu_ignore_dot_org', false );
	}

	/**
	 * Sanitize each setting field as needed.
	 *
	 * @param array<string, string> $input Contains all settings fields as array keys.
	 *
	 * @return array<string, string>
	 */
	final public function sanitize( $input ) {
		$new_input = [];
		foreach ( array_keys( (array) $input ) as $id ) {
			$new_input[ sanitize_title_with_dashes( $id ) ] = sanitize_text_field( $input[ $id ] );
		}

		return $new_input;
	}

	/**
	 * Return an array of the running git servers.
	 *
	 * @access public
	 * @return array<int, string>
	 */
	final public function get_running_git_servers() {
		$plugins = Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this )->get_plugin_configs();
		$themes  = Singleton::get_instance( 'Fragen\Git_Updater\Theme', $this )->get_theme_configs();

		$repos = array_merge( $plugins, $themes );
		$gits  = array_map(
			function ( $e ) {
				return $e->git;
			},
			$repos
		);

		/**
		 * Filter array of repository git servers.
		 *
		 * @since 10.0.0
		 * @param array $gits  Array of repository git servers.
		 * @param array $repos Array of repository objects.
		 */
		$gits = apply_filters( 'gu_running_git_servers', $gits, $repos );

		return array_unique( array_values( $gits ) );
	}

	/**
	 * Check whether the background fetch for a managed repo has completed.
	 * A repo is considered waiting when its 'ran' bookkeeping key is missing
	 * or does not yet contain every expected API call.
	 *
	 * @param null|stdClass $repo Repo object.
	 *
	 * @return bool true when waiting for background job to finish.
	 */
	final protected function waiting_for_background_update( $repo = null ) {
		if ( null !== $repo ) {
			// Getting class instance also runs API::settings_hook().
			if ( isset( $repo->git ) ) {
				$git_class = 'Fragen\\Git_Updater\\API\\' . $this->base::$git_servers[ $repo->git ] . '_API';
				Singleton::get_instance( $git_class, $this );
			}

			// Probably not managed by Git Updater if we can't identify the repo.
			if ( ! isset( $repo->slug ) ) {
				return true;
			}

			$table = \Fragen\Git_Updater\DB\Repo_Cache_Table::instance();
			$ran   = $table->get_entry( $repo->slug, 'ran' );

			// No `ran` row yet, OR a pending error → still waiting on an API response.
			if ( null === $ran ) {
				return true;
			}

			$error_timeout = (int) $table->get_entry( $repo->slug, 'error_timeout' );
			return ! empty( $table->get_error_cache( $repo->slug ) )
				&& $error_timeout > 0
				&& time() < $error_timeout;
		}

		$repos = array_merge(
			Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this )->get_plugin_configs(),
			Singleton::get_instance( 'Fragen\Git_Updater\Theme', $this )->get_theme_configs()
		);

		/**
		 * Filter to modify array of repos.
		 *
		 * @since 10.2.0
		 * @param array $repos Array of repositories.
		 */
		$repos = apply_filters( 'gu_config_pre_process', $repos );

		// A repo is "waiting" if its fetch cycle hasn't run yet (no `ran` row) or a
		// step errored and is still pending a retry (non-empty `error_cache`). Uses a
		// slug+ran / slug+error_cache projection (no LONGTEXT read / unserialize of
		// payloads) so the full per-repo data is never materialized into memory.
		$table       = \Fragen\Git_Updater\DB\Repo_Cache_Table::instance();
		$ran_map     = $table->get_cached_ran();
		$error_flags = $table->get_cached_error_flags();

		$waiting = false;
		foreach ( $repos as $git_repo ) {
			$slug = $git_repo->slug;
			if ( ! isset( $ran_map[ $slug ] ) || ! empty( $error_flags[ $slug ] ) ) {
				$waiting = true;
				break;
			}
		}

		return $waiting;
	}

	/**
	 * Parse URI param returning array of parts.
	 *
	 * @param string $repo_header Repo URL.
	 *
	 * @return array<string, string|null>
	 */
	final protected function parse_header_uri( $repo_header ) {
		$header_parts = parse_url( $repo_header );
		if ( ! $header_parts || ! isset( $header_parts['path'] ) ) {
			return [];
		}
		$header_path          = pathinfo( $header_parts['path'] );
		$header['original']   = $repo_header;
		$header['scheme']     = $header_parts['scheme'] ?? null;
		$header['host']       = $header_parts['host'] ?? null;
		$header['owner']      = trim( $header_path['dirname'], '/' );
		$header['repo']       = isset( $header_path['extension'] ) && 'git' === $header_path['extension'] ? $header_path['filename'] : $header_path['basename'];
		$header['owner_repo'] = implode( '/', [ $header['owner'], $header['repo'] ] );
		$header['base_uri']   = str_replace( $header_parts['path'], '', $repo_header );
		$header['uri']        = isset( $header['scheme'] ) ? trim( $repo_header, '/' ) : null;

		$header = $this->sanitize( $header );

		return $header;
	}

	/**
	 * Create repo parts.
	 *
	 * @param string $repo Repo type.
	 * @param string $type plugin|theme.
	 *
	 * @return mixed
	 */
	final protected function get_repo_parts( $repo, $type ) {
		$extra_repo_headers = $this->get_class_vars( 'Base', 'extra_repo_headers' );

		$arr['bool'] = false;
		$pattern     = '/' . strtolower( $repo ) . '_/';
		$type        = preg_replace( $pattern, '', $type );

		$repos = [
			'types' => [ 'GitHub' => 'github_' . $type ],
			'uris'  => [ 'GitHub' => 'https://github.com/' ],
		];

		/**
		 * Filter repo parts from other git hosts.
		 *
		 * @since 10.0.0
		 * @param array  $repos Array of repo data.
		 * @param string $type  Repository type string.
		 */
		$repos = apply_filters( 'gu_get_repo_parts', $repos, $type );

		if ( array_key_exists( $repo, $repos['types'] ) ) {
			$arr['type']       = $repos['types'][ $repo ];
			$arr['git_server'] = strtolower( $repo );
			$arr['base_uri']   = $repos['uris'][ $repo ];
			$arr['bool']       = true;
			foreach ( $extra_repo_headers as $key => $value ) {
				$arr[ $key ] = $repo . ' ' . $value;
			}
		}

		return $arr;
	}

	/**
	 * Set array with normal repo names.
	 * Fix name even if installed without renaming originally, eg <repo>-master
	 *
	 * @param string                                                                        $slug            Repo slug.
	 * @param \Fragen\Git_Updater\Base|\Fragen\Git_Updater\Plugin|\Fragen\Git_Updater\Theme $upgrader_object Upgrader object.
	 *
	 * @return array<string, string>
	 */
	final protected function get_repo_slugs( string $slug, $upgrader_object = null ): array {
		$arr = [];

		// For AJAX install, not from Install tab, slug is correct. Refer to Add-Ons.
		if ( ( ! isset( $_POST['git_updater_repo'] ) && isset( $_POST['action'] ) )
			&& ( wp_doing_ajax() && check_ajax_referer( 'updates' ) )
		) {
			if ( str_contains( sanitize_key( wp_unslash( $_POST['action'] ) ), 'install' ) ) {
				$arr['slug'] = $slug;
			}
		}

		if ( null === $upgrader_object ) {
			$upgrader_object = $this;
		}

		$config = $this->get_class_vars( ( new ReflectionClass( $upgrader_object ) )->getShortName(), 'config' );

		foreach ( (array) $config as $repo ) {
			// Check repo slug or directory name for match.
			$slug_check = [
				$repo->slug,
				dirname( $repo->file ),
			];

			// Exact match.
			if ( in_array( $slug, $slug_check, true ) ) {
				$arr['slug'] = $repo->slug;
				break;
			}
		}

		return $arr;
	}

	/**
	 * Get default headers plus extra headers.
	 *
	 * @param string $type plugin|theme.
	 *
	 * @return array<string, string>
	 */
	final public function get_headers( $type ) {
		$default_plugin_headers = [
			'Name'            => 'Plugin Name',
			'PluginURI'       => 'Plugin URI',
			'Version'         => 'Version',
			'Description'     => 'Description',
			'Author'          => 'Author',
			'AuthorURI'       => 'Author URI',
			'License'         => 'License',
			'TextDomain'      => 'Text Domain',
			'DomainPath'      => 'Domain Path',
			'Network'         => 'Network',
			'Requires'        => 'Requires at least',
			'RequiresPHP'     => 'Requires PHP',
			'UpdateURI'       => 'Update URI',
			'RequiresPlugins' => 'Requires Plugins',
		];

		$default_theme_headers = [
			'Name'        => 'Theme Name',
			'ThemeURI'    => 'Theme URI',
			'Description' => 'Description',
			'Author'      => 'Author',
			'AuthorURI'   => 'Author URI',
			'Version'     => 'Version',
			'License'     => 'License',
			'Template'    => 'Template',
			'Status'      => 'Status',
			'Tags'        => 'Tags',
			'TextDomain'  => 'Text Domain',
			'DomainPath'  => 'Domain Path',
			'Requires'    => 'Requires at least',
			'RequiresPHP' => 'Requires PHP',
			'UpdateURI'   => 'Update URI',
		];

		$all_headers = array_merge( ${"default_{$type}_headers"}, Base::$extra_headers );

		return $all_headers;
	}

	/**
	 * Take remote file contents as string or array and parse and reduce headers.
	 *
	 * @param string|array<string, string> $contents File contents or array of file headers.
	 * @param string                       $type     plugin|theme.
	 *
	 * @return array<string, string>
	 */
	final public function get_file_headers( $contents, $type ) {
		$all_headers = [];
		$all_headers = $this->get_headers( $type );
		$all_headers = array_unique( $all_headers );

		/*
		 * Make sure we catch CR-only line endings.
		 */
		if ( is_string( $contents ) ) {
			$file_data = str_replace( "\r", "\n", $contents );

			foreach ( $all_headers as $field => $regex ) {
				if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $file_data, $match ) && $match[1] ) {
					$all_headers[ $field ] = _cleanup_header_comment( $match[1] );
				} else {
					$all_headers[ $field ] = '';
				}
			}
		}

		$all_headers = is_array( $contents ) ? $contents : $all_headers;

		// Reduce array to only headers with data.
		$all_headers = array_filter( $all_headers );

		return $all_headers;
	}

	/**
	 * Parse Enterprise, Languages, Release Asset, and CI Job headers for plugins and themes.
	 *
	 * @param array<string, mixed> $header       Array of repo data.
	 * @param array<string, mixed> $headers      Array of repo header data.
	 * @param array<int, string>   $header_parts Array of header parts.
	 *
	 * @return array<string, mixed>
	 */
	final public function parse_extra_headers( $header, $headers, $header_parts ) {
		$extra_repo_headers = $this->get_class_vars( 'Base', 'extra_repo_headers' );

		$header['enterprise_uri'] = null;
		$header['enterprise_api'] = null;
		$header['languages']      = null;
		$header['ci_job']         = false;
		$header['release_asset']  = false;
		$header['primary_branch'] = false;
		$header['did']            = null;

		if ( ! empty( $header['host'] ) ) {
			if ( 'GitHub' === $header_parts[0] && ! str_contains( $header['host'], 'github.com' ) ) {
				$header['enterprise_uri']  = $header['base_uri'];
				$header['enterprise_api']  = trim( $header['enterprise_uri'], '/' );
				$header['enterprise_api'] .= '/api/v3';
			}

			/**
			 * Filter REST endpoint for API.
			 *
			 * @since 10.0.0
			 * @param array  $header          Array or repo header data.
			 * @param string $header_parts[0] Name of git host.
			 */
			$header = apply_filters( 'gu_parse_enterprise_headers', $header, $header_parts[0] );
		}

		$self_hosted_parts = array_keys( $extra_repo_headers );
		foreach ( $self_hosted_parts as $part ) {
			if ( ! empty( $headers[ $header_parts[0] . $part ] ) ) {
				switch ( $part ) {
					case 'Languages':
						$header['languages'] = $headers[ $header_parts[0] . $part ];
						break;
					case 'CIJob':
						$header['ci_job'] = $headers[ $header_parts[0] . $part ];
						break;
				}
			}
		}
		$header['release_asset']  = ! $header['release_asset'] && ! empty( $headers['ReleaseAsset'] ) ? filter_var( $headers['ReleaseAsset'], FILTER_VALIDATE_BOOLEAN ) : $header['release_asset'];
		$header['primary_branch'] = ! $header['primary_branch'] && ! empty( $headers['PrimaryBranch'] ) ? $headers['PrimaryBranch'] : 'master';

		$header['did'] = ! empty( $headers['PluginID'] ) ? $headers['PluginID'] : '';
		$header['did'] = ! empty( $headers['ThemeID'] ) ? $headers['ThemeID'] : $header['did'];

		return $header;
	}

	/**
	 * Check to see if there's already a cron event for $hook.
	 *
	 * @param string $hook Cron event hook.
	 *
	 * @return bool
	 */
	final public function is_cron_event_scheduled( $hook ) {
		foreach ( wp_get_ready_cron_jobs() as $timestamp => $event ) {
			if ( key( $event ) === $hook ) {
				$this->is_cron_overdue( $timestamp );
				return true;
			}
		}

		return false;
	}

	/**
	 * Merge any existing scheduled cron batch for $hook with $new_args, then
	 * unschedule all existing events and schedule a single consolidated event.
	 * This prevents duplicate events accumulating across page loads.
	 *
	 * The unschedule + schedule is done in a single `cron` option write so a
	 * transient DB write failure cannot leave the hook half-removed, and so
	 * concurrent requests racing through this method cannot each trigger the
	 * core `could_not_set` unschedule error.
	 *
	 * If a *due* event for $hook already exists, the wp-cron runner is (or is
	 * about to be) executing it. Re-scheduling at `time()` would keep the batch
	 * perpetually pending and race core's post-run unschedule, so we bail out.
	 *
	 * @param string               $hook     Cron event hook name.
	 * @param array<string, mixed> $new_args Keyed-by-slug repo array for this request.
	 *
	 * @return void
	 */
	final protected function merge_and_reschedule_cron_batch( string $hook, array $new_args ): void {
		wp_cache_delete( 'cron', 'options' );
		$cron = _get_cron_array();
		foreach ( (array) $cron as $timestamp => $hooks ) {
			if ( isset( $hooks[ $hook ] ) && (int) $timestamp <= time() ) {
				// A due event exists; do not re-schedule it.
				return;
			}
		}

		// Merge args from any existing events for this hook.
		$cron = _get_cron_array();
		foreach ( (array) $cron as $hooks ) {
			if ( isset( $hooks[ $hook ] ) ) {
				foreach ( $hooks[ $hook ] as $event ) {
					$existing = $event['args'][0] ?? [];
					$new_args = array_merge( $existing, $new_args );
				}
			}
		}

		// Remove all existing events for $hook from the array manually
		// to avoid triggering wp_unschedule_hook() which causes "could_not_set" errors.
		foreach ( array_keys( (array) $cron ) as $timestamp ) {
			unset( $cron[ $timestamp ][ $hook ] );
			if ( empty( $cron[ $timestamp ] ) ) {
				unset( $cron[ $timestamp ] );
			}
		}

		// Write the cleaned cron array back to the database.
		_set_cron_array( $cron );

		// Clear cache again to ensure wp_schedule_single_event() reads fresh data.
		wp_cache_delete( 'cron', 'options' );

		// Schedule the single consolidated event using WordPress's built-in function
		// so it properly integrates with the cron system.
		$timestamp = time();
		wp_schedule_single_event( $timestamp, $hook, [ $new_args ] );
	}

	/**
	 * Check to see if wp-cron event is overdue by 24 hours and report error message.
	 *
	 * @param int $timestamp WP-Cron event timestamp.
	 *
	 * @return void
	 */
	final public function is_cron_overdue( $timestamp ) {
		$overdue = ( ( time() - $timestamp ) / HOUR_IN_SECONDS ) > 24;
		if ( $overdue ) {
			$error_msg = esc_html__( 'There may be a problem with WP-Cron. A Git Updater WP-Cron event is overdue.', 'git-updater' );
			$error     = new WP_Error( 'git_updater_cron_error', $error_msg );
			Singleton::get_instance( 'Fragen\Git_Updater\Messages', $this )->create_error_message( $error );
		}
	}

	/**
	 * Returns current plugin version.
	 *
	 * @return string Git Updater plugin version
	 */
	final public static function get_plugin_version() {
		$plugin_version = get_file_data( dirname( __DIR__, 3 ) . '/git-updater.php', [ 'Version' => 'Version' ] )['Version'];

		return $plugin_version;
	}

	/**
	 * Test whether to use release asset.
	 *
	 * A pure config + target decision. Availability is deliberately NOT decided
	 * here: the cached release_assets struct is only populated by
	 * get_release_assets(), which runs after this decision inside
	 * construct_download_link(), and '0.0.0' newest_tag as a proxy conflates
	 * "tags never fetched" with "repo has no tags". Existence of assets is
	 * resolved downstream in resolve_release_asset().
	 *
	 * @param bool|string $branch_switch Branch to switch to or false.
	 *
	 * @return bool
	 */
	final public function use_release_asset( $branch_switch = false ): bool {
		$target = false !== $branch_switch ? $branch_switch : $this->type->branch;

		// Check if target is a tag (not in branches list).
		$is_tag = is_string( $target ) && ! array_key_exists( $target, (array) ( $this->type->branches ?? [] ) );

		// Use release asset for primary branch or any tag (if release_asset is true).
		$use_release_asset = $this->type->primary_branch === $target || $is_tag;

		return (bool) ( $this->type->release_asset ?? false )
			&& $use_release_asset;
	}

	/**
	 * Modify options without saving.
	 *
	 * Check if a filter effecting a checkbox is set elsewhere.
	 * Adds value '-1' without saving so that checkbox is checked and disabled.
	 *
	 * @param  array<string, mixed> $options Site options.
	 * @return array<string, mixed>
	 */
	private function modify_options( $options ) {
		// Remove any inadvertently saved options with value -1.
		$options = array_filter(
			$options,
			function ( $e ) {
				return '-1' !== $e;
			}
		);

		if ( ! isset( $options['branch_switch'] ) ) {
			$options['branch_switch'] = '0';
		}

		if ( ! isset( $options['bypass_background_processing'] ) ) {
			$options['bypass_background_processing'] = '0';
		}

		// Check if filter set elsewhere.
		$disable_wp_cron = (bool) apply_filters( 'gu_disable_wpcron', false );
		if ( $disable_wp_cron ) {
			$options['bypass_background_processing'] = '1';
		}

		return $options;
	}

	/**
	 * Get WP and PHP requirements from main plugin/theme file.
	 *
	 * @param stdClass $repo Repository object.
	 *
	 * @return array<string, string|null>
	 */
	final protected function get_repo_requirements( $repo ) {
		$requires      = [
			'RequiresPHP' => 'Requires PHP',
			'RequiresWP'  => 'Requires at least',
		];
		$default_empty = [
			'RequiresPHP' => null,
			'RequiresWP'  => null,
		];
		$filepath      = 'gist' === $repo->git
			? trailingslashit( dirname( $repo->local_path ) ) . $repo->file
			: '';
		$repo_data     = file_exists( $filepath ) ? get_file_data( $filepath, $requires ) : $default_empty;

		return $repo_data;
	}

	/**
	 * Deletes temporary upgrade directory.
	 *
	 * @since 10.10.0
	 * @uses `upgrader_install_package_result` filter
	 *
	 * @global \WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 *
	 * @param array<string, mixed>|WP_Error $result Result from WP_Upgrader::install_package().
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	final public function delete_upgrade_source( $result ) {
		global $wp_filesystem;

		if ( ! is_wp_error( $result ) && ! empty( $result['destination_name'] ) ) {
			$wp_filesystem->delete(
				$wp_filesystem->wp_content_dir() . "upgrade/{$result['destination_name']}",
				true
			);
		}

		return $result;
	}

	/**
	 * Get hash of DID.
	 *
	 * @param  string $did DID.
	 *
	 * @return string
	 */
	final public function get_did_hash( $did ): string {
		return substr( hash( 'sha256', $did ), 0, 6 );
	}

	/**
	 * Return plugin file without DID hash.
	 *
	 * Assumes pattern of <slug>-<hash>.
	 *
	 * @param string $did DID.
	 * @param string $plugin Plugin basename.
	 *
	 * @return string
	 */
	final public function get_file_without_did_hash( $did, $plugin ): string {
		list( $slug, $file ) = explode( '/', $plugin, 2 );
		$slug                = str_replace( '-' . $this->get_did_hash( $did ), '', $slug );

		return $slug . '/' . $file;
	}

	/**
	 * Get GitHub API rate limit headers.
	 *
	 * Display ratelimit reset time in minutes.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	final public function get_github_rate_limit_headers() {
		$auth_header = Singleton::get_instance( 'Fragen\Git_Updater\API\API', $this )->add_auth_header( [], 'https://api.github.com/rate_limit' );
		$response    = wp_remote_head( 'https://api.github.com/rate_limit', $auth_header );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$headers = wp_remote_retrieve_headers( $response );
		$data    = $headers->getAll();
		if ( isset( $data['x-ratelimit-reset'] ) ) {
			$reset = (int) $data['x-ratelimit-reset'];
			// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			$data['x-ratelimit-reset'] = date( 'i', $reset - time() ) . ' minutes';
		} else {
			$data['x-ratelimit-reset'] = '60 minutes';
		}

		return $data;
	}
}
