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
use Fragen\Singleton;

/**
 * Class Add_Ons
 */
class Add_Ons {
	use GU_Trait;

	/**
	 * Holds Add-on config data.
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * Add_Ons constructor.
	 */
	public function __construct() {
		$this->config = $this->load_addon_config();
	}

	/**
	 * Load add-on config data.
	 *
	 * @return array
	 */
	protected function load_addon_config() {
		$config = [
			'gist'      => [
				[
					'name'     => 'Git Updater - Gist',
					'host'     => 'github',
					'slug'     => 'git-updater-gist/git-updater-gist.php',
					'uri'      => 'afragen/git-updater-gist',
					'branch'   => 'main',
					'required' => true,
					'api'      => 'gist',
				],
			],
			'bitbucket' => [
				[
					'name'     => 'Git Updater - Bitbucket',
					'host'     => 'github',
					'slug'     => 'git-updater-bitbucket/git-updater-bitbucket.php',
					'uri'      => 'afragen/git-updater-bitbucket',
					'branch'   => 'main',
					'required' => true,
					'api'      => 'bitbucket',
				],
			],
			'gitlab'    => [
				[
					'name'     => 'Git Updater - GitLab',
					'host'     => 'github',
					'slug'     => 'git-updater-gitlab/git-updater-gitlab.php',
					'uri'      => 'afragen/git-updater-gitlab',
					'branch'   => 'main',
					'required' => true,
					'api'      => 'gitlab',
				],
			],
			'gitea'     => [
				[
					'name'     => 'Git Updater - Gitea',
					'host'     => 'github',
					'slug'     => 'git-updater-gitea/git-updater-gitea.php',
					'uri'      => 'afragen/git-updater-gitea',
					'branch'   => 'main',
					'required' => true,
					'api'      => 'gitea',
				],
			],
		];

		return $config;
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
		foreach ( $this->config as $addon ) {
			$addon = \array_pop( $addon );
			$this->make_card( $addon, $action );
		}
	}

	/**
	 * Install Git Updater API plugins.
	 *
	 * @uses afragen/wp-dependency-installer
	 *
	 * @return bool
	 */
	public function install_api_plugin() {
		$config = false;
		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_POST['install_api_plugin'] ) ) {
			$_POST = $_REQUEST;
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$_POST['_wp_http_referer'] = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : null;

			switch ( $_POST['install_api_plugin'] ) {
				case 'gist':
					$config = $this->config['gist'];
					break;
				case 'bitbucket':
					$config = $this->config['bitbucket'];
					break;
				case 'gitlab':
					$config = $this->config['gitlab'];
					break;
				case 'gitea':
					$config = $this->config['gitea'];
					break;
			}
			// phpcs:enable

			if ( $config ) {
				\WP_Dependency_Installer::instance( __DIR__ )->register( $config )->run()->admin_init();
				return true;
			}
		}
		return false;
	}


	/**
	 * Create Add-on card.
	 *
	 * @param array  $config Array of add-on config data.
	 * @param string $action URL for form action.
	 *
	 * @return void
	 */
	public function make_card( $config, $action ) {
		$config['repo']                 = basename( $config['uri'] );
		$config['owner']                = dirname( $config['uri'] );
		$repo_api                       = Singleton::get_instance( 'API\API', $this )->get_repo_api( 'github' );
		$repo_api->type->owner          = $config['owner'];
		$repo_api->type->slug           = $config['repo'];
		$repo_api->type->branch         = $config['branch'];
		$repo_api->type->type           = 'plugin';
		$repo_api->type->git            = 'github';
		$repo_api->type->enterprise     = false;
		$repo_api->type->enterprise_api = false;

		$plugin_icon_url = \plugin_dir_url( dirname( __DIR__ ) ) . 'assets/icon.svg';
		$cache           = $this->get_repo_cache( $config['repo'] );
		if ( ! $cache ) {
			$repo_api->get_remote_info( basename( $config['slug'] ) );
			$cache = $this->get_repo_cache( $config['repo'] );
		}

		?>
			<div class="git-updater plugin-card plugin-card-<?php echo sanitize_html_class( $config['repo'] ); ?>">
				<div class="plugin-card-top">
					<div class="name column-name">
						<h3>
						<?php echo esc_html( $config['name'] ); ?>
						<img src="<?php echo esc_attr( $plugin_icon_url ); ?>" class="plugin-icon" alt="" />
						</h3>
					</div>
					<div class="action-links">
					<?php $install_api = add_query_arg( [ 'install_api_plugin' => $config['api'] ], $action ); ?>
					<?php if ( \is_plugin_active( $config ) ?: 'disabled' ) : ?>
						<?php submit_button( esc_html__( 'Install & Activate', 'git-updater' ), 'disabled' ); ?>
					<?php else : ?>
						<form class="settings no-sub-tabs" method="post" action="<?php esc_attr_e( $install_api ); ?>">
							<?php submit_button( esc_html__( 'Install & Activate', 'git-updater' ) ); ?>
						</form>
					<?php endif; ?>
					</div>
					<div class="desc column-description">
						<p><?php echo esc_html( $cache[ $config['repo'] ]['Description'] ); ?></p>
						<p class="authors"><?php echo esc_html( $cache[ $config['repo'] ]['Author'] ); ?></p>
					</div>
				</div>
			</div>
		<?php
	}
}
