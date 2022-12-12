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
 * Class Remote_Management
 */
class Remote_Management {
	use GU_Trait;

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
		self::$api_key = get_site_option( 'git_updater_api_key' );
		$this->ensure_api_key_is_set();
	}

	/**
	 * Ensure api key is set.
	 */
	public function ensure_api_key_is_set() {
		if ( ! self::$api_key ) {
			update_site_option( 'git_updater_api_key', md5( uniqid( \wp_rand(), true ) ) );
		}
	}

	/**
	 * Initialize.
	 */
	public function init() {
		$this->remote_management_page_init();
		$this->add_settings_tabs();
	}

	/**
	 * Adds Remote Management tab to Settings page.
	 */
	public function add_settings_tabs() {
		$install_tabs = [ 'git_updater_remote_management' => esc_html__( 'Remote Management', 'git-updater-pro' ) ];
		add_filter(
			'gu_add_settings_tabs',
			function ( $tabs ) use ( $install_tabs ) {
				return array_merge( $tabs, $install_tabs );
			}
		);
		add_filter(
			'gu_add_admin_page',
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
	 * @uses 'gu_add_admin_page' action hook
	 *
	 * @param string $tab    Tab name.
	 * @param string $action Form action.
	 */
	public function add_admin_page( $tab, $action ) {
		if ( 'git_updater_remote_management' === $tab ) {
			$action = add_query_arg( 'tab', $tab, $action );
			$this->admin_page_notices(); ?>
			<form class="settings" method="post" action="<?php echo esc_attr( $action ); ?>">
				<?php do_settings_sections( 'git_updater_remote_settings' ); ?>
			</form>
			<?php $reset_api_action = add_query_arg( [ 'git_updater_reset_api_key' => true ], $action ); ?>
			<form class="settings no-sub-tabs" method="post" action="<?php echo esc_attr( $reset_api_action ); ?>">
				<?php submit_button( esc_html__( 'Reset REST API key', 'git-updater-pro' ) ); ?>
			</form>
			<?php
		}
	}

	/**
	 * Display appropriate notice for Remote Management page action.
	 */
	private function admin_page_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$display = isset( $_GET['reset'] ) && '1' === $_GET['reset'];
		if ( $display ) {
			echo '<div class="updated"><p>';
			esc_html_e( 'REST API key reset.', 'git-updater-pro' );
			echo '</p></div>';
		}
	}

	/**
	 * Settings for Remote Management.
	 */
	public function remote_management_page_init() {
		register_setting(
			'git_updater_remote_management',
			'git_updater_remote_settings',
			[ $this, 'sanitize' ]
		);

		add_settings_section(
			'remote_management',
			esc_html__( 'Remote Management', 'git-updater-pro' ),
			[ $this, 'print_section_remote_management' ],
			'git_updater_remote_settings'
		);
	}

	/**
	 * Print the Remote Management text.
	 */
	public function print_section_remote_management() {
		if ( empty( self::$api_key ) ) {
			$this->load_options();
		}
		$update_endpoint       = add_query_arg(
			[ 'key' => self::$api_key ],
			home_url( 'wp-json/' . $this->get_class_vars( 'REST\REST_API', 'namespace' ) . '/update/' )
		);
		$branch_reset_endpoint = add_query_arg(
			[ 'key' => self::$api_key ],
			home_url( 'wp-json/' . $this->get_class_vars( 'REST\REST_API', 'namespace' ) . '/reset-branch/' )
		);

		echo '<p>';
		esc_html_e( 'Remote Management services should just work for plugins like MainWP, ManageWP, InfiniteWP, iThemes Sync and others.', 'git-updater-pro' );
		echo '</p>';

		echo '<p>';
		printf(
			wp_kses_post(
				/* translators: %s: Link to Git Remote Updater repository */
				__( 'The <a href="%s">Git Remote Updater</a> plugin was specifically created to make the remote management of Git Updater supported plugins and themes much simpler. You will need the Site URL and REST API key to use with Git Remote Updater settings.', 'git-updater-pro' )
			),
			'https://git-updater.com/knowledge-base/git-remote-updater/'
		);
		echo '</p>';

		echo '<p>';
		printf(
			wp_kses_post(
				/* translators: 1: home URL, 2: REST API key */
				__( 'Site URL: %1$s<br> REST API key: %2$s', 'git-updater-pro' )
			),
			'<span style="font-family:monospace;">' . esc_url( home_url() ) . '</span>',
			'<span style="font-family:monospace;">' . esc_attr( self::$api_key ) . '</span>'
		);
		echo '</p>';

		echo '<p>';
		printf(
			wp_kses_post(
				/* translators: 1: Link to wiki, 2: RESTful API URL */
				__( 'Please refer to the <a href="%s">Git Updater Knowledge Base</a> for complete list of attributes.', 'git-updater-pro' )
			),
			'https://git-updater.com/knowledge-base/remote-management-restful-endpoints/'
		);
		echo '</p>';

		echo '<p>';
		printf(
			wp_kses_post(
				/* translators: link to REST API endpoint for updating */
				__( 'REST API endpoints for webhook updating begin at: %s', 'git-updater-pro' )
			),
			'<br><span style="font-family:monospace;">' . esc_url( $update_endpoint ) . '</span>'
		);
		echo '</p>';

		echo '<p>';
		printf(
			wp_kses_post(
				/* translators: link to REST API endpoint for branch resetting */
				__( 'REST API endpoints for webhook branch resetting begin at: %s', 'git-updater-pro' )
			),
			'<br><span style="font-family:monospace;">' . esc_url( $branch_reset_endpoint ) . '</span>'
		);
		echo '</p>';
	}

	/**
	 * Reset RESTful API key.
	 * Deleting site option will cause it to be re-created.
	 *
	 * @return bool
	 */
	public function reset_api_key() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['tab'], $_REQUEST['git_updater_reset_api_key'] )
			&& 'git_updater_remote_management' === sanitize_title_with_dashes( wp_unslash( $_REQUEST['tab'] ) )
		) {
			$_POST = $_REQUEST;
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$_POST['_wp_http_referer'] = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : null;
			// phpcs:enable
			delete_site_option( 'git_updater_api_key' );

			return true;
		}

		return false;
	}
}
