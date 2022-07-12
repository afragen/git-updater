<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.0.3
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Freemius SDK Version.
	 *
	 * @var string
	 */
	$this_sdk_version = '2.4.4';

	#region SDK Selection Logic --------------------------------------------------------------------

	/**
	 * Special logic added on 1.1.6 to make sure that every Freemius powered plugin
	 * will ALWAYS be loaded with the newest SDK from the active Freemius powered plugins.
	 *
	 * Since Freemius SDK is backward compatible, this will make sure that all Freemius powered
	 * plugins will run correctly.
	 *
	 * @since 1.1.6
	 */

	global $fs_active_plugins;

	if ( ! function_exists( 'fs_find_caller_plugin_file' ) ) {
		// Require SDK essentials.
		require_once dirname( __FILE__ ) . '/includes/fs-essential-functions.php';
	}

	/**
	 * This complex logic fixes symlink issues (e.g. with Vargant). The logic assumes
	 * that if it's a file from an SDK running in a theme, the location of the SDK
	 * is in the main theme's folder.
	 *
	 * @author Vova Feldman (@svovaf)
	 * @since  1.2.2.6
	 */
	$file_path    = fs_normalize_path( __FILE__ );
	$fs_root_path = dirname( $file_path );
    /**
     * Get the themes directory where the active theme is located (not passing the stylesheet will make WordPress
     * assume that the themes directory is inside `wp-content`.
     *
     * @author Leo Fajardo (@leorw)
     * @since 2.2.3
     */
	$themes_directory         = get_theme_root( get_stylesheet() );
	$themes_directory_name    = basename( $themes_directory );
	$theme_candidate_basename = basename( dirname( $fs_root_path ) ) . '/' . basename( $fs_root_path );

	if ( $file_path == fs_normalize_path( realpath( trailingslashit( $themes_directory ) . $theme_candidate_basename . '/' . basename( $file_path ) ) )
	) {
		$this_sdk_relative_path = '../' . $themes_directory_name . '/' . $theme_candidate_basename;
		$is_theme               = true;
	} else {
		$this_sdk_relative_path = plugin_basename( $fs_root_path );
		$is_theme               = false;
	}

	if ( ! isset( $fs_active_plugins ) ) {
		// Load all Freemius powered active plugins.
		$fs_active_plugins = get_option( 'fs_active_plugins', new stdClass() );

		if ( ! isset( $fs_active_plugins->plugins ) ) {
			$fs_active_plugins->plugins = array();
		}
	}

	if ( empty( $fs_active_plugins->abspath ) ) {
		/**
		 * Store the WP install absolute path reference to identify environment change
		 * while replicating the storage.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.2.1.7
		 */
		$fs_active_plugins->abspath = ABSPATH;
	} else {
		if ( ABSPATH !== $fs_active_plugins->abspath ) {
			/**
			 * WordPress path has changed, cleanup the SDK references cache.
			 * This resolves issues triggered when spinning a staging environments
			 * while replicating the database.
			 *
			 * @author Vova Feldman (@svovaf)
			 * @since  1.2.1.7
			 */
			$fs_active_plugins->abspath = ABSPATH;
			$fs_active_plugins->plugins = array();
			unset( $fs_active_plugins->newest );
		} else {
			/**
			 * Make sure SDK references are still valid. This resolves
			 * issues when users hard delete modules via FTP.
			 *
			 * @author Vova Feldman (@svovaf)
			 * @since  1.2.1.7
			 */
			$has_changes = false;
			foreach ( $fs_active_plugins->plugins as $sdk_path => $data ) {
                if ( ! file_exists( ( isset( $data->type ) && 'theme' === $data->type ? $themes_directory : WP_PLUGIN_DIR ) . '/' . $sdk_path ) ) {
					unset( $fs_active_plugins->plugins[ $sdk_path ] );

                    if (
                        ! empty( $fs_active_plugins->newest ) &&
                        $sdk_path === $fs_active_plugins->newest->sdk_path
                    ) {
                        unset( $fs_active_plugins->newest );
                    }

					$has_changes = true;
				}
			}

			if ( $has_changes ) {
				if ( empty( $fs_active_plugins->plugins ) ) {
					unset( $fs_active_plugins->newest );
				}

				update_option( 'fs_active_plugins', $fs_active_plugins );
			}
		}
	}

	if ( ! function_exists( 'fs_find_direct_caller_plugin_file' ) ) {
		require_once dirname( __FILE__ ) . '/includes/supplements/fs-essential-functions-1.1.7.1.php';
	}

	if ( ! function_exists( 'fs_get_plugins' ) ) {
		require_once dirname( __FILE__ ) . '/includes/supplements/fs-essential-functions-2.2.1.php';
	}

	// Update current SDK info based on the SDK path.
	if ( ! isset( $fs_active_plugins->plugins[ $this_sdk_relative_path ] ) ||
	     $this_sdk_version != $fs_active_plugins->plugins[ $this_sdk_relative_path ]->version
	) {
		if ( $is_theme ) {
			$plugin_path = basename( dirname( $this_sdk_relative_path ) );
		} else {
			$plugin_path = plugin_basename( fs_find_direct_caller_plugin_file( $file_path ) );
		}

		$fs_active_plugins->plugins[ $this_sdk_relative_path ] = (object) array(
			'version'     => $this_sdk_version,
			'type'        => ( $is_theme ? 'theme' : 'plugin' ),
			'timestamp'   => time(),
			'plugin_path' => $plugin_path,
		);
	}

	$is_current_sdk_newest = isset( $fs_active_plugins->newest ) && ( $this_sdk_relative_path == $fs_active_plugins->newest->sdk_path );

	if ( ! isset( $fs_active_plugins->newest ) ) {
		/**
		 * This will be executed only once, for the first time a Freemius powered plugin is activated.
		 */
		fs_update_sdk_newest_version( $this_sdk_relative_path, $fs_active_plugins->plugins[ $this_sdk_relative_path ]->plugin_path );

		$is_current_sdk_newest = true;
	} else if ( version_compare( $fs_active_plugins->newest->version, $this_sdk_version, '<' ) ) {
		/**
		 * Current SDK is newer than the newest stored SDK.
		 */
		fs_update_sdk_newest_version( $this_sdk_relative_path, $fs_active_plugins->plugins[ $this_sdk_relative_path ]->plugin_path );

		if ( class_exists( 'Freemius' ) ) {
			// Older SDK version was already loaded.

			if ( ! $fs_active_plugins->newest->in_activation ) {
				// Re-order plugins to load this plugin first.
				fs_newest_sdk_plugin_first();
			}

			// Refresh page.
			fs_redirect( $_SERVER['REQUEST_URI'] );
		}
	} else {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$fs_newest_sdk = $fs_active_plugins->newest;
		$fs_newest_sdk = $fs_active_plugins->plugins[ $fs_newest_sdk->sdk_path ];

		$is_newest_sdk_type_theme = ( isset( $fs_newest_sdk->type ) && 'theme' === $fs_newest_sdk->type );

		if ( ! $is_newest_sdk_type_theme ) {
			$is_newest_sdk_plugin_active = is_plugin_active( $fs_newest_sdk->plugin_path );
		} else {
			$current_theme               = wp_get_theme();
			$is_newest_sdk_plugin_active = ( $current_theme->stylesheet === $fs_newest_sdk->plugin_path );

            $current_theme_parent = $current_theme->parent();

            /**
             * If the current theme is a child of the theme that has the newest SDK, this prevents a redirects loop
             * from happening by keeping the SDK info stored in the `fs_active_plugins` option.
             */
            if ( ! $is_newest_sdk_plugin_active && $current_theme_parent instanceof WP_Theme ) {
                $is_newest_sdk_plugin_active = ( $fs_newest_sdk->plugin_path === $current_theme_parent->stylesheet );
            }
		}

		if ( $is_current_sdk_newest &&
		     ! $is_newest_sdk_plugin_active &&
		     ! $fs_active_plugins->newest->in_activation
		) {
			// If current SDK is the newest and the plugin is NOT active, it means
			// that the current plugin in activation mode.
			$fs_active_plugins->newest->in_activation = true;
			update_option( 'fs_active_plugins', $fs_active_plugins );
		}

		if ( ! $is_theme ) {
			$sdk_starter_path = fs_normalize_path( WP_PLUGIN_DIR . '/' . $this_sdk_relative_path . '/start.php' );
		} else {
			$sdk_starter_path = fs_normalize_path(
                $themes_directory
				. '/'
				. str_replace( "../{$themes_directory_name}/", '', $this_sdk_relative_path )
				. '/start.php' );
		}

		$is_newest_sdk_path_valid = ( $is_newest_sdk_plugin_active || $fs_active_plugins->newest->in_activation ) && file_exists( $sdk_starter_path );

		if ( ! $is_newest_sdk_path_valid && ! $is_current_sdk_newest ) {
			// Plugin with newest SDK is no longer active, or SDK was moved to a different location.
			unset( $fs_active_plugins->plugins[ $fs_active_plugins->newest->sdk_path ] );
		}

		if ( ! ( $is_newest_sdk_plugin_active || $fs_active_plugins->newest->in_activation ) ||
		     ! $is_newest_sdk_path_valid ||
		     // Is newest SDK downgraded.
		     ( $this_sdk_relative_path == $fs_active_plugins->newest->sdk_path &&
		       version_compare( $fs_active_plugins->newest->version, $this_sdk_version, '>' ) )
		) {
			/**
			 * Plugin with newest SDK is no longer active.
			 *    OR
			 * The newest SDK was in the current plugin. BUT, seems like the version of
			 * the SDK was downgraded to a lower SDK.
			 */
			// Find the active plugin with the newest SDK version and update the newest reference.
			fs_fallback_to_newest_active_sdk();
		} else {
			if ( $is_newest_sdk_plugin_active &&
			     $this_sdk_relative_path == $fs_active_plugins->newest->sdk_path &&
			     ( $fs_active_plugins->newest->in_activation ||
			       ( class_exists( 'Freemius' ) && ( ! defined( 'WP_FS__SDK_VERSION' ) || version_compare( WP_FS__SDK_VERSION, $this_sdk_version, '<' ) ) )
			     )

			) {
				if ( $fs_active_plugins->newest->in_activation && ! $is_newest_sdk_type_theme ) {
					// Plugin no more in activation.
					$fs_active_plugins->newest->in_activation = false;
					update_option( 'fs_active_plugins', $fs_active_plugins );
				}

				// Reorder plugins to load plugin with newest SDK first.
				if ( fs_newest_sdk_plugin_first() ) {
					// Refresh page after re-order to make sure activated plugin loads newest SDK.
					if ( class_exists( 'Freemius' ) ) {
						fs_redirect( $_SERVER['REQUEST_URI'] );
					}
				}
			}
		}
	}

	if ( class_exists( 'Freemius' ) ) {
		// SDK was already loaded.
		return;
	}

	if ( version_compare( $this_sdk_version, $fs_active_plugins->newest->version, '<' ) ) {
		$newest_sdk = $fs_active_plugins->plugins[ $fs_active_plugins->newest->sdk_path ];

		$plugins_or_theme_dir_path = ( ! isset( $newest_sdk->type ) || 'theme' !== $newest_sdk->type ) ?
			WP_PLUGIN_DIR :
            $themes_directory;

		$newest_sdk_starter = fs_normalize_path(
			$plugins_or_theme_dir_path
			. '/'
			. str_replace( "../{$themes_directory_name}/", '', $fs_active_plugins->newest->sdk_path )
			. '/start.php' );

		if ( file_exists( $newest_sdk_starter ) ) {
			// Reorder plugins to load plugin with newest SDK first.
			fs_newest_sdk_plugin_first();

			// There's a newer SDK version, load it instead of the current one!
			require_once $newest_sdk_starter;

			return;
		}
	}

	#endregion SDK Selection Logic --------------------------------------------------------------------

	#region Hooks & Filters Collection --------------------------------------------------------------------

	/**
	 * Freemius hooks (actions & filters) tags structure:
	 *
	 *      fs_{filter/action_name}_{plugin_slug}
	 *
	 * --------------------------------------------------------
	 *
	 * Usage with WordPress' add_action() / add_filter():
	 *
	 *      add_action('fs_{filter/action_name}_{plugin_slug}', $callable);
	 *
	 * --------------------------------------------------------
	 *
	 * Usage with Freemius' instance add_action() / add_filter():
	 *
	 *      // No need to add 'fs_' prefix nor '_{plugin_slug}' suffix.
	 *      my_freemius()->add_action('{action_name}', $callable);
	 *
	 * --------------------------------------------------------
	 *
	 * Freemius filters collection:
	 *
	 *      fs_connect_url_{plugin_slug}
	 *      fs_trial_promotion_message_{plugin_slug}
	 *      fs_is_long_term_user_{plugin_slug}
	 *      fs_uninstall_reasons_{plugin_slug}
	 *      fs_is_plugin_update_{plugin_slug}
	 *      fs_api_domains_{plugin_slug}
	 *      fs_email_template_sections_{plugin_slug}
	 *      fs_support_forum_submenu_{plugin_slug}
	 *      fs_support_forum_url_{plugin_slug}
	 *      fs_connect_message_{plugin_slug}
	 *      fs_connect_message_on_update_{plugin_slug}
	 *      fs_uninstall_confirmation_message_{plugin_slug}
	 *      fs_pending_activation_message_{plugin_slug}
	 *      fs_is_submenu_visible_{plugin_slug}
	 *      fs_plugin_icon_{plugin_slug}
	 *      fs_show_trial_{plugin_slug}
	 *
	 * --------------------------------------------------------
	 *
	 * Freemius actions collection:
	 *
	 *      fs_after_license_loaded_{plugin_slug}
	 *      fs_after_license_change_{plugin_slug}
	 *      fs_after_plans_sync_{plugin_slug}
	 *
	 *      fs_after_account_details_{plugin_slug}
	 *      fs_after_account_user_sync_{plugin_slug}
	 *      fs_after_account_plan_sync_{plugin_slug}
	 *      fs_before_account_load_{plugin_slug}
	 *      fs_after_account_connection_{plugin_slug}
	 *      fs_account_property_edit_{plugin_slug}
	 *      fs_account_email_verified_{plugin_slug}
	 *      fs_account_page_load_before_departure_{plugin_slug}
	 *      fs_before_account_delete_{plugin_slug}
	 *      fs_after_account_delete_{plugin_slug}
	 *
	 *      fs_sdk_version_update_{plugin_slug}
	 *      fs_plugin_version_update_{plugin_slug}
	 *
	 *      fs_initiated_{plugin_slug}
	 *      fs_after_init_plugin_registered_{plugin_slug}
	 *      fs_after_init_plugin_anonymous_{plugin_slug}
	 *      fs_after_init_plugin_pending_activations_{plugin_slug}
	 *      fs_after_init_addon_registered_{plugin_slug}
	 *      fs_after_init_addon_anonymous_{plugin_slug}
	 *      fs_after_init_addon_pending_activations_{plugin_slug}
	 *
	 *      fs_after_premium_version_activation_{plugin_slug}
	 *      fs_after_free_version_reactivation_{plugin_slug}
	 *
	 *      fs_after_uninstall_{plugin_slug}
	 *      fs_before_admin_menu_init_{plugin_slug}
	 */

	#endregion Hooks & Filters Collection --------------------------------------------------------------------

	if ( ! class_exists( 'Freemius' ) ) {

		if ( ! defined( 'WP_FS__SDK_VERSION' ) ) {
			define( 'WP_FS__SDK_VERSION', $this_sdk_version );
		}

		$plugins_or_theme_dir_path = fs_normalize_path( trailingslashit( $is_theme ?
            $themes_directory :
			WP_PLUGIN_DIR ) );

		if ( 0 === strpos( $file_path, $plugins_or_theme_dir_path ) ) {
			// No symlinks
		} else {
			/**
			 * This logic finds the SDK symlink and set WP_FS__DIR to use it.
			 *
			 * @author Vova Feldman (@svovaf)
			 * @since  1.2.2.5
			 */
			$sdk_symlink = null;

			// Try to load SDK's symlink from cache.
			if ( isset( $fs_active_plugins->plugins[ $this_sdk_relative_path ] ) &&
			     is_object( $fs_active_plugins->plugins[ $this_sdk_relative_path ] ) &&
			     ! empty( $fs_active_plugins->plugins[ $this_sdk_relative_path ]->sdk_symlink )
			) {
                $sdk_symlink = $fs_active_plugins->plugins[ $this_sdk_relative_path ]->sdk_symlink;
                if ( 0 === strpos( $sdk_symlink, $plugins_or_theme_dir_path ) ) {
                    /**
                     * Make the symlink path relative.
                     *
                     * @author Leo Fajardo (@leorw)
                     */
                    $sdk_symlink = substr( $sdk_symlink, strlen( $plugins_or_theme_dir_path ) );

                    $fs_active_plugins->plugins[ $this_sdk_relative_path ]->sdk_symlink = $sdk_symlink;
                    update_option( 'fs_active_plugins', $fs_active_plugins );
                }

                $realpath = realpath( $plugins_or_theme_dir_path . $sdk_symlink );
                if ( ! is_string( $realpath ) || ! file_exists( $realpath ) ) {
                    $sdk_symlink = null;
                }
            }

			if ( empty( $sdk_symlink ) ) // Has symlinks, therefore, we need to configure WP_FS__DIR based on the symlink.
			{
				$partial_path_right = basename( $file_path );
				$partial_path_left  = dirname( $file_path );
				$realpath           = realpath( $plugins_or_theme_dir_path . $partial_path_right );

				while ( '/' !== $partial_path_left &&
				        ( false === $realpath || $file_path !== fs_normalize_path( $realpath ) )
				) {
                    $partial_path_right     = trailingslashit( basename( $partial_path_left ) ) . $partial_path_right;
                    $partial_path_left_prev = $partial_path_left;
                    $partial_path_left      = dirname( $partial_path_left_prev );

                    /**
                     * Avoid infinite loop if for example `$partial_path_left_prev` is `C:/`, in this case,
                     * `dirname( 'C:/' )` will return `C:/`.
                     *
                     * @author Leo Fajardo (@leorw)
                     */
                    if ( $partial_path_left === $partial_path_left_prev ) {
                        $partial_path_left = '';
                        break;
                    }

                    $realpath = realpath( $plugins_or_theme_dir_path . $partial_path_right );
				}

                if ( ! empty( $partial_path_left ) && '/' !== $partial_path_left ) {
                    $sdk_symlink = fs_normalize_path( dirname( $partial_path_right ) );

					// Cache value.
					if ( isset( $fs_active_plugins->plugins[ $this_sdk_relative_path ] ) &&
					     is_object( $fs_active_plugins->plugins[ $this_sdk_relative_path ] )
					) {
						$fs_active_plugins->plugins[ $this_sdk_relative_path ]->sdk_symlink = $sdk_symlink;
						update_option( 'fs_active_plugins', $fs_active_plugins );
					}
				}
			}

			if ( ! empty( $sdk_symlink ) ) {
				// Set SDK dir to the symlink path.
				define( 'WP_FS__DIR', $plugins_or_theme_dir_path . $sdk_symlink );
			}
		}

		// Load SDK files.
		require_once dirname( __FILE__ ) . '/require.php';

		/**
		 * Quick shortcut to get Freemius for specified plugin.
		 * Used by various templates.
		 *
		 * @param number $module_id
		 *
		 * @return Freemius
		 */
		function freemius( $module_id ) {
			return Freemius::instance( $module_id );
		}

		/**
		 * @param string $slug
		 * @param number $plugin_id
		 * @param string $public_key
		 * @param bool   $is_live    Is live or test plugin.
		 * @param bool   $is_premium Hints freemius if running the premium plugin or not.
		 *
		 * @return Freemius
		 *
		 * @deprecated Please use fs_dynamic_init().
		 */
		function fs_init( $slug, $plugin_id, $public_key, $is_live = true, $is_premium = true ) {
			$fs = Freemius::instance( $plugin_id, $slug, true );
			$fs->init( $plugin_id, $public_key, $is_live, $is_premium );

			return $fs;
		}

		/**
		 * @param array <string,string|bool|array> $module Plugin or Theme details.
		 *
		 * @return Freemius
		 * @throws Freemius_Exception
		 */
		function fs_dynamic_init( $module ) {
			$fs = Freemius::instance( $module['id'], $module['slug'], true );
			$fs->dynamic_init( $module );

			return $fs;
		}

		function fs_dump_log() {
			FS_Logger::dump();
		}
	}
