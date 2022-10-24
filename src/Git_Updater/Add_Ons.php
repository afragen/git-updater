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
	 * Holds free add-on config data.
	 *
	 * @var array
	 */
	protected $addon;

	/**
	 * Holds premium add-on config data.
	 *
	 * @var array
	 */
	protected $premium_addon;

	/**
	 *  Holds URL for form action.
	 *
	 * @var string
	 */
	protected $action;

	/**
	 * Add_Ons constructor.
	 */
	public function __construct() {
		$this->addon   = $this->load_addon_config();
		$this->premium = $this->load_premium_config();
		validate_active_plugins();
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
					'name'        => __( 'Git Updater - Gist', 'git-updater' ),
					'description' => __( 'Add GitHub Gist hosted repositories to the Git Updater plugin.', 'git-updater' ),
					'host'        => 'github',
					'slug'        => 'git-updater-gist/git-updater-gist.php',
					'uri'         => 'afragen/git-updater-gist',
					'branch'      => 'main',
					'required'    => true,
					'api'         => 'gist',
				],
			],
			'bitbucket' => [
				[
					'name'        => __( 'Git Updater - Bitbucket', 'git-updater' ),
					'description' => __( 'Add Bitbucket and Bitbucket Server repositories to the Git Updater plugin.', 'git-updater' ),
					'host'        => 'github',
					'slug'        => 'git-updater-bitbucket/git-updater-bitbucket.php',
					'uri'         => 'afragen/git-updater-bitbucket',
					'branch'      => 'main',
					'required'    => true,
					'api'         => 'bitbucket',
				],
			],
			'gitlab'    => [
				[
					'name'        => __( 'Git Updater - GitLab', 'git-updater' ),
					'description' => __( 'Add GitLab hosted repositories to the Git Updater plugin.', 'git-updater' ),
					'host'        => 'github',
					'slug'        => 'git-updater-gitlab/git-updater-gitlab.php',
					'uri'         => 'afragen/git-updater-gitlab',
					'branch'      => 'main',
					'required'    => true,
					'api'         => 'gitlab',
				],
			],
			'gitea'     => [
				[
					'name'        => __( 'Git Updater - Gitea', 'git-updater' ),
					'description' => __( 'Add GitLab hosted repositories to the Git Updater plugin.', 'git-updater' ),
					'host'        => 'github',
					'slug'        => 'git-updater-gitea/git-updater-gitea.php',
					'uri'         => 'afragen/git-updater-gitea',
					'branch'      => 'main',
					'required'    => true,
					'api'         => 'gitea',
				],
			],
		];

		return $config;
	}

	/**
	 * Load premium add-on data.
	 *
	 * @return array
	 */
	protected function load_premium_config() {
		$config = [
			'pro'            => [
				[
					'repo'        => 'git-updater-pro',
					'slug'        => 'git-updater-pro/git-updater-pro.php',
					'name'        => __( 'Git Updater PRO', 'git-updater' ),
					'description' => __( 'A Git Updater add-on plugin that unlocks PRO features of branch switching, remote installation of plugins and themes, REST API, Webhooks, WP-CLI, and more.', 'git-updater' ),
					'author'      => 'Andy Fragen',
					'link'        => 'https://checkout.freemius.com/mode/dialog/plugin/8282/plan/13715/?trial=paid',
				],
			],
			'additions'      => [
				[
					'repo'        => 'git-updater-additions',
					'slug'        => 'git-updater-additions/git-updater-additions.php',
					'name'        => __( 'Git Updater Additions', 'git-updater' ),
					'description' => __( 'A Git Updater add-on plugin that will add the appropriate data via hooks in Git Updater so that repositories that are not correctly configured to use Git Updater may be added to Git Updater without modifying the repository.', 'git-updater' ),
					'author'      => 'Andy Fragen',
					'link'        => 'https://checkout.freemius.com/mode/dialog/plugin/8313/plan/13771/?trial=paid',
				],
			],
			'remote-updater' => [
				[
					'repo'        => 'git-remote-updater',
					'slug'        => 'git-remote-updater/git-remote-updater.php',
					'name'        => __( 'Git Remote Updater', 'git-updater' ),
					'description' => __( 'A Git Updater add-on plugin that allows you to easily update Git Updater repositories in bulk via REST API endpoint updating. Requires Git Updater PRO.', 'git-updater' ),
					'author'      => 'Andy Fragen',
					'link'        => 'https://checkout.freemius.com/mode/dialog/plugin/8312/plan/13768/?trial=paid',
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
			$this->action = add_query_arg( 'tab', $tab, $action );
			$this->admin_page_notices();
			do_settings_sections( 'git_updater_addons_settings' );
		}
	}

	/**
	 * Display appropriate notice for Remote Management page action.
	 */
	private function admin_page_notices() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'gu_settings' ) ) {
			return;
		}
		$display = isset( $_POST['install_api_plugin'] ) && '1' === $_POST['install_api_plugin'];
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
			esc_html__( 'API Add-Ons', 'git-updater' ),
			[ $this, 'insert_cards' ],
			'git_updater_addons_settings'
		);

		/*
		 add_settings_section(
			'premium_addons',
			esc_html__( 'Premium Add-Ons', 'git-updater' ),
			[ $this, 'insert_premium_cards' ],
			'git_updater_addons_settings'
		); */
	}

	/**
	 * Some method to insert cards for API plugin installation.
	 *
	 * @return void
	 */
	public function insert_cards() {
		echo '<p>';
		esc_html_e( 'Install additional API plugins.', 'git-updater' );
		echo '</p>';
		echo '<div class="wp-list-table widefat plugin-install">';
		foreach ( $this->addon as $addon ) {
			$addon = \array_pop( $addon );
			$this->make_card( 'free', $addon );
		}
		echo '</div>';
		echo '<div style="clear:both;"></div>';
	}

	/**
	 * Some method to insert cards for premium plugin purchase.
	 *
	 * @return void
	 */
	public function insert_premium_cards() {
		echo '<p>';
		esc_html_e( 'Install premium plugins.', 'git-updater' );
		echo '</p>';
		echo '<div class="wp-list-table widefat plugin-install">';
		foreach ( $this->premium as $addon ) {
			$addon = array_pop( $addon );
			$this->make_card( 'premium', $addon );
		}
		echo '</div>';
		echo '<div style="clear:both;"></div>';
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
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['install_api_plugin'] ) ) {

			// Redirect back to the Add-Ons.
			$_POST                     = $_REQUEST;
			$_POST['_wp_http_referer'] = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : null;

			switch ( $_GET['install_api_plugin'] ) {
			//phpcs:enable
				case 'gist':
					$config = $this->addon['gist'];
					break;
				case 'bitbucket':
					$config = $this->addon['bitbucket'];
					break;
				case 'gitlab':
					$config = $this->addon['gitlab'];
					break;
				case 'gitea':
					$config = $this->addon['gitea'];
					break;
			}

			if ( $config ) {
				\WP_Dependency_Installer::instance()->register( $config )->admin_init();
				return true;
			}
		}
		return false;
	}

	/**
	 * Create Add-on card.
	 *
	 * @param string $type   Type of addon, free|premium.
	 * @param array  $config Array of add-on config data.
	 *
	 * @return void
	 */
	public function make_card( $type, $config ) {
		if ( 'free' === $type ) {
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

			$cache = $this->get_repo_cache( $config['repo'] );
			if ( ! $cache ) {
				$repo_api->get_remote_info( basename( $config['slug'] ) );
				$cache = $this->get_repo_cache( $config['repo'] );
			}
			$config['description'] = $cache[ $config['repo'] ]['Description'];
			$config['author']      = $cache[ $config['repo'] ]['Author'];
		}

		$plugin_icon_url = \plugin_dir_url( dirname( __DIR__ ) ) . 'assets/icon.svg';

		?>
		<div class="git-updater plugin-card plugin-card-<?php echo sanitize_html_class( $config['repo'] ); ?>">
			<div class="plugin-card-top">
				<div class="name column-name">
					<h3>
					<?php echo esc_html( $config['name'] ); ?>
					<img src="<?php echo esc_attr( $plugin_icon_url ); ?>" class="plugin-icon" alt="" />
					</h3>
				</div>
				<div class="desc column-description">
					<p><?php echo esc_html( $config['description'] ); ?></p>
					<p class="authors"><?php echo esc_html( $config['author'] ); ?></p>
				</div>
			</div>
			<div class="plugin-card-bottom">
				<?php
				if ( 'free' === $type ) {
					$this->free_button( $config );
				}
				if ( 'premium' === $type ) {
					$this->premium_button( $config );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get button for free add-on card.
	 *
	 * @param array $config Array of plugin data.
	 *
	 * @return void
	 */
	private function free_button( $config ) {
		$install_api = add_query_arg( [ 'install_api_plugin' => $config['api'] ], $this->action );
		if ( \is_plugin_active( $config['slug'] ) ) {
			submit_button( esc_html__( 'Install & Activate', 'git-updater' ), 'disabled' );
		} else {
			?>
			<form class="settings no-sub-tabs" method="post" action="<?php echo esc_attr( $install_api ); ?>">
				<?php submit_button( esc_html__( 'Install & Activate', 'git-updater' ) ); ?>
			</form>
			<?php
		}
	}

	/**
	 * Get button for premium add-on card.
	 *
	 * @param array $config Array of plugin data.
	 *
	 * @return void
	 */
	private function premium_button( $config ) {
		$purchase_addon = add_query_arg( [ 'purchase_premium_addon' => esc_url( wp_nonce_url( $config['link'], 'gu-freemius-premium-addon' ) ) ], $this->action );
		if ( \is_plugin_active( $config['slug'] ) ) {
			submit_button( esc_html__( 'Free Trial', 'git-updater' ), 'disabled' );
		} else {
			?>
			<form class="settings no-sub-tabs" method="post" action="<?php echo esc_attr( $purchase_addon ); ?>">
				<?php submit_button( esc_html__( 'Free Trial', 'git-updater' ) ); ?>
			</form>
			<?php
		}
	}
}
