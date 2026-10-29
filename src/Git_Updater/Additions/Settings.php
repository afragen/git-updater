<?php
/**
 * Git Updater
 *
 * @author    Andy Fragen
 * @license   GPL-3.0-or-later
 * @link      https://github.com/afragen/git-updater
 * @package   git-updater
 */

namespace Fragen\Git_Updater\Additions;

/**
 * Class Settings
 */
class Settings {
	/**
	 * Holds the values for additions settings.
	 *
	 * @var array<int, array<string, mixed>> $options_additions
	 */
	public static $options_additions;

	/**
	 * Supported types.
	 *
	 * @var array<int, string> $addition_types
	 */
	public static $addition_types = [
		'github_plugin',
		'github_theme',
	];

	/**
	 * Settings constructor.
	 */
	public function __construct() {
		$this->load_options();
	}

	/**
	 * Load site options.
	 *
	 * @return void
	 */
	private function load_options() {
		self::$options_additions = get_site_option( 'git_updater_additions', [] );
	}

	/**
	 * Load needed action/filter hooks.
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
			'gu_add_admin_page',
			function ( $tab, $action ) {
				$this->add_admin_page( $tab, $action );
			},
			10,
			2
		);

		add_action( 'wp_ajax_gu_additions_quick_edit', [ $this, 'ajax_quick_edit' ] );
		add_action( 'wp_ajax_gu_additions_refresh', [ $this, 'ajax_refresh' ] );
	}

	/**
	 * AJAX handler for saving a Quick Edit of an addition.
	 *
	 * @uses 'wp_ajax_gu_additions_quick_edit' action hook
	 *
	 * @return void
	 */
	public function ajax_quick_edit() {
		check_ajax_referer( 'gu_additions_quick_edit' );

		if ( ! current_user_can( is_multisite() ? 'manage_network_options' : 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Sorry, you are not allowed to edit additions.', 'git-updater' ) ], 403 );
		}

		$original_id = isset( $_POST['original_id'] ) ? sanitize_key( wp_unslash( $_POST['original_id'] ) ) : '';
		$input       = isset( $_POST['git_updater_additions'] ) && is_array( $_POST['git_updater_additions'] )
			? wp_unslash( $_POST['git_updater_additions'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in update_addition().
			: [];

		$addition = $this->update_addition( $original_id, $input );
		if ( is_wp_error( $addition ) ) {
			wp_send_json_error( [ 'message' => $addition->get_error_message() ] );
		}

		wp_send_json_success( [ 'row' => ( new Repo_List_Table( self::$options_additions ) )->render_row( $addition ) ] );
	}

	/**
	 * AJAX handler for refreshing an addition's repo cache from the git host.
	 *
	 * @uses 'wp_ajax_gu_additions_refresh' action hook
	 *
	 * @return void
	 */
	public function ajax_refresh() {
		check_ajax_referer( 'gu_additions_refresh' );

		if ( ! current_user_can( is_multisite() ? 'manage_network_options' : 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Sorry, you are not allowed to refresh additions.', 'git-updater' ) ], 403 );
		}

		$id       = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
		$addition = null;
		foreach ( (array) get_site_option( 'git_updater_additions', [] ) as $option ) {
			if ( isset( $option['ID'] ) && $id === $option['ID'] ) {
				$addition = $option;
				break;
			}
		}
		if ( null === $addition ) {
			wp_send_json_error( [ 'message' => esc_html__( 'This addition no longer exists.', 'git-updater' ) ] );
		}

		$result = $this->refresh_addition( $addition );
		$row    = ( new Repo_List_Table( self::$options_additions ) )->render_row( $addition );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				[
					'message' => $result->get_error_message(),
					'row'     => $row,
				]
			);
		}

		wp_send_json_success( [ 'row' => $row ] );
	}

	/**
	 * Flush an addition's repo cache and refetch it from the git host.
	 *
	 * @param array<string, mixed> $addition A saved addition.
	 *
	 * @return true|\WP_Error True when the fetch cycle completed.
	 */
	public function refresh_addition( $addition ) {
		// The repo cache and repo configs are keyed by repository name, the last segment of the URI.
		$repo    = basename( (string) ( $addition['uri'] ?? '' ) );
		$configs = str_contains( (string) ( $addition['type'] ?? '' ), 'theme' )
			? \Fragen\Singleton::get_instance( 'Fragen\Git_Updater\Theme', $this )->get_theme_configs()
			: \Fragen\Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this )->get_plugin_configs();

		if ( '' === $repo || ! isset( $configs[ $repo ] ) ) {
			return new \WP_Error( 'gu_addition_not_loaded', esc_html__( 'Git Updater has not loaded this repository. Check its slug and URL.', 'git-updater' ) );
		}

		add_filter( 'gu_disable_wpcron', '__return_false' );
		$complete = \Fragen\Singleton::get_instance( 'Fragen\Git_Updater\Base', $this )->refresh_repo( $configs[ $repo ] );
		remove_filter( 'gu_disable_wpcron', '__return_false' );

		if ( ! $complete ) {
			return new \WP_Error( 'gu_addition_refresh_incomplete', esc_html__( 'Could not fetch everything from the git host. Try again in a few minutes.', 'git-updater' ) );
		}

		return true;
	}

	/**
	 * Validate and save changes to an existing addition.
	 *
	 * Flushes the repo cache for both the previous and the updated repository
	 * so a changed URI or primary branch takes effect on the next update check.
	 *
	 * @param string               $original_id ID of the addition being edited.
	 * @param array<string, mixed> $input       Submitted field values.
	 *
	 * @return array<string, mixed>|\WP_Error The saved addition, or an error.
	 */
	public function update_addition( $original_id, $input ) {
		$options = (array) get_site_option( 'git_updater_additions', [] );
		$key     = null;
		foreach ( $options as $option_key => $option ) {
			if ( isset( $option['ID'] ) && $original_id === $option['ID'] ) {
				$key = $option_key;
				break;
			}
		}
		if ( null === $key ) {
			return new \WP_Error( 'gu_addition_not_found', esc_html__( 'This addition no longer exists. Reload the page and try again.', 'git-updater' ) );
		}
		$original = $options[ $key ];

		$fields = [ 'type', 'slug', 'uri', 'primary_branch', 'release_asset', 'private_package', 'uses_lite' ];
		$input  = array_filter( array_intersect_key( (array) $input, array_flip( $fields ) ), 'is_scalar' );
		$input  = array_merge(
			[
				'type' => '',
				'slug' => '',
				'uri'  => '',
			],
			$input
		);

		$new                  = $this->sanitize( $input )[0];
		$new['release_asset'] = ! empty( $new['release_asset'] );
		$new['source']        = $original['source'] ?? $new['source'];

		if ( empty( $new['slug'] ) || empty( $new['uri'] ) ) {
			return new \WP_Error( 'gu_addition_required', esc_html__( 'Repository slug and URI are required.', 'git-updater' ) );
		}

		$types = (array) apply_filters( 'gua_addition_types', self::$addition_types );
		if ( ! in_array( $new['type'], $types, true ) && ( $original['type'] ?? null ) !== $new['type'] ) {
			return new \WP_Error( 'gu_addition_type', esc_html__( 'Invalid repository type.', 'git-updater' ) );
		}

		$is_plugin_slug = str_contains( $new['slug'], '/' );
		$is_plugin_type = str_contains( $new['type'], 'plugin' );
		if ( $is_plugin_type && ! $is_plugin_slug ) {
			return new \WP_Error( 'gu_addition_slug', esc_html__( 'A plugin slug must include the main plugin file, e.g. plugin-slug/plugin-slug.php.', 'git-updater' ) );
		}
		if ( ! $is_plugin_type && $is_plugin_slug ) {
			return new \WP_Error( 'gu_addition_slug', esc_html__( 'A theme slug must be the theme directory name only.', 'git-updater' ) );
		}

		foreach ( $options as $option_key => $option ) {
			if ( $option_key !== $key && isset( $option['ID'] ) && $new['ID'] === $option['ID'] ) {
				return new \WP_Error( 'gu_addition_duplicate', esc_html__( 'An addition with this slug already exists.', 'git-updater' ) );
			}
		}

		$options[ $key ] = array_merge( $original, $new );
		update_site_option( 'git_updater_additions', $options );
		self::$options_additions = $options;

		// The repo cache is keyed by repository name, the last segment of the URI.
		$repos = array_unique( array_filter( [ basename( (string) ( $original['uri'] ?? '' ) ), basename( $new['uri'] ) ] ) );
		foreach ( $repos as $repo ) {
			\Fragen\Git_Updater\DB\Repo_Cache_Table::instance()->delete_repo( $repo );
		}

		return $options[ $key ];
	}

	/**
	 * Save Additions settings.
	 *
	 * @uses 'gu_update_settings' action hook
	 * @uses 'gu_save_redirect' filter hook
	 *
	 * @param array<string, mixed> $post_data $_POST data.
	 *
	 * @return void
	 */
	public function save_settings( $post_data ) {
		if ( ! isset( $_POST['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'git_updater_additions-options' )
			|| ! current_user_can( is_multisite() ? 'manage_network_options' : 'manage_options' )
		) {
			return;
		}
		$options   = (array) get_site_option( 'git_updater_additions', [] );
		$duplicate = false;
		$bad_input = false;
		if ( isset( $post_data['option_page'] ) &&
			'git_updater_additions' === $post_data['option_page']
		) {
			$new_options = $post_data['git_updater_additions'] ?? [];
			$new_options = $this->sanitize( $new_options );
			$bad_input   = empty( $new_options[0]['slug'] ) || empty( $new_options[0]['uri'] );

			foreach ( $options as $option ) {
				$is_plugin_slug = preg_match( '@/@', $new_options[0]['slug'] );
				$type_plugin    = preg_match( '/plugin/', $new_options[0]['type'] );
				$bad_input      = $type_plugin && ! $is_plugin_slug;
				$bad_input      = ! $bad_input ? ! $type_plugin && $is_plugin_slug : $bad_input;
				$duplicate      = in_array( $new_options[0]['ID'], $option, true );
				if ( $duplicate || $bad_input ) {
					$_POST['action'] = false;
					break;
				}
			}

			if ( ! $duplicate && ! $bad_input ) {
				$options = array_merge( $options, $new_options );
				$options = array_filter( $options );
				update_site_option( 'git_updater_additions', $options );
			}

			add_filter(
				'gu_save_redirect',
				function ( $option_page ) {
					return array_merge( $option_page, [ 'git_updater_additions' ] );
				}
			);
		}
	}

	/**
	 * Adds Additions tab to Settings page.
	 *
	 * @return void
	 */
	public function add_settings_tabs() {
		$install_tabs = [ 'git_updater_additions' => esc_html__( 'Additions', 'git-updater' ) ];
		add_filter(
			'gu_add_settings_tabs',
			function ( $tabs ) use ( $install_tabs ) {
				return array_merge( $tabs, $install_tabs );
			},
			20,
			1
		);
	}

	/**
	 * Add Settings page data via action hook.
	 *
	 * @uses 'gu_add_admin_page' action hook
	 *
	 * @param string $tab    Tab name.
	 * @param string $action Form action.
	 *
	 * @return void
	 */
	public function add_admin_page( $tab, $action ) {
		$this->additions_page_init();

		if ( 'git_updater_additions' === $tab ) {
			$action = add_query_arg(
				[
					'page' => 'git-updater',
					'tab'  => $tab,
				],
				$action
			);
			wp_enqueue_script(
				'gu-additions-quick-edit',
				plugin_dir_url( \Fragen\Git_Updater\PLUGIN_FILE ) . 'js/gu-additions-quick-edit.js',
				[],
				\Fragen\Git_Updater\Settings::get_plugin_version(),
				[ 'in_footer' => true ]
			);
			wp_localize_script(
				'gu-additions-quick-edit',
				'guAdditionsQuickEdit',
				[
					'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
					'nonce'        => wp_create_nonce( 'gu_additions_quick_edit' ),
					'error'        => __( 'Error while saving the changes.', 'git-updater' ),
					'refreshNonce' => wp_create_nonce( 'gu_additions_refresh' ),
					'refreshing'   => __( 'Refreshing…', 'git-updater' ),
					'refreshError' => __( 'Refresh failed.', 'git-updater' ),
				]
			);
			( new Repo_List_Table( self::$options_additions ) )->render_list_table();
			?>
			<form class="settings" method="post" action="<?php echo esc_attr( $action ); ?>">
				<?php
				settings_fields( 'git_updater_additions' );
				do_settings_sections( 'git_updater_additions' );
				submit_button();
				?>
			</form>
			<?php
		}
	}

	/**
	 * Settings for Additions.
	 *
	 * @return void
	 */
	public function additions_page_init() {
		register_setting(
			'git_updater_additions',
			'git_updater_additions'
		);

		add_settings_section(
			'git_updater_additions',
			esc_html__( 'Addition Packages', 'git-updater' ),
			[ $this, 'print_section_additions' ],
			'git_updater_additions'
		);

		add_settings_field(
			'type',
			esc_html__( 'Repository Type', 'git-updater' ),
			[ $this, 'callback_dropdown' ],
			'git_updater_additions',
			'git_updater_additions',
			[
				'id'      => 'git_updater_additions_type',
				'setting' => 'type',
			]
		);

		add_settings_field(
			'slug',
			esc_html__( 'Repository Slug', 'git-updater' ),
			[ $this, 'callback_field' ],
			'git_updater_additions',
			'git_updater_additions',
			[
				'id'          => 'git_updater_additions_slug',
				'setting'     => 'slug',
				'title'       => __( 'Ensure proper slug for plugin or theme.', 'git-updater' ),
				'placeholder' => 'plugin-slug/plugin-slug.php',
			]
		);

		add_settings_field(
			'uri',
			esc_html__( 'Repository URI', 'git-updater' ),
			[ $this, 'callback_field' ],
			'git_updater_additions',
			'git_updater_additions',
			[
				'id'      => 'git_updater_additions_uri',
				'setting' => 'uri',
				'title'   => __( 'Ensure proper URI for plugin or theme.', 'git-updater' ),
			]
		);

		add_settings_field(
			'primary_branch',
			esc_html__( 'Primary Branch', 'git-updater' ),
			[ $this, 'callback_field' ],
			'git_updater_additions',
			'git_updater_additions',
			[
				'id'          => 'git_updater_additions_primary_branch',
				'setting'     => 'primary_branch',
				'title'       => __( 'Ensure proper primary branch, default is `master`', 'git-updater' ),
				'placeholder' => 'master',
			]
		);

		add_settings_field(
			'release_asset',
			esc_html__( 'Release Asset', 'git-updater' ),
			[ $this, 'callback_checkbox' ],
			'git_updater_additions',
			'git_updater_additions',
			[
				'id'      => 'git_updater_additions_release_asset',
				'setting' => 'release_asset',
				'title'   => __( 'Check if a release asset is required.', 'git-updater' ),
			]
		);

		add_settings_field(
			'private_package',
			esc_html__( 'Private Package', 'git-updater' ),
			[ $this, 'callback_checkbox' ],
			'git_updater_additions',
			'git_updater_additions',
			[
				'id'      => 'git_updater_additions_private_package',
				'setting' => 'private_package',
				'title'   => __( 'Check if this package is not to be shared with aggregators.', 'git-updater' ),
			]
		);

		add_settings_field(
			'uses_lite',
			esc_html__( 'Uses Git Updater Lite', 'git-updater' ),
			[ $this, 'callback_checkbox' ],
			'git_updater_additions',
			'git_updater_additions',
			[
				'id'      => 'git_updater_additions_uses_lite',
				'setting' => 'uses_lite',
				'title'   => __( 'Check if this package is distributed to git-updater-lite clients. This is a distribution flag, not a privacy marker; the package must also carry an `Update URI:` header. Private packages are approved per client on the Lite Clients tab.', 'git-updater' ),
			]
		);
	}

	/**
	 * Sanitize each setting field as needed.
	 *
	 * @param array<string, mixed> $input Contains all settings fields as array keys.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function sanitize( $input ) {
		$new_input = [];

		foreach ( (array) $input as $key => $value ) {
			$new_input[0][ $key ] = 'uri' === $key ? untrailingslashit( esc_url_raw( trim( $value ) ) ) : sanitize_text_field( $value );
		}
		$new_input[0]['primary_branch']  = ! empty( $new_input[0]['primary_branch'] ) ? $new_input[0]['primary_branch'] : 'master';
		$new_input[0]['ID']              = md5( $new_input[0]['slug'] );
		$new_input[0]['source']          = md5( home_url() );
		$new_input[0]['private_package'] = ! empty( $new_input[0]['private_package'] ) ? true : false;
		$new_input[0]['uses_lite']       = ! empty( $new_input[0]['uses_lite'] ) ? true : false;

		return $new_input;
	}

	/**
	 * Print the Remote Management text.
	 *
	 * @return void
	 */
	public function print_section_additions() {
		echo '<p>';
		esc_html_e( 'If there are git repositories that do not natively support Git Updater you can add them here.', 'git-updater' );
		echo '</p>';
	}

	/**
	 * Field callback.
	 *
	 * @param array<string, mixed> $args Data passed from add_settings_field().
	 *
	 * @return void
	 */
	public function callback_field( $args ) {
		$placeholder = $args['placeholder'] ?? null;
		?>
		<label for="<?php echo esc_attr( $args['id'] ); ?>">
			<input type="text" style="width:50%;" id="<?php echo esc_attr( $args['id'] ); ?>" name="git_updater_additions[<?php echo esc_attr( $args['setting'] ); ?>]" value="" placeholder="<?php echo esc_attr( $placeholder ); ?>">
			<br>
			<span class="description">
				<?php echo esc_attr( $args['title'] ); ?>
			</span>
		</label>
		<?php
	}

	/**
	 * Dropdown callback.
	 *
	 * @param array<string, mixed> $args Data passed from add_settings_field().
	 *
	 * @return void
	 */
	public function callback_dropdown( $args ) {
		$options['type'] = [ 'github_plugin' ];
		?>
		<label for="<?php echo esc_attr( $args['id'] ); ?>">
		<select id="<?php echo esc_attr( $args['id'] ); ?>" name="git_updater_additions[<?php echo esc_attr( $args['setting'] ); ?>]">
		<?php
		$addition_types = apply_filters( 'gua_addition_types', self::$addition_types );
		foreach ( $addition_types as $item ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $item ),
				selected( 'plugin', $item, false ),
				esc_html( $item )
			);

		}
		?>
		</select>
		</label>
		<?php
	}

	/**
	 * Get the settings option array and print one of its values.
	 *
	 * @param array<string, mixed> $args Callback args.
	 *
	 * @return void
	 */
	public function callback_checkbox( $args ) {
		$checked = self::$options_additions[ $args['id'] ] ?? null;
		?>
		<label for="<?php echo esc_attr( $args['id'] ); ?>">
			<input type="checkbox" id="<?php echo esc_attr( $args['id'] ); ?>" name="git_updater_additions[<?php echo esc_attr( $args['setting'] ); ?>]" value="1" <?php checked( 1, intval( $checked ), true ); ?> <?php disabled( '-1', $checked, true ); ?> >
			<?php echo esc_attr( $args['title'] ); ?>
		</label>
		<?php
	}
}
