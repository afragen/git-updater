<?php
/**
 * GitHub Updater
 *
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 * @package   github-updater
 */

namespace Fragen\GitHub_Updater;

use Fragen\GitHub_Updater\Traits\GHU_Trait;

/**
 * Class Remote_Management
 */
class Remote_Management {
	use GHU_Trait;

	/**
	 * Holds the values for remote management settings.
	 *
	 * @var mixed
	 */
	public static $options_remote;
	/**
	 * Supported remote management services.
	 *
	 * @var array
	 */
	public static $remote_management = [
		'ithemes_sync' => 'iThemes Sync',
		'infinitewp'   => 'InfiniteWP',
		'managewp'     => 'ManageWP',
		'mainwp'       => 'MainWP',
	];
	/**
	 * Holds the value for the Remote Management API key.
	 *
	 * @var
	 */
	private static $api_key;

	/**
	 * Remote_Management constructor.
	 */
	public function __construct() {
		$this->load_options();
		$this->ensure_api_key_is_set();
		$this->load_hooks();
	}

	/**
	 * Load site options.
	 */
	private function load_options() {
		self::$options_remote = get_site_option( 'github_updater_remote_management', [] );
		self::$api_key        = get_site_option( 'github_updater_api_key' );
	}

	/**
	 * Ensure api key is set.
	 */
	public function ensure_api_key_is_set() {
		if ( ! self::$api_key ) {
			update_site_option( 'github_updater_api_key', md5( uniqid( mt_rand(), true ) ) );
		}
	}

	/**
	 * Load needed action/filter hooks.
	 */
	private function load_hooks() {
		add_action( 'admin_init', [ $this, 'remote_management_page_init' ] );
		add_action(
			'github_updater_update_settings',
			function ( $post_data ) {
				$this->save_settings( $post_data );
			}
		);
		add_filter( 'github_updater_add_admin_pages', [ $this, 'extra_admin_pages' ] );
		$this->add_settings_tabs();
	}

	/**
	 * Return list of pages where GitHub Updater loads/runs.
	 *
	 * @param array $admin_pages Default list of pages where GitHub Updater loads.
	 *
	 * @return array $admin_pages
	 */
	public function extra_admin_pages( $admin_pages ) {
		$extra_admin_pages = [];
		foreach ( array_keys( self::$remote_management ) as $key ) {
			if ( ! empty( self::$options_remote[ $key ] ) ) {
				$extra_admin_pages = [ 'index.php' ];
				break;
			}
		}

		return array_merge( $admin_pages, $extra_admin_pages );
	}

	/**
	 * Save Remote Management settings.
	 *
	 * @uses 'github_updater_update_settings' action hook
	 * @uses 'github_updater_save_redirect' filter hook
	 *
	 * @param $post_data
	 */
	public function save_settings( $post_data ) {
		if ( isset( $post_data['option_page'] ) &&
			'github_updater_remote_management' === $post_data['option_page']
		) {
			$options = isset( $post_data['github_updater_remote_management'] )
				? $post_data['github_updater_remote_management']
				: [];

			update_site_option( 'github_updater_remote_management', (array) $this->sanitize( $options ) );

			add_filter(
				'github_updater_save_redirect',
				function ( $option_page ) {
					return array_merge( $option_page, [ 'github_updater_remote_management' ] );
				}
			);
		}
	}

	/**
	 * Adds Remote Management tab to Settings page.
	 */
	public function add_settings_tabs() {
		$install_tabs = [ 'github_updater_remote_management' => esc_html__( 'Remote Management', 'github-updater' ) ];
		add_filter(
			'github_updater_add_settings_tabs',
			function ( $tabs ) use ( $install_tabs ) {
				return array_merge( $tabs, $install_tabs );
			}
		);
		add_filter(
			'github_updater_add_admin_page',
			function ( $tab, $action ) {
				$this->add_admin_page( $tab, $action );
			},
			10,
			2
		);
	}

	/**
	 * Add Settings page data via action hook.
	 *
	 * @uses 'github_updater_add_admin_page' action hook
	 *
	 * @param $tab
	 * @param $action
	 */
	public function add_admin_page( $tab, $action ) {
		if ( 'github_updater_remote_management' === $tab ) {
			$action = add_query_arg( 'tab', $tab, $action ); ?>
			<form class="settings" method="post" action="<?php esc_attr_e( $action ); ?>">
				<?php
				settings_fields( 'github_updater_remote_management' );
				do_settings_sections( 'github_updater_remote_settings' );
				submit_button();
				?>
			</form>
			<?php
			$reset_api_action = add_query_arg( [ 'github_updater_reset_api_key' => true ], $action );
			?>
			<form class="settings no-sub-tabs" method="post" action="<?php esc_attr_e( $reset_api_action ); ?>">
				<?php submit_button( esc_html__( 'Reset RESTful key', 'github-updater' ) ); ?>
			</form>
			<?php
		}
	}

	/**
	 * Settings for Remote Management.
	 */
	public function remote_management_page_init() {
		register_setting(
			'github_updater_remote_management',
			'github_updater_remote_settings',
			[ $this, 'sanitize' ]
		);

		add_settings_section(
			'remote_management',
			esc_html__( 'Remote Management', 'github-updater' ),
			[ $this, 'print_section_remote_management' ],
			'github_updater_remote_settings'
		);

		foreach ( self::$remote_management as $id => $name ) {
			add_settings_field(
				$id,
				null,
				[ $this, 'token_callback_checkbox_remote' ],
				'github_updater_remote_settings',
				'remote_management',
				[
					'id'    => $id,
					'title' => esc_html( $name ),
				]
			);
		}
	}

	/**
	 * Print the Remote Management text.
	 */
	public function print_section_remote_management() {
		if ( empty( self::$api_key ) ) {
			$this->load_options();
		}
		$api_url = add_query_arg(
			[
				'action' => 'github-updater-update',
				'key'    => self::$api_key,
			],
			admin_url( 'admin-ajax.php' )
		);
		?>
		<p>
			<?php esc_html_e( 'Please refer to README for complete list of attributes. RESTful endpoints begin at:', 'github-updater' ); ?>
			<br>
			<span style="font-family:monospace;"><?php echo $api_url; ?></span>
		<p>
			<?php esc_html_e( 'Use of Remote Management services may result increase some page load speeds only for `admin` level users in the dashboard.', 'github-updater' ); ?>
		</p>
		<?php
	}

	/**
	 * Get the settings option array and print one of its values.
	 * For remote management settings.
	 *
	 * @param $args
	 *
	 * @return bool|void
	 */
	public function token_callback_checkbox_remote( $args ) {
		$checked = isset( self::$options_remote[ $args['id'] ] ) ? self::$options_remote[ $args['id'] ] : null;
		?>
		<label for="<?php esc_attr_e( $args['id'] ); ?>">
			<input type="checkbox" name="github_updater_remote_management[<?php esc_attr_e( $args['id'] ); ?>]" value="1" <?php checked( '1', $checked ); ?> >
			<?php echo $args['title']; ?>
		</label>
		<?php
	}

	/**
	 * Reset RESTful API key.
	 * Deleting site option will cause it to be re-created.
	 *
	 * @return bool
	 */
	public function reset_api_key() {
		if ( isset( $_REQUEST['tab'], $_REQUEST['github_updater_reset_api_key'] ) &&
			'github_updater_remote_management' === $_REQUEST['tab']
		) {
			$_POST                     = $_REQUEST;
			$_POST['_wp_http_referer'] = $_SERVER['HTTP_REFERER'];
			delete_site_option( 'github_updater_api_key' );

			return true;
		}

		return false;
	}
}
