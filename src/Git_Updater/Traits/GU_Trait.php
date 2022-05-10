<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater\Traits;

use Fragen\Singleton;
use Fragen\Git_Updater\Readme_Parser as Readme_Parser;

/**
 * Trait GU_Trait
 */
trait GU_Trait {

	/**
	 * Checks to see if a heartbeat is resulting in activity.
	 *
	 * @return bool
	 */
	public static function is_heartbeat() {
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
	public static function is_wp_cli() {
		return defined( 'WP_CLI' ) && \WP_CLI;
	}

	/**
	 * Checks to see if DOING_AJAX.
	 *
	 * @return bool
	 */
	public static function is_doing_ajax() {
		return defined( 'DOING_AJAX' ) && \DOING_AJAX;
	}

	/**
	 * Checks to see if Git Updater PRO is running.
	 *
	 * @return bool
	 */
	public function is_premium_only() {
		if ( \is_plugin_active( 'git-updater-pro/git-updater-pro.php' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Load site options.
	 */
	public function load_options() {
		Singleton::get_instance( 'Fragen\Git_Updater\GU_Upgrade', $this )->convert_ghu_options_to_gu_options();
		$base           = Singleton::get_instance( 'Fragen\Git_Updater\Base', $this );
		$base::$options = get_site_option( 'git_updater', [] );
		$base::$options = $this->modify_options( $base::$options );
	}

	/**
	 * Check current page.
	 *
	 * @param  array $pages Array of pages.
	 * @return bool
	 */
	public function is_current_page( array $pages ) {
		global $pagenow;

		return in_array( $pagenow, $pages, true );
	}

	/**
	 * Returns repo cached data.
	 *
	 * @access protected
	 *
	 * @param string|bool $repo Repo name or false.
	 *
	 * @return array|bool The repo cache. False if expired.
	 */
	public function get_repo_cache( $repo = false ) {
		if ( ! $repo ) {
			$repo = isset( $this->type->slug ) ? $this->type->slug : 'ghu';
		}
		$cache_key = 'ghu-' . md5( $repo );
		$cache     = get_site_option( $cache_key );

		if ( empty( $cache['timeout'] ) || time() > $cache['timeout'] ) {
			return false;
		}

		return $cache;
	}

	/**
	 * Sets repo data for cache in site option.
	 *
	 * @access protected
	 *
	 * @param string      $id       Data Identifier.
	 * @param mixed       $response Data to be stored.
	 * @param string|bool $repo     Repo name or false.
	 * @param string|bool $timeout  Timeout for cache.
	 *                              Default is $hours (12 hours).
	 *
	 * @return bool
	 */
	public function set_repo_cache( $id, $response, $repo = false, $timeout = false ) {
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$hours = $this->get_class_vars( 'API\API', 'hours' );
		if ( ! $repo ) {
			$repo = isset( $this->type->slug ) ? $this->type->slug : 'ghu';
		}
		$cache_key = 'ghu-' . md5( $repo );
		$timeout   = $timeout ? $timeout : '+' . $hours . ' hours';

		/**
		 * Allow filtering of cache timeout for repo information.
		 *
		 * @since 8.7.1
		 *
		 * @param string      $timeout  Timeout value used with strtotime().
		 * @param string      $id       Data Identifier.
		 * @param mixed       $response Data to be stored.
		 * @param string|bool $repo     Repo name or false.
		 */
		$timeout = apply_filters_deprecated( 'github_updater_repo_cache_timeout', [ $timeout, $id, $response, $repo ], '10.0.0', 'gu_repo_cache_timeout' );

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

		$this->response['timeout'] = strtotime( $timeout );
		$this->response[ $id ]     = $response;

		update_site_option( $cache_key, $this->response );

		return true;
	}

	/**
	 * Getter for class variables.
	 *
	 * @param string $class_name Name of class.
	 * @param string $var        Name of variable.
	 *
	 * @return mixed
	 */
	public function get_class_vars( $class_name, $var ) {
		$class          = Singleton::get_instance( $class_name, $this );
		$reflection_obj = new \ReflectionObject( $class );
		if ( ! $reflection_obj->hasProperty( $var ) ) {
			return false;
		}
		$property = $reflection_obj->getProperty( $var );
		$property->setAccessible( true );

		return $property->getValue( $class );
	}

	/**
	 * Returns static class variable $error_code.
	 *
	 * @return array self::$error_code
	 */
	public function get_error_codes() {
		return $this->get_class_vars( 'API\API', 'error_code' );
	}

	/**
	 * Function to check if plugin or theme object is able to be updated.
	 *
	 * @param \stdClass $type Repo object.
	 *
	 * @return bool
	 */
	public function can_update_repo( $type ) {
		$wp_version = get_bloginfo( 'version' );

		$wp_version_ok   = ! empty( $type->requires )
			? version_compare( $wp_version, $type->requires, '>=' )
			: true;
		$php_version_ok  = ! empty( $type->requires_php )
			? version_compare( phpversion(), $type->requires_php, '>=' )
			: true;
		$remote_is_newer = isset( $type->remote_version )
			? version_compare( $type->remote_version, $type->local_version, '>' )
			: false;

		/**
		 * Filter $remote_is_newer if you use another method to test for updates.
		 *
		 * @since 10.0.0
		 * @param bool      $remote_is_newer
		 * @param \stdClass $type            Plugin/Theme data.
		 */
		$remote_is_newer = apply_filters( 'gu_remote_is_newer', $remote_is_newer, $type );

		/**
		 * Filter $remote_is_newer if you use another method to test for updates.
		 *
		 * @param bool      $remote_is_newer
		 * @param \stdClass $type            Plugin/Theme data.
		 */
		$remote_is_newer = $remote_is_newer ?: apply_filters_deprecated( 'github_updater_remote_is_newer', [ $remote_is_newer, $type ], '10.0.0', 'gu_remote_is_newer' );

		return $remote_is_newer && $wp_version_ok && $php_version_ok;
	}

	/**
	 * Delete all `ghu-` prefixed data from options table.
	 *
	 * @return bool
	 */
	public function delete_all_cached_data() {
		global $wpdb;

		$table         = is_multisite() ? $wpdb->base_prefix . 'sitemeta' : $wpdb->base_prefix . 'options';
		$column        = is_multisite() ? 'meta_key' : 'option_name';
		$delete_string = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' LIKE %s LIMIT 1000';

		$wpdb->query( $wpdb->prepare( $delete_string, [ '%ghu-%' ] ) ); // phpcs:ignore

		wp_cron();

		return true;
	}

	/**
	 * Is this a private repo with a token/checked or needing token/checked?
	 * Test for whether remote_version is set ( default = 0.0.0 ) or
	 * a repo option is set/not empty.
	 *
	 * @param \stdClass $repo Repository.
	 *
	 * @return bool
	 */
	public function is_private( $repo ) {
		if ( ! isset( $repo->remote_version ) && ! self::is_doing_ajax() ) {
			return true;
		}
		if ( isset( $repo->remote_version ) && ! self::is_doing_ajax() ) {
			return ( '0.0.0' === $repo->remote_version ) || ! empty( self::$options[ $repo->slug ] );
		}

		return false;
	}

	/**
	 * Do we override dot org updates?
	 *
	 * @param string    $type (plugin|theme).
	 * @param \stdClass $repo Repository object.
	 *
	 * @return bool
	 */
	public function override_dot_org( $type, $repo ) {
		// Correctly account for dashicon in Settings page.
		$icon           = is_array( $repo );
		$repo           = is_array( $repo ) ? (object) $repo : $repo;
		$dot_org_master = ! $icon ? property_exists( $repo, 'dot_org' ) && $repo->dot_org && $repo->primary_branch === $repo->branch : true;

		$transient_key = 'plugin' === $type ? $repo->file : null;
		$transient_key = 'theme' === $type ? $repo->slug : $transient_key;

		$overrides = apply_filters( 'gu_override_dot_org', [] );
		$overrides = empty( $overrides ) ? apply_filters_deprecated( 'github_updater_override_dot_org', [ [] ], '10.0.0', 'gu_override_dot_org' ) : $overrides;

		$override = in_array( $transient_key, $overrides, true );

		// Set $override if set in Skip Updates plugin.
		if ( ! $override && \class_exists( '\\Fragen\\Skip_Updates\\Bootstrap' ) ) {
			$skip_updates = get_site_option( 'skip_updates', [] );
			foreach ( $skip_updates as $skip ) {
				if ( $repo->file === $skip['slug'] ) {
					$override = true;
					break;
				}
			}
		}

		return ! $dot_org_master || $override;
	}

	/**
	 * Sanitize each setting field as needed.
	 *
	 * @param array $input Contains all settings fields as array keys.
	 *
	 * @return array
	 */
	public function sanitize( $input ) {
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
	 * @return array $gits
	 */
	public function get_running_git_servers() {
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
	 * Check to see if wp-cron/background updating has finished.
	 * Or not managed by Git Updater.
	 *
	 * @param null|\stdClass $repo Repo object.
	 *
	 * @return bool true when waiting for background job to finish.
	 */
	protected function waiting_for_background_update( $repo = null ) {
		$caches = [];
		if ( null !== $repo ) {
			$cache = isset( $repo->slug ) ? $this->get_repo_cache( $repo->slug ) : null;

			// Probably not managed by Git Updater if $cache is empty.
			return empty( $cache );
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

		foreach ( $repos as $git_repo ) {
			$caches[ $git_repo->slug ] = $this->get_repo_cache( $git_repo->slug );
		}
		$waiting = array_filter(
			$caches,
			function ( $e ) {
				return empty( $e );
			}
		);

		return ! empty( $waiting );
	}

	/**
	 * Parse URI param returning array of parts.
	 *
	 * @param string $repo_header Repo URL.
	 *
	 * @return array $header
	 */
	protected function parse_header_uri( $repo_header ) {
		$header_parts         = parse_url( $repo_header );
		$header_path          = pathinfo( $header_parts['path'] );
		$header['original']   = $repo_header;
		$header['scheme']     = isset( $header_parts['scheme'] ) ? $header_parts['scheme'] : null;
		$header['host']       = isset( $header_parts['host'] ) ? $header_parts['host'] : null;
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
	protected function get_repo_parts( $repo, $type ) {
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
		 * @param array $repos Array of repo data.
		 */
		$repos = \apply_filters( 'gu_get_repo_parts', $repos, $type );

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
	 * @param string            $slug            Repo slug.
	 * @param Base|Plugin|Theme $upgrader_object Upgrader object.
	 *
	 * @return array
	 */
	protected function get_repo_slugs( $slug, $upgrader_object = null ) {
		$arr    = [];
		$rename = explode( '-', $slug );
		array_pop( $rename );
		$rename = implode( '-', $rename );

		if ( null === $upgrader_object ) {
			$upgrader_object = $this;
		}

		$rename = isset( $upgrader_object->config[ $slug ] ) ? $slug : $rename;
		$config = $this->get_class_vars( ( new \ReflectionClass( $upgrader_object ) )->getShortName(), 'config' );

		foreach ( (array) $config as $repo ) {
			// Check repo slug or directory name for match.
			$slug_check = [
				$repo->slug,
				dirname( $repo->file ),
			];

			// Exact match.
			if ( \in_array( $slug, $slug_check, true ) ) {
				$arr['slug'] = $repo->slug;
				break;
			}

			// Soft match, there may still be an exact $slug match.
			if ( \in_array( $rename, $slug_check, true ) ) {
				$arr['slug'] = $repo->slug;
			}
		}

		return $arr;
	}

	/**
	 * Get default headers plus extra headers.
	 *
	 * @param string $type plugin|theme.
	 *
	 * @return array
	 */
	public function get_headers( $type ) {
		$default_plugin_headers = [
			'Name'        => 'Plugin Name',
			'PluginURI'   => 'Plugin URI',
			'Version'     => 'Version',
			'Description' => 'Description',
			'Author'      => 'Author',
			'AuthorURI'   => 'Author URI',
			'TextDomain'  => 'Text Domain',
			'DomainPath'  => 'Domain Path',
			'Network'     => 'Network',
			'Requires'    => 'Requires at least',
			'RequiresPHP' => 'Requires PHP',
		];

		$default_theme_headers = [
			'Name'        => 'Theme Name',
			'ThemeURI'    => 'Theme URI',
			'Description' => 'Description',
			'Author'      => 'Author',
			'AuthorURI'   => 'Author URI',
			'Version'     => 'Version',
			'Template'    => 'Template',
			'Status'      => 'Status',
			'Tags'        => 'Tags',
			'TextDomain'  => 'Text Domain',
			'DomainPath'  => 'Domain Path',
			'Requires'    => 'Requires at least',
			'RequiresPHP' => 'Requires PHP',
		];

		$all_headers = array_merge( ${"default_{$type}_headers"}, self::$extra_headers );

		return $all_headers;
	}

	/**
	 * Take remote file contents as string or array and parse and reduce headers.
	 *
	 * @param string|array $contents File contents or array of file headers.
	 * @param string       $type     plugin|theme.
	 *
	 * @return array $all_headers Reduced array of all headers.
	 */
	public function get_file_headers( $contents, $type ) {
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
	 * @param array $header       Array of repo data.
	 * @param array $headers      Array of repo header data.
	 * @param array $header_parts Array of header parts.
	 *
	 * @return array
	 */
	public function parse_extra_headers( $header, $headers, $header_parts ) {
		$extra_repo_headers = $this->get_class_vars( 'Base', 'extra_repo_headers' );

		$header['enterprise_uri'] = null;
		$header['enterprise_api'] = null;
		$header['languages']      = null;
		$header['ci_job']         = false;
		$header['release_asset']  = false;
		$header['primary_branch'] = false;

		if ( ! empty( $header['host'] ) ) {
			if ( 'GitHub' === $header_parts[0] && false === strpos( $header['host'], 'github.com' ) ) {
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
		$header['release_asset']  = ! $header['release_asset'] && ! empty( $headers['ReleaseAsset'] ) ? 'true' === $headers['ReleaseAsset'] : $header['release_asset'];
		$header['primary_branch'] = ! $header['primary_branch'] && ! empty( $headers['PrimaryBranch'] ) ? $headers['PrimaryBranch'] : 'master';

		return $header;
	}

	/**
	 * Check to see if there's already a cron event for $hook.
	 *
	 * @param string $hook Cron event hook.
	 *
	 * @return bool
	 */
	public function is_cron_event_scheduled( $hook ) {
		foreach ( wp_get_ready_cron_jobs() as $timestamp => $event ) {
			if ( key( $event ) === $hook ) {
				$this->is_cron_overdue( $timestamp );
				return true;
			}
		}

		return false;
	}

	/**
	 * Check to see if wp-cron event is overdue by 24 hours and report error message.
	 *
	 * @param int $timestamp WP-Cron event timestamp.
	 *
	 * @return void
	 */
	public function is_cron_overdue( $timestamp ) {
		$overdue = ( ( time() - $timestamp ) / HOUR_IN_SECONDS ) > 24;
		if ( $overdue ) {
			$error_msg = esc_html__( 'There may be a problem with WP-Cron. A Git Updater WP-Cron event is overdue.', 'git-updater' );
			$error     = new \WP_Error( 'git_updater_cron_error', $error_msg );
			Singleton::get_instance( 'Fragen\Git_Updater\Messages', $this )->create_error_message( $error );
		}
	}

	/**
	 * Returns current plugin version.
	 *
	 * @return string Git Updater plugin version
	 */
	public static function get_plugin_version() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_data = \get_plugin_data( dirname( __DIR__, 3 ) . '/git-updater.php' );

		return $plugin_data['Version'];
	}

	/**
	 * Test whether to use release asset.
	 *
	 * @param bool|string $branch_switch Branch to switch to or false.
	 *
	 * @return bool
	 */
	public function use_release_asset( $branch_switch = false ) {
		$is_tag                  = $branch_switch && ! array_key_exists( $branch_switch, $this->type->branches );
		$switch_master_tag       = $this->type->primary_branch === $branch_switch || $is_tag;
		$current_master_noswitch = $this->type->primary_branch === $this->type->branch && false === $branch_switch;

		$need_release_asset = $switch_master_tag || $current_master_noswitch;
		$use_release_asset  = $this->type->release_asset && '0.0.0' !== $this->type->newest_tag
			&& $need_release_asset;

		return $use_release_asset;
	}

	/**
	 * Modify options without saving.
	 *
	 * Check if a filter effecting a checkbox is set elsewhere.
	 * Adds value '-1' without saving so that checkbox is checked and disabled.
	 *
	 * @param  array $options Site options.
	 * @return array
	 */
	private function modify_options( $options ) {
		// Remove any inadvertently saved options with value -1.
		$options = array_filter(
			$options,
			function ( $e ) {
				return '-1' !== $e;
			}
		);

		// Check if filter set elsewhere.
		$disable_wp_cron = (bool) apply_filters( 'gu_disable_wpcron', false );
		$disable_wp_cron = $disable_wp_cron ?: (bool) apply_filters_deprecated( 'github_updater_disable_wpcron', [ false ], '10.0.0', 'gu_disable_wpcron' );

		if ( ! isset( $options['bypass_background_processing'] ) && $disable_wp_cron ) {
			$options['bypass_background_processing'] = '-1';
		}

		return $options;
	}

	/**
	 * Set readme and changelog data when repo set to not check API.
	 * Get data from local files.
	 *
	 * @param \stdClass|bool $false Plugin API response.
	 * @param \stdClass      $repo Repo object.
	 *
	 * @return \stdClass
	 */
	public function set_no_api_check_readme_changes( $false, $repo ) {
		if ( ( $false || $repo ) && isset( $repo->git ) && ! isset( $repo->remote_version ) ) {
			$repo_api = Singleton::get_instance( 'API\API', $this )->get_repo_api( $repo->git, $repo );

			$changelog_file = $this->base->get_changelog_filename( $repo );
			$changelog      = $changelog_file ? $repo_api->get_local_info( $repo, $changelog_file ) : false;
			if ( $changelog ) {
				$parser                      = new \Parsedown();
				$changes                     = $parser->text( $changelog );
				$repo->sections['changelog'] = $changes;
			}

			$readme = $repo_api->get_local_info( $repo, 'readme.txt' );
			if ( $readme ) {
				$parser = new Readme_Parser( $readme );
				$readme = $parser->parse_data();
				$repo_api->set_readme_info( $readme );
			}

			$repo_requires      = $this->get_repo_requirements( $repo );
			$repo->requires     = empty( $repo->requires ) ? $repo_requires['RequiresWP'] : $repo->requires;
			$repo->requires_php = empty( $repo->requires_php ) ? $repo_requires['RequiresPHP'] : $repo->requires_php;
			$repo->version      = $repo->local_version;

			$false_arr = array_merge( (array) $false, (array) $repo );
			$false     = (object) $false_arr;
		}

		return $false;
	}

	/**
	 * Get WP and PHP requirements from main plugin/theme file.
	 *
	 * @param \stdClass $repo Repository object.
	 *
	 * @return array
	 */
	protected function get_repo_requirements( $repo ) {
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
			: $repo->local_path . basename( $repo->file );
		$repo_data     = file_exists( $filepath ) ? get_file_data( $filepath, $requires ) : $default_empty;

		return $repo_data;
	}

	/**
	 * Deletes temporary upgrade directory.
	 *
	 * @since 10.10.0
	 * @uses `upgrader_install_package_result` filter
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 *
	 * @param array|WP_Error $result     Result from WP_Upgrader::install_package().
	 * @param array          $hook_extra Extra arguments passed to hooked filters.
	 * @return bool
	 */
	public function delete_upgrade_source( $result, $hook_extra ) {
		global $wp_filesystem;

		if ( ! is_wp_error( $result ) && ! empty( $result['destination_name'] ) ) {
			$wp_filesystem->delete(
				$wp_filesystem->wp_content_dir() . "upgrade/{$result['destination_name']}",
				true
			);
		}

		return $result;
	}

}
