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
 * Class Lite_Clients
 *
 * Manages git-updater-lite client keys. Each client site generates its own key
 * (lazily, only when it meets a private package) and an administrator approves
 * it once, per repository, on the "Lite Clients" tab.
 */
class Lite_Clients {
	use GU_Trait;

	/**
	 * Durable site option holding approved client-key grants.
	 *
	 * Shape: [ <slug> => [ <sha256(key)> => [ 'status', 'label', 'created', 'seen' ] ] ].
	 *
	 * @var string
	 */
	private const OPTION = 'git_updater_lite_client_keys';

	/**
	 * Prefix for the per-slug pending-grant site transient.
	 *
	 * @var string
	 */
	private const PENDING_PREFIX = 'git_updater_lite_pending_';

	/**
	 * Pending-grant transient TTL, in seconds (7 days).
	 *
	 * @var int
	 */
	private const PENDING_TTL = 604800;

	/**
	 * Maximum pending grants retained per slug (anti-abuse cap).
	 *
	 * @var int
	 */
	private const PENDING_CAP = 25;

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
	}

	/**
	 * Whether a presented client key (as a SHA-256 hash) is approved for a slug.
	 *
	 * @param string $slug Package slug.
	 * @param string $hash SHA-256 hash of the presented key.
	 *
	 * @return bool
	 */
	public static function is_approved_client_key( $slug, $hash ) {
		$grants = self::get_grants();
		if ( isset( $grants[ $slug ][ $hash ]['status'] ) && 'approved' === $grants[ $slug ][ $hash ]['status'] ) {
			return true;
		}

		/**
		 * Filter to pre-approve client key(s) for a slug (server-side / headless).
		 *
		 * Return a raw key string, or an array of raw key strings.
		 *
		 * @since 14.4.3
		 *
		 * @param string|array<int, string> $keys Raw client key(s).
		 * @param string                     $slug Package slug.
		 */
		$keys = apply_filters( 'git_updater_lite_client_key', [], $slug );
		foreach ( (array) $keys as $key ) {
			if ( '' !== (string) $key && hash_equals( hash( 'sha256', (string) $key ), $hash ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Record (or refresh) a pending grant for an unknown client key.
	 *
	 * Bounded by PENDING_CAP and held in a short-lived transient so random-key
	 * spam cannot bloat the options table.
	 *
	 * @param string $slug  Package slug.
	 * @param string $hash  SHA-256 hash of the presented key.
	 * @param string $label Display-only client label.
	 *
	 * @return void
	 */
	public static function record_pending_client( $slug, $hash, $label ) {
		if ( '' === $slug || '' === $hash ) {
			return;
		}

		$transient = self::PENDING_PREFIX . md5( $slug );
		$pending   = get_site_transient( $transient );
		$pending   = is_array( $pending ) ? $pending : [];

		if ( isset( $pending[ $hash ] ) ) {
			$pending[ $hash ]['seen'] = time();
			set_site_transient( $transient, $pending, self::PENDING_TTL );
			return;
		}

		if ( count( $pending ) >= self::PENDING_CAP ) {
			uasort(
				$pending,
				static function ( $a, $b ) {
					return ( $a['seen'] ?? 0 ) <=> ( $b['seen'] ?? 0 );
				}
			);
			array_shift( $pending );
		}

		$pending[ $hash ] = [
			'label' => sanitize_text_field( (string) $label ),
			'seen'  => time(),
		];
		set_site_transient( $transient, $pending, self::PENDING_TTL );
	}

	/**
	 * Save Lite Clients changes.
	 *
	 * @param array<string, mixed> $post_data $_POST data.
	 * @return void
	 */
	public function save_settings( $post_data ) {
		if ( ! isset( $_POST['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'git_updater_lite_clients-options' )
			|| ! current_user_can( is_multisite() ? 'manage_network_options' : 'manage_options' )
		) {
			return;
		}

		if ( ! isset( $post_data['option_page'] ) || 'git_updater_lite_clients' !== $post_data['option_page'] ) {
			return;
		}

		$action = isset( $_POST['gu_lite_action'] ) ? sanitize_key( wp_unslash( $_POST['gu_lite_action'] ) ) : '';
		$slug   = isset( $_POST['gu_lite_slug'] ) ? sanitize_title_with_dashes( wp_unslash( $_POST['gu_lite_slug'] ) ) : '';
		$hash   = isset( $_POST['gu_lite_hash'] ) ? sanitize_key( wp_unslash( $_POST['gu_lite_hash'] ) ) : '';

		if ( '' === $slug || '' === $hash || ! in_array( $action, [ 'approve', 'reject', 'revoke' ], true ) ) {
			return;
		}

		$this->apply_action( $action, $slug, $hash );

		add_filter(
			'gu_save_redirect',
			function ( $option_page ) {
				return array_merge( $option_page, [ 'git_updater_lite_clients' ] );
			}
		);
	}

	/**
	 * Apply an approve/reject/revoke action to the grant stores.
	 *
	 * @param string $action approve|reject|revoke.
	 * @param string $slug   Package slug.
	 * @param string $hash   SHA-256 hash of the client key.
	 *
	 * @return void
	 */
	private function apply_action( $action, $slug, $hash ) {
		$transient = self::PENDING_PREFIX . md5( $slug );
		$pending   = get_site_transient( $transient );
		$pending   = is_array( $pending ) ? $pending : [];
		$grants    = self::get_grants();

		if ( 'approve' === $action ) {
			$label                    = isset( $pending[ $hash ]['label'] ) ? (string) $pending[ $hash ]['label'] : '';
			$grants[ $slug ][ $hash ] = [
				'status'  => 'approved',
				'label'   => $label,
				'created' => time(),
				'seen'    => time(),
			];
			unset( $pending[ $hash ] );
			update_site_option( self::OPTION, $grants );
			set_site_transient( $transient, $pending, self::PENDING_TTL );
			return;
		}

		if ( 'reject' === $action ) {
			unset( $pending[ $hash ] );
			set_site_transient( $transient, $pending, self::PENDING_TTL );
			return;
		}

		if ( 'revoke' === $action ) {
			unset( $grants[ $slug ][ $hash ] );
			if ( empty( $grants[ $slug ] ) ) {
				unset( $grants[ $slug ] );
			}
			update_site_option( self::OPTION, $grants );
		}
	}

	/**
	 * Adds Lite Clients tab to Settings page.
	 *
	 * @return void
	 */
	public function add_settings_tabs() {
		$tabs = [ 'git_updater_lite_clients' => esc_html__( 'Lite Clients', 'git-updater' ) ];
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
		if ( 'git_updater_lite_clients' !== $tab ) {
			return;
		}

		$action = add_query_arg(
			[
				'page' => 'git-updater',
				'tab'  => $tab,
			],
			$action
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Lite Clients', 'git-updater' ); ?></h1>
			<p><?php esc_html_e( 'Each git-updater-lite site generates its own key when it first requests a private package, and appears below as a pending client. Approve a client once to let it receive the private packages it requests; revoke to withdraw access. Only a hash of each key is stored.', 'git-updater' ); ?></p>
			<?php $this->render_clients_table( $action ); ?>
		</div>
		<?php
	}

	/**
	 * Render the approved and pending client tables.
	 *
	 * @param string $action Form action URL.
	 * @return void
	 */
	private function render_clients_table( $action ) {
		$slugs = $this->get_flagged_slugs();

		if ( empty( $slugs ) ) {
			echo '<p><em>' . esc_html__( 'No Lite-distributed private packages were found.', 'git-updater' ) . '</em></p>';
			return;
		}

		$grants = self::get_grants();

		foreach ( $slugs as $slug ) {
			$approved = isset( $grants[ $slug ] ) && is_array( $grants[ $slug ] ) ? $grants[ $slug ] : [];
			$pending  = get_site_transient( self::PENDING_PREFIX . md5( $slug ) );
			$pending  = is_array( $pending ) ? $pending : [];

			echo '<h2>' . esc_html( $slug );
			if ( $this->is_flagged_for_warning( $slug ) ) {
				echo ' <span class="description" style="color:#d63638;font-weight:bold;">' . esc_html__( '⚠️ private repository', 'git-updater' ) . '</span>';
			}
			echo '</h2>';

			$this->render_rows( $slug, 'approved', $approved, $action );
			$this->render_rows( $slug, 'pending', $pending, $action );
		}
	}

	/**
	 * Render one grant table (approved or pending) for a slug.
	 *
	 * @param string               $slug  Package slug.
	 * @param string               $state approved|pending.
	 * @param array<string, mixed> $rows  Grant rows keyed by key hash.
	 * @param string               $action Form action URL.
	 * @return void
	 */
	private function render_rows( $slug, $state, $rows, $action ) {
		$heading = 'approved' === $state
			? __( 'Approved clients', 'git-updater' )
			: __( 'Pending requests', 'git-updater' );

		echo '<h3>' . esc_html( $heading ) . '</h3>';

		if ( empty( $rows ) ) {
			echo '<p><em>' . esc_html__( 'None.', 'git-updater' ) . '</em></p>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Client', 'git-updater' ) . '</th>';
		echo '<th>' . esc_html__( 'Key', 'git-updater' ) . '</th>';
		echo '<th>' . esc_html__( 'Seen', 'git-updater' ) . '</th>';
		echo '<th>' . esc_html__( 'Action', 'git-updater' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $hash => $row ) {
			$label   = isset( $row['label'] ) && '' !== $row['label'] ? $row['label'] : __( '(unlabeled)', 'git-updater' );
			$seen    = isset( $row['seen'] ) ? (int) $row['seen'] : 0;
			$button  = 'approved' === $state ? 'revoke' : 'approve';
			$label_t = 'revoke' === $button ? __( 'Revoke', 'git-updater' ) : __( 'Approve', 'git-updater' );

			echo '<tr>';
			echo '<td>' . esc_html( $label ) . '</td>';
			echo '<td><code>' . esc_html( substr( (string) $hash, 0, 12 ) ) . '</code></td>';
			echo '<td>' . esc_html( $seen ? wp_date( 'Y-m-d H:i', $seen ) : '—' ) . '</td>';
			echo '<td>';
			// Approve/Revoke form.
			printf( '<form method="post" action="%s" style="display:inline-block;margin-right:6px;">', esc_url( $action ) );
			settings_fields( 'git_updater_lite_clients' );
			printf( '<input type="hidden" name="gu_lite_action" value="%s" />', esc_attr( $button ) );
			printf( '<input type="hidden" name="gu_lite_slug" value="%s" />', esc_attr( $slug ) );
			printf( '<input type="hidden" name="gu_lite_hash" value="%s" />', esc_attr( (string) $hash ) );
			printf( '<button class="button">%s</button>', esc_html( $label_t ) );
			echo '</form>';

			if ( 'pending' === $state ) {
				// Reject form.
				printf( '<form method="post" action="%s" style="display:inline-block;">', esc_url( $action ) );
				settings_fields( 'git_updater_lite_clients' );
				echo '<input type="hidden" name="gu_lite_action" value="reject" />';
				printf( '<input type="hidden" name="gu_lite_slug" value="%s" />', esc_attr( $slug ) );
				printf( '<input type="hidden" name="gu_lite_hash" value="%s" />', esc_attr( (string) $hash ) );
				printf( '<button class="button">%s</button>', esc_html__( 'Reject', 'git-updater' ) );
				echo '</form>';
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Settings initialization.
	 *
	 * @return void
	 */
	public function page_init() {
		register_setting(
			'git_updater_lite_clients',
			'git_updater_lite_clients'
		);
	}

	/**
	 * Get approved grants, keyed by slug and key hash.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	private static function get_grants() {
		$grants = get_site_option( self::OPTION, [] );

		return is_array( $grants ) ? $grants : [];
	}

	/**
	 * Get slugs that should be shown on the Lite Clients tab.
	 *
	 * @return array<string> Array of slugs.
	 */
	private function get_flagged_slugs() {
		$flagged = [];

		// 1. Additions flagged 'uses_lite'.
		$additions = get_site_option( 'git_updater_additions', [] );
		foreach ( $additions as $addition ) {
			if ( ! empty( $addition['uses_lite'] ) && ! empty( $addition['slug'] ) ) {
				$slug      = str_contains( $addition['type'], 'plugin' ) ? dirname( $addition['slug'] ) : $addition['slug'];
				$flagged[] = $slug;
			}
		}

		// 2. Private repos with an Update URI header.
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

		// 3. Slugs that already have a grant or a pending request.
		$grants = self::get_grants();
		foreach ( array_keys( $grants ) as $configured_slug ) {
			$flagged[] = $configured_slug;
		}

		return array_unique( array_filter( $flagged ) );
	}

	/**
	 * Check if a slug should show a private-repo warning.
	 *
	 * @param string $slug The package slug.
	 * @return bool
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
