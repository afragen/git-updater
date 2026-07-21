<?php
/**
 * Git Updater
 *
 * @author    Andy Fragen
 * @license   GPL-3.0-or-later
 * @link      https://github.com/afragen/git-updater
 * @package   git-updater
 */

namespace Fragen\Git_Updater;

use Fragen\Git_Updater\Traits\GU_Trait;

/**
 * Class Lite_Domains
 *
 * Manages the "Lite Client Domains" settings for git-updater-lite domain validation.
 */
class Lite_Domains {
	use GU_Trait;

	/**
	 * Holds site options.
	 *
	 * @var array<string, mixed>
	 */
	private static $options;

	/**
	 * Constructor.
	 */
	public function __construct() {
		self::$options = get_site_option( 'git_updater_lite_domains', [] );
	}

	/**
	 * Load relevant action/filter hooks.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_action(
			'gu_update_settings',
			function ( $post_data ) {
				$this->save_settings( $post_data );
			}
		);
		add_action(
			'init',
			function () {
				$this->add_settings_tabs();
			}
		);
		add_action(
			'admin_init',
			function () {
				$this->page_init();
			}
		);

		add_action(
			'gu_add_admin_page',
			function ( $tab, $action ) {
				$this->add_admin_page( $tab, $action );
			},
			10,
			2
		);

		add_filter(
			'git_updater_lite_authorized_domains',
			function ( $domains, $slug ) {
				return $this->get_domains_for_slug( $slug );
			},
			10,
			2
		);
	}

	/**
	 * Save Lite Domains settings.
	 *
	 * @param array<string, mixed> $post_data $_POST data.
	 * @return void
	 */
	public function save_settings( $post_data ) {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'git_updater_lite_domains-options' ) ) {
			return;
		}

		if ( isset( $post_data['option_page'] ) && 'git_updater_lite_domains' === $post_data['option_page'] ) {
			$new_options = $post_data['git_updater_lite_domains'] ?? [];
			$sanitized   = [];

			foreach ( $new_options as $slug => $domains_string ) {
				$slug = sanitize_title_with_dashes( $slug );
				if ( empty( $slug ) ) {
					continue;
				}

				$domains_array = array_filter(
					array_map(
						function ( $domain ) {
							$domain = strtolower( trim( sanitize_text_field( $domain ) ) );
							// Remove 'www.' prefix for consistency.
							if ( str_starts_with( $domain, 'www.' ) ) {
								$domain = substr( $domain, 4 );
							}
							return $domain;
						},
						preg_split( '/[\s,]+/', $domains_string )
					)
				);

				if ( ! empty( $domains_array ) ) {
					$sanitized[ $slug ] = implode( ', ', $domains_array );
				}
			}

			update_site_option( 'git_updater_lite_domains', $sanitized );
			self::$options = $sanitized;

			add_filter(
				'gu_save_redirect',
				function ( $option_page ) {
					return array_merge( $option_page, [ 'git_updater_lite_domains' ] );
				}
			);
		}
	}

	/**
	 * Adds Lite Client Domains tab to Settings page.
	 *
	 * @return void
	 */
	public function add_settings_tabs() {
		$tabs = [ 'git_updater_lite_domains' => esc_html__( 'Lite Client Domains', 'git-updater' ) ];
		add_filter(
			'gu_add_settings_tabs',
			function ( $existing_tabs ) use ( $tabs ) {
				return array_merge( $existing_tabs, $tabs );
			},
			20,
			1
		);
	}

	/**
	 * Add Settings page data via action hook.
	 *
	 * @param string $tab    Tab name.
	 * @param string $action Form action.
	 * @return void
	 */
	public function add_admin_page( $tab, $action ) {
		if ( 'git_updater_lite_domains' === $tab ) {
			$action = add_query_arg(
				[
					'page' => 'git-updater',
					'tab'  => $tab,
				],
				$action
			);
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Lite Client Domains', 'git-updater' ); ?></h1>
				<p><?php esc_html_e( 'Configure authorized base domains for git-updater-lite updates. Subdomains (e.g., staging.example.com) are automatically accepted when a base domain (e.g., example.com) is configured.', 'git-updater' ); ?></p>

				<form class="settings" method="post" action="<?php echo esc_attr( $action ); ?>">
					<?php
					settings_fields( 'git_updater_lite_domains' );
					do_settings_sections( 'git_updater_lite_domains' );
					submit_button();
					?>
				</form>
			</div>
			<?php
		}
	}

	/**
	 * Settings initialization.
	 *
	 * @return void
	 */
	public function page_init() {
		register_setting(
			'git_updater_lite_domains',
			'git_updater_lite_domains'
		);

		add_settings_section(
			'git_updater_lite_domains_section',
			esc_html__( 'Authorized Domains', 'git-updater' ),
			[ $this, 'print_section_description' ],
			'git_updater_lite_domains'
		);

		// Get flagged slugs from Additions and cached API data.
		$flagged_slugs = $this->get_flagged_slugs();

		foreach ( $flagged_slugs as $slug ) {
			add_settings_field(
				'lite_domain_' . $slug,
				esc_html( $slug ),
				[ $this, 'callback_domain_field' ],
				'git_updater_lite_domains',
				'git_updater_lite_domains_section',
				[
					'slug'    => $slug,
					'warning' => $this->is_flagged_for_warning( $slug ),
				]
			);
		}

		// Add custom slug field.
		add_settings_field(
			'lite_domain_custom',
			esc_html__( 'Add Custom Slug', 'git-updater' ),
			[ $this, 'callback_custom_slug_field' ],
			'git_updater_lite_domains',
			'git_updater_lite_domains_section'
		);
	}

	/**
	 * Print section description.
	 *
	 * @return void
	 */
	public function print_section_description() {
		echo '<p>' . esc_html__( 'Enter comma-separated base domains for each slug. Leave blank to remove domain validation for that slug.', 'git-updater' ) . '</p>';
	}

	/**
	 * Domain field callback.
	 *
	 * @param array<string, mixed> $args Data passed from add_settings_field().
	 * @return void
	 */
	public function callback_domain_field( $args ) {
		$slug    = $args['slug'];
		$warning = $args['warning'];
		$value   = self::$options[ $slug ] ?? '';
		?>
		<label for="git_updater_lite_domains_<?php echo esc_attr( $slug ); ?>">
			<input type="text" style="width:50%;" id="git_updater_lite_domains_<?php echo esc_attr( $slug ); ?>" name="git_updater_lite_domains[<?php echo esc_attr( $slug ); ?>]" value="<?php echo esc_attr( $value ); ?>" placeholder="example.com, client-site.com">
			<?php if ( $warning ) : ?>
				<br>
				<span class="description" style="color: #d63638; font-weight: bold;">
					<?php esc_html_e( '⚠️ This repository requires authentication. Add a domain to restrict access.', 'git-updater' ); ?>
				</span>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * Custom slug field callback.
	 *
	 * @return void
	 */
	public function callback_custom_slug_field() {
		?>
		<label for="git_updater_lite_domains_custom_slug">
			<input type="text" style="width:30%;" id="git_updater_lite_domains_custom_slug" name="git_updater_lite_domains[custom_slug]" placeholder="<?php esc_attr_e( 'custom-slug', 'git-updater' ); ?>">
			<input type="text" style="width:40%;" id="git_updater_lite_domains_custom_domain" name="git_updater_lite_domains[custom_domain]" placeholder="<?php esc_attr_e( 'example.com', 'git-updater' ); ?>">
			<br>
			<span class="description">
				<?php esc_html_e( 'Enter a custom slug and its authorized domains to add it to the list.', 'git-updater' ); ?>
			</span>
		</label>
		<?php
	}

	/**
	 * Get domains for a specific slug.
	 *
	 * @param string $slug The package slug.
	 * @return array<string> Array of authorized domains.
	 */
	public function get_domains_for_slug( $slug ) {
		$domains_string = self::$options[ $slug ] ?? '';
		if ( empty( $domains_string ) ) {
			return [];
		}

		return array_filter(
			array_map(
				function ( $domain ) {
					return strtolower( trim( sanitize_text_field( $domain ) ) );
				},
				preg_split( '/[\s,]+/', $domains_string )
			)
		);
	}

	/**
	 * Get slugs that should be flagged for domain configuration.
	 *
	 * @return array<string> Array of flagged slugs.
	 */
	private function get_flagged_slugs() {
		$flagged = [];

		// 1. Check Additions for 'uses_lite' flag.
		$additions = get_site_option( 'git_updater_additions', [] );
		foreach ( $additions as $addition ) {
			if ( ! empty( $addition['uses_lite'] ) && ! empty( $addition['slug'] ) ) {
				$slug      = str_contains( $addition['type'], 'plugin' ) ? dirname( $addition['slug'] ) : $addition['slug'];
				$flagged[] = $slug;
			}
		}

		// 2. Check cached API data for private repos that are not hard-blocked
		// and have the 'Update URI' header set (indicating they are managed by Git Updater).
		$gu_plugins = \Fragen\Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this )->get_plugin_configs();
		$gu_themes  = \Fragen\Singleton::get_instance( 'Fragen\Git_Updater\Theme', $this )->get_theme_configs();
		$gu_repos   = array_merge( $gu_plugins, $gu_themes );

		foreach ( $gu_repos as $repo ) {
			if ( ! empty( $repo->is_private )
				&& empty( $repo->private_package )
				&& ! empty( $repo->update_uri )
			) {
				$flagged[] = $repo->slug;
			}
		}

		// Also include any slugs already configured in the options.
		foreach ( array_keys( self::$options ) as $configured_slug ) {
			if ( ! in_array( $configured_slug, $flagged, true ) ) {
				$flagged[] = $configured_slug;
			}
		}

		return array_unique( array_filter( $flagged ) );
	}

	/**
	 * Check if a slug should show a warning.
	 *
	 * @param string $slug The package slug.
	 * @return bool True if it's a private repo requiring authentication.
	 */
	private function is_flagged_for_warning( $slug ) {
		$gu_plugins = \Fragen\Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this )->get_plugin_configs();
		$gu_themes  = \Fragen\Singleton::get_instance( 'Fragen\Git_Updater\Theme', $this )->get_theme_configs();
		$gu_repos   = array_merge( $gu_plugins, $gu_themes );

		foreach ( $gu_repos as $repo ) {
			if ( $repo->slug === $slug && ! empty( $repo->is_private ) ) {
				return true;
			}
		}

		return false;
	}
}
