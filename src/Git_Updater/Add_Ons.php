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
	 * Add_Ons constructor.
	 */
	public function __construct() {

	}

	/**
	 * Load needed action/filter hooks.
	 */
	public function load_hooks() {
		add_action( 'admin_init', [ $this, 'addons_page_init' ] );

		$this->add_settings_tabs();
	}

	/**
	 * Adds Remote Management tab to Settings page.
	 */
	public function add_settings_tabs() {
		$install_tabs = [ 'git_updater_addons' => esc_html__( 'Add-Ons', 'git-updater' ) ];
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
		if ( 'git_updater_addons' === $tab ) {
			$action = add_query_arg( 'tab', $tab, $action );
			$this->admin_page_notices(); ?>
			<form class="settings" method="post" action="<?php esc_attr_e( $action ); ?>">
				<?php do_settings_sections( 'git_updater_addons_settings' ); ?>
			</form>
			<?php
			$this->insert_cards( $action );
		}
	}

	/**
	 * Display appropriate notice for Remote Management page action.
	 */
	private function admin_page_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$display = isset( $_GET['install_api_plugin'] ) && '1' === $_GET['install_api_plugin'];
		if ( $display ) {
			echo '<div class="updated"><p>';
			esc_html_e( 'Git Updater API plugin installed.', 'git-updater' );
			echo '</p></div>';
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
			esc_html__( 'Add-Ons', 'git-updater' ),
			[ $this, 'print_section_addons' ],
			'git_updater_addons_settings'
		);
	}

	/**
	 * Print the Add Ons text.
	 *
	 * @return void
	 */
	public function print_section_addons() {
		echo '<p>';
		esc_html_e( 'Install additional API plugins.', 'git-updater' );
		echo '</p>';
	}

	/**
	 * Some method to insert cards for API plugin installation.
	 *
	 * @param string $action URL for form action.
	 * @return void
	 */
	public function insert_cards( $action ) {
		echo '<p>';
		esc_html_e( 'Add table of cards for API plugins.', 'git-updater' );
		echo '</p>';

		$install_gist = add_query_arg( [ 'install_api_plugin' => 'gist' ], $action );
		?>
		<form class="settings no-sub-tabs" method="post" action="<?php esc_attr_e( $install_gist ); ?>">
			<?php submit_button( esc_html__( 'Install Gist', 'git-updater' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Install Git Updater API plugins.
	 *
	 * @uses afragen/wp-dependency-installer
	 *
	 * @return bool
	 */
	public function install_api_plugin() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['install_api_plugin'] ) ) {
			$_POST = $_REQUEST;
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$_POST['_wp_http_referer'] = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : null;

			if ( 'gist' === $_GET['install_api_plugin'] ) {
			// phpcs:enable
				$gist_api = [
					[
						'name'     => 'Git Updater - Gist',
						'host'     => 'github',
						'slug'     => 'git-updater-gist/git-updater-gist.php',
						'uri'      => 'afragen/git-updater-gist',
						'branch'   => 'main',
						'required' => true,
					],
				];
				\WP_Dependency_Installer::instance( __DIR__ )->register( $gist_api )->run()->admin_init();
				return true;
			}
		}
		return false;
	}
}
