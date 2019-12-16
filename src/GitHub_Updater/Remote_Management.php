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
use Fragen\Singleton;

/**
 * Class Remote_Management
 */
class Remote_Management {
	use GHU_Trait;

	/**
	 * Holds the values for remote management settings.
	 *
	 * @deprecated 9.1.0
	 *
	 * @var array $option_remote
	 */
	public static $options_remote;

	/**
	 * Supported remote management services.
	 *
	 * @deprecated 9.1.0
	 *
	 * @var array $remote_management
	 */
	public static $remote_management = array(
		'ithemes_sync' => 'iThemes Sync',
		'infinitewp'   => 'InfiniteWP',
		'managewp'     => 'ManageWP',
		'mainwp'       => 'MainWP',
	);

	/**
	 * Holds the value for the Remote Management API key.
	 *
	 * @var string $api_key
	 */
	private static $api_key;

	/**
	 * Remote_Management constructor.
	 */
	public function __construct() {
		$this->load_options();
		$this->ensure_api_key_is_set();
	}

	/**
	 * Load site options.
	 */
	private function load_options() {
		self::$options_remote = get_site_option( 'github_updater_remote_management', array() );
		self::$api_key        = get_site_option( 'github_updater_api_key' );
	}

	/**
	 * Ensure api key is set.
	 */
	public function ensure_api_key_is_set() {
		if ( ! self::$api_key ) {
			update_site_option( 'github_updater_api_key', md5( uniqid( \rand(), true ) ) );
		}
	}

	/**
	 * Load needed action/filter hooks.
	 */
	public function load_hooks() {
		add_action( 'admin_init', array( $this, 'remote_management_page_init' ) );
		add_action(
			'github_updater_update_settings',
			function ( $post_data ) {
				$this->save_settings( $post_data );
			}
		);
		$this->add_settings_tabs();
	}

	/**
	 * Save Remote Management settings.
	 *
	 * @uses 'github_updater_update_settings' action hook
	 * @uses 'github_updater_save_redirect' filter hook
	 *
	 * @param array $post_data $_POST data.
	 */
	public function save_settings( $post_data ) {
		if ( isset( $post_data['option_page'] ) &&
			'github_updater_remote_management' === $post_data['option_page']
		) {
			$options = isset( $post_data['github_updater_remote_management'] )
				? $post_data['github_updater_remote_management']
				: array();

			update_site_option( 'github_updater_remote_management', (array) $this->sanitize( $options ) );

			add_filter(
				'github_updater_save_redirect',
				function ( $option_page ) {
					return array_merge( $option_page, array( 'github_updater_remote_management' ) );
				}
			);
		}
	}

