<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater;

use Fragen\Git_Updater\Traits\GU_Trait;

/**
 * Class Add_Ons
 */
class Add_Ons {
	use GU_Trait;

	/**
	 * Holds add-on slugs.
	 *
	 * @var string[]
	 */
	protected static $addons = [
		'git-updater-gist',
		'git-updater-bitbucket',
		'git-updater-gitlab',
		'git-updater-gitea',
	];

	/**
	 * Stored repo data.
	 *
	 * @var \stdClass
	 */
	protected $response;

	/**
	 * Load needed action/filter hooks.
	 */
	public function load_hooks() {
		add_action( 'admin_init', [ $this, 'addons_page_init' ] );
		add_filter( 'plugins_api', [ $this, 'plugins_api' ], 99, 3 );
		add_filter( 'upgrader_source_selection', [ $this, 'upgrader_source_selection' ], 10, 4 );

		$this->add_settings_tabs();
	}

	/**
	 * Adds Remote Management tab to Settings page.
	 */
	public function add_settings_tabs() {
		$install_tabs = [ 'git_updater_addons' => esc_html__( 'API Add-Ons', 'git-updater' ) ];
		add_filter(
			'gu_add_settings_tabs',
			function ( $tabs ) use ( $install_tabs ) {
				return array_merge( $tabs, $install_tabs );
			}
		);
		add_filter(
			'gu_add_admin_page',
			function ( $tab ) {
				$this->add_admin_page( $tab );
			},
			10,
			2
		);
	}

	/**
	 * Add Settings page data via action hook.
	 *
	 * @uses 'gu_add_admin_page' action hook
	 *
	 * @param string $tab    Tab name.
	 */
	public function add_admin_page( $tab ) {
		if ( 'git_updater_addons' === $tab ) {
			wp_enqueue_script( 'plugin-install' );
			wp_enqueue_script( 'updates' );
			add_thickbox();
			do_settings_sections( 'git_updater_addons_settings' );
		}
	}

	/**
	 * Settings for Add Ons.
	 */
	public function addons_page_init() {
		register_setting(
			'git_updater_addons',
			'git_updater_addons_settings',
			[ $this, 'sanitize' ]
		);

		add_settings_section(
			'addons',
			esc_html__( 'API Add-Ons', 'git-updater' ),
			[ $this, 'insert_cards' ],
			'git_updater_addons_settings'
		);
	}

	/**
	 * Some method to insert cards for API plugin installation.
	 *
	 * @return void
	 */
	public function insert_cards() {
		global $tab;
		$tab = ''; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		if ( ! function_exists( 'wp_get_plugin_action_button' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}

		$wp_list_table        = _get_list_table( 'WP_Plugin_Install_List_Table' );
		$wp_list_table->items = $this->get_addon_api_results();

		echo '</div><form id="plugin-filter" class="git-updater-addons" method="post">';
		$wp_list_table->display();
		echo '</form';
	}

	/**
	 * Filters the plugins API result for an add-on.
	 *
	 * @param object|WP_Error $result Response object or WP_Error.
	 * @param string          $action The action being taken.
	 * @param object          $args   API arguments.
	 * @return array|object The original result or the modified result as an object.
	 */
	public function plugins_api( $result, $action, $args ) {
		if ( in_array( $args->slug, static::$addons, true ) ) {
			$results = (array) $this->get_addon_api_results();

			if ( isset( $results[ $args->slug ] ) ) {
				$result = (object) $results[ $args->slug ];
			}
		}

		return $result;
	}

	/**
	 * Gets API results for the add-ons.
	 *
	 * The results are cached.
	 *
	 * @return array An array of API results.
	 */
	public function get_addon_api_results() {
		$api_results = $this->get_repo_cache( 'gu_addon_api_results' );

		if ( false === $api_results ) {
			$api_results = [];
			$api_url     = 'https://git-updater.com/wp-json/git-updater/v1/plugins-api/?slug=';

			foreach ( self::$addons as $addon ) {
				$response = wp_remote_post( "{$api_url}{$addon}" );

				if ( 200 !== wp_remote_retrieve_response_code( $response ) || is_wp_error( $response ) ) {
					continue;
				}

				$api_results[ $addon ] = json_decode( wp_remote_retrieve_body( $response ), true );
			}
			$this->set_repo_cache( 'gu_addon_api_results', $api_results, 'gu_addon_api_results', '+24 hours' );
		}

		return isset( $api_results['timeout'] ) ? $api_results['gu_addon_api_results'] : $api_results;
	}

	/**
	 * Correctly rename addon for activation.
	 *
	 * @param string                           $source        Path fo $source.
	 * @param string                           $remote_source Path of $remote_source.
	 * @param \Plugin_Upgrader|\Theme_Upgrader $upgrader      An Upgrader object.
	 * @param array                            $hook_extra    Array of hook data.
	 *
	 * @return string|\WP_Error
	 */
	public function upgrader_source_selection( string $source, string $remote_source, \Plugin_Upgrader|\Theme_Upgrader $upgrader, $hook_extra = null ) {
		global $wp_filesystem;

		$new_source = $source;

		foreach ( self::$addons as $addon ) {
			if ( str_contains( $source, $addon ) ) {
				$hook_extra['plugin'] = "{$addon}/{$addon}.php";
				break;
			}
		}

		// Rename plugins.
		if ( $upgrader instanceof \Plugin_Upgrader ) {
			if ( isset( $hook_extra['plugin'] ) ) {
				$slug       = dirname( $hook_extra['plugin'] );
				$new_source = trailingslashit( $remote_source ) . $slug;
			}
		}

		if ( basename( $source ) === $slug ) {
			return $source;
		}

		if ( trailingslashit( strtolower( $source ) ) !== trailingslashit( strtolower( $new_source ) ) ) {
			$wp_filesystem->move( $source, $new_source, true );
		}

		return trailingslashit( $new_source );
	}
}
