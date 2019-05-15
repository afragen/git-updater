<?php
/**
 * GitHub Updater
 *
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 * @package   github-updater
 */

namespace Fragen\GitHub_Updater\Traits;

use Fragen\Singleton;

/**
 * Trait GHU_Trait
 */
trait GHU_Trait {
	/**
	 * Checks to see if a heartbeat is resulting in activity.
	 *
	 * @return bool
	 */
	public static function is_heartbeat() {
		return isset( $_POST['action'] ) && 'heartbeat' === $_POST['action'];
	}

	/**
	 * Checks to see if WP_CLI.
	 *
	 * @return bool
	 */
	public static function is_wp_cli() {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * Checks to see if DOING_AJAX.
	 *
	 * @return bool
	 */
	public static function is_doing_ajax() {
		return defined( 'DOING_AJAX' ) && DOING_AJAX;
	}

	/**
	 * Load site options.
	 */
	public function load_options() {
		$base           = Singleton::get_instance( 'Base', $this );
		$base::$options = get_site_option( 'github_updater', [] );
	}

	/**
	 * Check current page.
	 *
	 * @param array $pages
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
		$hours = $this->get_class_vars( 'API', 'hours' );
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
		$timeout = apply_filters( 'github_updater_repo_cache_timeout', $timeout, $id, $response, $repo );

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
		return $this->get_class_vars( 'API', 'error_code' );
	}

	/**
	 * Function to check if plugin or theme object is able to be updated.
	 *
	 * @param \stdClass $type
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
		 * @since 8.7.0
		 * @param bool      $remote_is_newer
		 * @param \stdClass $type             Plugin/Theme data.
		 */
		$remote_is_newer = apply_filters( 'github_updater_remote_is_newer', $remote_is_newer, $type );

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

		$wpdb->query( $wpdb->prepare( $delete_string, [ '%ghu-%' ] ) );

		wp_cron();

		return true;
	}

	/**
	 * Is this a private repo with a token/checked or needing token/checked?
	 * Test for whether remote_version is set ( default = 0.0.0 ) or
	 * a repo option is set/not empty.
	 *
	 * @param \stdClass $repo
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
	 * @param string    $type (plugin|theme)
	 * @param \stdClass $repo Repository object.
	 *
	 * @return bool
	 */
	public function override_dot_org( $type, $repo ) {
		// Correctly account for dashicon in Settings page.
		$icon           = is_array( $repo );
		$repo           = is_array( $repo ) ? (object) $repo : $repo;
		$dot_org_master = ! $icon ? $repo->dot_org && 'master' === $repo->branch : true;

		$transient_key = 'plugin' === $type ? $repo->file : null;
		$transient_key = 'theme' === $type ? $repo->slug : $transient_key;

		/**
		 * Filter update to override dot org.
		 *
		 * @since 8.5.0
		 *
		 * @return bool
		 */
		$override = in_array( $transient_key, apply_filters( 'github_updater_override_dot_org', [] ), true );

		return ! $dot_org_master || $override || $this->deprecate_override_constant();
	}

	/**
	 * Deprecated dot org override constant.
	 *
	 * @return bool
	 */
	public function deprecate_override_constant() {
		if ( defined( 'GITHUB_UPDATER_OVERRIDE_DOT_ORG' ) && GITHUB_UPDATER_OVERRIDE_DOT_ORG ) {
			error_log( 'GITHUB_UPDATER_OVERRIDE_DOT_ORG constant deprecated. Use `github_updater_override_dot_org` filter hook.' );
			return true;
		}
		return false;
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
			$new_input[ sanitize_file_name( $id ) ] = sanitize_text_field( $input[ $id ] );
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
		$plugins = Singleton::get_instance( 'Plugin', $this )->get_plugin_configs();
		$themes  = Singleton::get_instance( 'Theme', $this )->get_theme_configs();

		$repos = array_merge( $plugins, $themes );
		$gits  = array_map(
			function ( $e ) {
				if ( ! empty( $e->enterprise ) ) {
					if ( 'bitbucket' === $e->git ) {
						return 'bbserver';
					}
					if ( 'gitlab' === $e->git ) {
						return 'gitlabce';
					}
				}

				return $e->git;
			},
			$repos
		);

		return array_unique( array_values( $gits ) );
	}

	/**
	 * Parse URI param returning array of parts.
	 *
	 * @param string $repo_header
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
		$header['repo']       = $header_path['filename'];
		$header['owner_repo'] = implode( '/', [ $header['owner'], $header['repo'] ] );
		$header['base_uri']   = str_replace( $header_parts['path'], '', $repo_header );
		$header['uri']        = isset( $header['scheme'] ) ? trim( $repo_header, '/' ) : null;

		$header = $this->sanitize( $header );

		return $header;
	}

	/**
	 * Take remote file contents as string or array and parse and reduce headers.
	 *
	 * @param string|array $contents File contents or array of file headers.
	 * @param string       $type plugin|theme.
	 *
	 * @return array $all_headers Reduced array of all headers.
	 */
	public function get_file_headers( $contents, $type ) {
		$all_headers            = [];
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
		];

		if ( 'plugin' === $type ) {
			$all_headers = $default_plugin_headers;
		}
		if ( 'theme' === $type ) {
			$all_headers = $default_theme_headers;
		}

		/*
		 * Merge extra headers and default headers.
		 */
		$all_headers = array_merge( self::$extra_headers, $all_headers );
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
		$all_headers = array_filter(
			$all_headers,
			function ( $e ) {
				return ! empty( $e );
			}
		);

		return $all_headers;
	}
}
