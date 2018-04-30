<?php
/**
 * GitHub Updater
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

namespace Fragen\GitHub_Updater\Traits;

use Fragen\Singleton,
	Fragen\GitHub_Updater\Settings;


/**
 * Trait GHU_Trait
 *
 * @package Fragen\GitHub_Updater
 */
trait GHU_Trait {

	/**
	 * Checks to see if a heartbeat is resulting in activity.
	 *
	 * @return bool
	 */
	public static function is_heartbeat() {
		return ( isset( $_POST['action'] ) && 'heartbeat' === $_POST['action'] );
	}

	/**
	 * Checks to see if WP_CLI.
	 *
	 * @return bool
	 */
	public static function is_wp_cli() {
		return ( defined( 'WP_CLI' ) && WP_CLI );
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
			$repo = isset( $this->type->repo ) ? $this->type->repo : 'ghu';
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
		$hours = $this->get_class_vars( 'API', 'hours' );
		if ( ! $repo ) {
			$repo = isset( $this->type->repo ) ? $this->type->repo : 'ghu';
		}
		$cache_key = 'ghu-' . md5( $repo );
		$timeout   = $timeout ? $timeout : '+' . $hours . ' hours';

		$this->response['timeout'] = strtotime( $timeout );
		$this->response[ $id ]     = $response;

		update_site_option( $cache_key, $this->response );

		return true;
	}

	/**
	 * Getter for class variables.
	 * Uses ReflectionProperty->isStatic() for testing.
	 *
	 * @param string $var Name of variable.
	 *
	 * @return mixed
	 */
	public function get_class_vars( $class_name, $var ) {
		$class = Singleton::get_instance( $class_name, $this );
		try {
			$prop = new \ReflectionProperty( get_class( $class ), $var );
		} catch ( \ReflectionException $Exception ) {
			die( '<table>' . $Exception->xdebug_message . '</table>' );
		}
		$static = $prop->isStatic();

		return $static ? $class::${$var} : $class->$var;
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
	 * Ensure api key is set.
	 */
	public function ensure_api_key_is_set() {
		if ( ! static::$api_key ) {
			update_site_option( 'github_updater_api_key', md5( uniqid( mt_rand(), true ) ) );
		}
	}

	/**
	 * Take remote file contents as string and parse headers.
	 *
	 * @param $contents
	 * @param $type
	 *
	 * @return array
	 */
	public function get_file_headers( $contents, $type ) {
		$all_headers            = array();
		$default_plugin_headers = array(
			'Name'        => 'Plugin Name',
			'PluginURI'   => 'Plugin URI',
			'Version'     => 'Version',
			'Description' => 'Description',
			'Author'      => 'Author',
			'AuthorURI'   => 'Author URI',
			'TextDomain'  => 'Text Domain',
			'DomainPath'  => 'Domain Path',
			'Network'     => 'Network',
		);

		$default_theme_headers = array(
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
		);

		if ( false !== strpos( $type, 'plugin' ) ) {
			$all_headers = $default_plugin_headers;
		}

		if ( false !== strpos( $type, 'theme' ) ) {
			$all_headers = $default_theme_headers;
		}

		/*
		 * Make sure we catch CR-only line endings.
		 */
		$file_data = str_replace( "\r", "\n", $contents );

		/*
		 * Merge extra headers and default headers.
		 */
		$all_headers = array_merge( self::$extra_headers, $all_headers );
		$all_headers = array_unique( $all_headers );

		foreach ( $all_headers as $field => $regex ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $file_data, $match ) && $match[1] ) {
				$all_headers[ $field ] = _cleanup_header_comment( $match[1] );
			} else {
				$all_headers[ $field ] = '';
			}
		}

		// Reduce array to only headers with data.
		$all_headers = array_filter( $all_headers,
			function( $e ) {
				return ! empty( $e );
			} );