	/**
	 * Adds Remote Management tab to Settings page.
	 */
	public function add_settings_tabs() {
		$install_tabs = array( 'github_updater_remote_management' => esc_html__( 'Remote Management', 'github-updater' ) );
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
	 * @param string $tab Tab name.
	 * @param string $action Form action.
	 */
	public function add_admin_page( $tab, $action ) {
		if ( 'github_updater_remote_management' === $tab ) {
			$action = add_query_arg( 'tab', $tab, $action ); ?>
			<form class="settings" method="post" action="<?php esc_attr_e( $action ); ?>">
				<?php
				// settings_fields( 'github_updater_remote_management' );
				do_settings_sections( 'github_updater_remote_settings' );
				// submit_button();
				?>
			</form>
			<?php
			$reset_api_action = add_query_arg( array( 'github_updater_reset_api_key' => true ), $action );
			?>
			<form class="settings no-sub-tabs" method="post" action="<?php esc_attr_e( $reset_api_action ); ?>">
				<?php submit_button( esc_html__( 'Reset RESTful key', 'github-updater' ) ); ?>
			</form>
			<?php
			$make_json = add_query_arg( array( 'github_updater_make_json_file' => true ), $action );
			?>
			<form class="settings no-sub-tabs" method="post" action="<?php esc_attr_e( $make_json ); ?>">
				<?php submit_button( esc_html__( 'Make JSON file', 'github-updater' ) ); ?>
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
			array( $this, 'sanitize' )
		);

		add_settings_section(
			'remote_management',
			esc_html__( 'Remote Management', 'github-updater' ),
			array( $this, 'print_section_remote_management' ),
			'github_updater_remote_settings'
		);

		// @deprecated 9.1.0
		// foreach ( self::$remote_management as $id => $name ) {
		// add_settings_field(
		// $id,
		// null,
		// array( $this, 'token_callback_checkbox_remote' ),
		// 'github_updater_remote_settings',
		// 'remote_management',
		// array(
		// 'id'    => $id,
		// 'title' => esc_html( $name ),
		// )
		// );
		// }
	}

	/**
	 * Print the Remote Management text.
	 */
	public function print_section_remote_management() {
		if ( empty( self::$api_key ) ) {
			$this->load_options();
		}
		$api_url = add_query_arg(
			array( 'key' => self::$api_key ),
			home_url( 'wp-json/' . $this->get_class_vars( 'REST_API', 'namespace' ) . '/update/' )
		);

		echo '<p>';
		esc_html_e( 'Remote Management services should just work for plugins like MainWP, ManageWP, InfiniteWP, iThemes Sync and others.', 'github-updater' );
		echo '</p>';

		echo '<p>';
		printf(
			wp_kses_post(
				/* translators: %s: Link to Git Remote Updater repository */
				__( 'The <a href="%s">Git Remote Updater</a> plugin was specifically created to make the remote management of GitHub Updater supported plugins and themes much simpler.', 'github-updater' )
			),
			'https://github.com/afragen/git-remote-updater'
		);
		echo '</p>';

		echo '<p>';
			printf(
				wp_kses_post(
					/* translators: %1$s: Link to wiki, %2$s: RESTful API URL */
					__( 'Please refer to the <a href="%1$s">wiki</a> for complete list of attributes. REST API endpoints begin at: %2$s', 'github-updater' )
				),
				'https://github.com/afragen/github-updater/wiki/Remote-Management---RESTful-Endpoints',
				'<br><span style="font-family:monospace;">' . $api_url . '</span>'
			);
		echo '</p>';
	}

	/**
	 * Get the settings option array and print one of its values.
	 * For remote management settings.
	 *
	 * @param array $args Checkbox args.
	 *
	 * @return bool|void
	 */
	public function token_callback_checkbox_remote( $args ) {
		$checked = isset( self::$options_remote[ $args['id'] ] ) ? self::$options_remote[ $args['id'] ] : null;
		?>
		<label for="<?php esc_attr_e( $args['id'] ); ?>">
			<input type="checkbox" id="<?php esc_attr_e( $args['id'] ); ?>" name="github_updater_remote_management[<?php esc_attr_e( $args['id'] ); ?>]" value="1" <?php checked( '1', $checked ); ?> >
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

	/**
	 * Make JSON file for Git Bulk Updater.
	 *
	 * @return void
	 */
	public function make_json() {
		if ( ! isset( $_REQUEST['github_updater_make_json_file'] )
		) {
			return;
		}
		$site   = $_SERVER['HTTP_HOST'];
		$origin = $_SERVER['HTTP_ORIGIN'];
		$json   = array(
			'site' => array(
				'host'                 => $origin,
				'rest_namespace_route' => $this->get_class_vars( 'REST_API', 'namespace' ) . '/repos/',
				'rest_api_key'         => self::$api_key,
			),
		);

		$json      = json_encode( $json, JSON_FORCE_OBJECT );
		$file      = str_replace( '.', '-', $site ) . '.json';
		$file_path = get_temp_dir() . "/{$file}";
		$file_path = file_put_contents( $file_path, $json ) ? $file_path : false;

		// Quick check to verify that the file exists
		if ( ! $file_path ) {
			die( 'File not found' );
		}
		// Force the download
		header( 'Content-Type: application/octet-stream;' );
		header( 'Content-Disposition: attachment; filename="' . basename( $file ) . '"' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		readfile( $file_path );
		exit;
	}
}
