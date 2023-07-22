<?php
/**
 * Git Updater
 *
 * @author    Andy Fragen
 * @license   MIT
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
	 * @var array $option_remote
	 */
	public static $options_additions;

	/**
	 * Supported types.
	 *
	 * @var array $addition_types
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
	 */
	private function load_options() {
		self::$options_additions = get_site_option( 'git_updater_additions', [] );
	}

	/**
	 * Load needed action/filter hooks.
	 */
	public function load_hooks() {
		add_action(
			'gu_update_settings',
			function ( $post_data ) {
				$this->save_settings( $post_data );
			}
		);
		$this->add_settings_tabs();

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
	 * Save Additions settings.
	 *
	 * @uses 'gu_update_settings' action hook
	 * @uses 'gu_save_redirect' filter hook
	 *
	 * @param array $post_data $_POST data.
	 */
	public function save_settings( $post_data ) {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'git_updater_additions-options' ) ) {
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

			foreach ( $options as $option ) {
				$is_plugin_slug = preg_match( '@/@', $new_options[0]['slug'] );
				$type_plugin    = \preg_match( '/plugin/', $new_options[0]['type'] );
				$bad_input      = $type_plugin && ! $is_plugin_slug;
				$bad_input      = ! $bad_input ? ! $type_plugin && $is_plugin_slug : $bad_input;
				$bad_input      = $bad_input || empty( $new_options[0]['slug'] ) || empty( $new_options[0]['uri'] );
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
	 */
	public function add_settings_tabs() {
		$install_tabs = [ 'git_updater_additions' => esc_html__( 'Additions', 'git-updater-additions' ) ];
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
	 */
	public function additions_page_init() {
		register_setting(
			'git_updater_additions',
			'git_updater_additions',
			null
		);

		add_settings_section(
			'git_updater_additions',
			esc_html__( 'Additions', 'github-updater' ),
			[ $this, 'print_section_additions' ],
			'git_updater_additions'
		);

		add_settings_field(
			'type',
			esc_html__( 'Repository Type', 'git-updater-additions' ),
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
			esc_html__( 'Repository Slug', 'git-updater-additions' ),
			[ $this, 'callback_field' ],
			'git_updater_additions',
			'git_updater_additions',
			[
				'id'          => 'git_updater_additions_slug',
				'setting'     => 'slug',
				'title'       => __( 'Ensure proper slug for plugin or theme.', 'git-updater-addtions' ),
				'placeholder' => 'plugin-slug/plugin-slug.php',
			]
		);

		add_settings_field(
			'uri',
			esc_html__( 'Repository URI', 'git-updater-additions' ),
			[ $this, 'callback_field' ],
			'git_updater_additions',
			'git_updater_additions',
			[
				'id'      => 'git_updater_additions_uri',
				'setting' => 'uri',
				'title'   => __( 'Ensure proper URI for plugin or theme.', 'git-updater-addtions' ),
			]
		);

		add_settings_field(
			'primary_branch',
			esc_html__( 'Primary Branch', 'git-updater-additions' ),
			[ $this, 'callback_field' ],
			'git_updater_additions',
			'git_updater_additions',
			[
				'id'          => 'git_updater_additions_primary_branch',
				'setting'     => 'primary_branch',
				'title'       => __( 'Ensure proper primary branch, default is `master`', 'git-updater-additions' ),
				'placeholder' => 'master',
			]
		);

		add_settings_field(
			'release_asset',
			esc_html__( 'Release Asset', 'git-updater-additions' ),
			[ $this, 'callback_checkbox' ],
			'git_updater_additions',
			'git_updater_additions',
			[
				'id'      => 'git_updater_additions_release_asset',
				'setting' => 'release_asset',
				'title'   => __( 'Check if a release asset is required.', 'git-updater-additions' ),
			]
		);
	}

	/**
	 * Sanitize each setting field as needed.
	 *
	 * @param array $input Contains all settings fields as array keys.
	 *
	 * @return array
	 */
	public function sanitize( $input ) {
		$new_input = [];

		foreach ( (array) $input as $key => $value ) {
			$new_input[0][ $key ] = 'uri' === $key ? untrailingslashit( esc_url_raw( trim( $value ) ) ) : sanitize_text_field( $value );
		}
		$new_input[0]['ID'] = md5( $new_input[0]['slug'] );

		return $new_input;
	}

	/**
	 * Print the Remote Management text.
	 */
	public function print_section_additions() {
		echo '<p>';
		esc_html_e( 'If there are git repositories that do not natively support Git Updater you can add them here.', 'git-updater-additions' );
		echo '</p>';
	}

	/**
	 * Field callback.
	 *
	 * @param array $args Data passed from add_settings_field().
	 *
	 * @return void
	 */
	public function callback_field( $args ) {
		$placeholder = $args['placeholder'] ?? null;
		?>
		<label for="<?php echo esc_attr( $args['id'] ); ?>">
			<input type="text" style="width:50%;" id="<?php esc_attr( $args['id'] ); ?>" name="git_updater_additions[<?php echo esc_attr( $args['setting'] ); ?>]" value="" placeholder="<?php echo esc_attr( $placeholder ); ?>">
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
	 * @param arra $args Data passed from add_settings_field().
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
	 * @param array $args Callback args.
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