		return $all_headers;
	}

	/**
	 * Function to check if plugin or theme object is able to be updated.
	 *
	 * @param $type
	 *
	 * @return bool
	 */
	public function can_update_repo( $type ) {
		global $wp_version;

		if ( isset( $type->remote_version, $type->requires_php_version, $type->requires_php_version ) ) {
			$remote_is_newer = version_compare( $type->remote_version, $type->local_version, '>' );
			$wp_version_ok   = version_compare( $wp_version, $type->requires_wp_version, '>=' );
			$php_version_ok  = version_compare( PHP_VERSION, $type->requires_php_version, '>=' );
		} else {
			return false;
		}

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

		$wpdb->query( $wpdb->prepare( $delete_string, array( '%ghu-%' ) ) );

		$this->force_run_cron_job();

		return true;
	}

	/**
	 * Force wp-cron.php to run.
	 */
	public function force_run_cron_job() {
		$doing_wp_cron = sprintf( '%.22F', microtime( true ) );
		$cron_request  = array(
			'url'  => site_url( 'wp-cron.php?doing_wp_cron=' . $doing_wp_cron ),
			'args' => array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', true ),
			),
		);

		wp_remote_post( $cron_request['url'], $cron_request['args'] );
	}

	/**
	 * Checks if dupicate wp-cron event exists.
	 *
	 * @param string $event Name of wp-cron event.
	 *
	 * @return bool
	 */
	public function is_duplicate_wp_cron_event( $event ) {
		$cron = _get_cron_array();
		foreach ( $cron as $timestamp => $cronhooks ) {
			if ( $event === key( $cronhooks ) ) {
				$this->is_cron_overdue( $cron, $timestamp );

				return true;
			}
		}

		return false;
	}

	/**
	 * Check to see if wp-cron event is overdue by 24 hours and report error message.
	 *
	 * @param $cron
	 * @param $timestamp
	 */
	public function is_cron_overdue( $cron, $timestamp ) {
		$overdue = ( ( time() - $timestamp ) / HOUR_IN_SECONDS ) > 24;
		if ( $overdue ) {
			$error_msg = esc_html__( 'There may be a problem with WP-Cron. A GitHub Updater WP-Cron event is overdue.', 'github-updater' );
			$error     = new \WP_Error( 'github_updater_cron_error', $error_msg );
			Singleton::get_instance( 'Messages', $this )->create_error_message( $error );
		}
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
			return ( '0.0.0' === $repo->remote_version ) || ! empty( self::$options[ $repo->repo ] );
		}

		return false;
	}

	/**
	 * Checks to see if DOING_AJAX.
	 *
	 * @return bool
	 */
	public static function is_doing_ajax() {
		return ( defined( 'DOING_AJAX' ) && DOING_AJAX );
	}

	/**
	 * Is override dot org option active?
	 *
	 * @return bool
	 */
	public function is_override_dot_org() {
		return ( defined( 'GITHUB_UPDATER_OVERRIDE_DOT_ORG' ) && GITHUB_UPDATER_OVERRIDE_DOT_ORG )
		       || ( defined( 'GITHUB_UPDATER_EXTENDED_NAMING' ) && GITHUB_UPDATER_EXTENDED_NAMING );
	}

	/**
	 * Set array with normal repo names.
	 * Fix name even if installed without renaming originally, eg <repo>-master
	 *
	 * @param string            $slug
	 * @param Base|Plugin|Theme $upgrader_object
	 *
	 * @return array
	 */
	protected function get_repo_slugs( $slug, $upgrader_object = null ) {
		$arr    = array();
		$rename = explode( '-', $slug );
		array_pop( $rename );
		$rename = implode( '-', $rename );

		if ( null === $upgrader_object ) {
			$upgrader_object = $this;
		}

		$rename = isset( $upgrader_object->config[ $slug ] ) ? $slug : $rename;
		foreach ( (array) $upgrader_object->config as $repo ) {
			if ( $slug === $repo->repo || $rename === $repo->repo ) {
				$arr['repo'] = $repo->repo;
				break;
			}
		}

		return $arr;
	}

	/**
	 * Get filename of changelog and return.
	 *
	 * @param $type
	 *
	 * @return bool|string
	 */
	protected function get_changelog_filename( $type ) {
		$changelogs  = array( 'CHANGES.md', 'CHANGELOG.md', 'changes.md', 'changelog.md' );
		$changes     = null;
		$local_files = null;

		if ( is_dir( $this->$type->local_path ) ) {
			$local_files = scandir( $this->$type->local_path, 0 );
		}

		$changes = array_intersect( (array) $local_files, $changelogs );
		$changes = array_pop( $changes );

		if ( ! empty( $changes ) ) {
			return $changes;
		}

		return false;
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
		$header['scheme']     = isset( $header_parts['scheme'] ) ? $header_parts['scheme'] : null;
		$header['host']       = isset( $header_parts['host'] ) ? $header_parts['host'] : null;
		$header['owner']      = trim( $header_path['dirname'], '/' );
		$header['repo']       = $header_path['filename'];
		$header['owner_repo'] = implode( '/', array( $header['owner'], $header['repo'] ) );
		$header['base_uri']   = str_replace( $header_parts['path'], '', $repo_header );
		$header['uri']        = isset( $header['scheme'] ) ? trim( $repo_header, '/' ) : null;

		$header = Settings::sanitize( $header );

		return $header;
	}

}
