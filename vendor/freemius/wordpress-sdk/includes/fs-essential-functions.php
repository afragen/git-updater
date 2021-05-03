<?php
	/**
	 * IMPORTANT:
	 *      This file will be loaded based on the order of the plugins/themes load.
	 *      If there's a theme and a plugin using Freemius, the plugin's essential
	 *      file will always load first.
	 *
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.1.5
	 */

	if ( ! function_exists( 'fs_normalize_path' ) ) {
		if ( function_exists( 'wp_normalize_path' ) ) {
			/**
			 * Normalize a filesystem path.
			 *
			 * Replaces backslashes with forward slashes for Windows systems, and ensures
			 * no duplicate slashes exist.
			 *
			 * @param string $path Path to normalize.
			 *
			 * @return string Normalized path.
			 */
			function fs_normalize_path( $path ) {
				return wp_normalize_path( $path );
			}
		} else {
			function fs_normalize_path( $path ) {
				$path = str_replace( '\\', '/', $path );
				$path = preg_replace( '|/+|', '/', $path );

				return $path;
			}
		}
	}

    require_once dirname( __FILE__ ) . '/supplements/fs-essential-functions-2.2.1.php';

	#region Core Redirect (copied from BuddyPress) -----------------------------------------

	if ( ! function_exists( 'fs_redirect' ) ) {
		/**
		 * Redirects to another page, with a workaround for the IIS Set-Cookie bug.
		 *
		 * @link  http://support.microsoft.com/kb/q176113/
		 * @since 1.5.1
		 * @uses  apply_filters() Calls 'wp_redirect' hook on $location and $status.
		 *
		 * @param string $location The path to redirect to.
		 * @param bool   $exit     If true, exit after redirect (Since 1.2.1.5).
		 * @param int    $status   Status code to use.
		 *
		 * @return bool False if $location is not set
		 */
		function fs_redirect( $location, $exit = true, $status = 302 ) {
			global $is_IIS;

			$file = '';
			$line = '';
			if ( headers_sent($file, $line) ) {
				if ( WP_FS__DEBUG_SDK && class_exists( 'FS_Admin_Notices' ) ) {
					$notices = FS_Admin_Notices::instance( 'global' );

					$notices->add( "Freemius failed to redirect the page because the headers have been already sent from line <b><code>{$line}</code></b> in file <b><code>{$file}</code></b>. If it's unexpected, it usually happens due to invalid space and/or EOL character(s).", 'Oops...', 'error' );
				}

				return false;
			}

			if ( defined( 'DOING_AJAX' ) ) {
				// Don't redirect on AJAX calls.
				return false;
			}

			if ( ! $location ) // allows the wp_redirect filter to cancel a redirect
			{
				return false;
			}

			$location = fs_sanitize_redirect( $location );

			if ( $is_IIS ) {
				header( "Refresh: 0;url=$location" );
			} else {
				if ( php_sapi_name() != 'cgi-fcgi' ) {
					status_header( $status );
				} // This causes problems on IIS and some FastCGI setups
				header( "Location: $location" );
			}

			if ( $exit ) {
				exit();
			}

			return true;
		}

		if ( ! function_exists( 'fs_sanitize_redirect' ) ) {
			/**
			 * Sanitizes a URL for use in a redirect.
			 *
			 * @since 2.3
			 *
			 * @param string $location
			 *
			 * @return string redirect-sanitized URL
			 */
			function fs_sanitize_redirect( $location ) {
				$location = preg_replace( '|[^a-z0-9-~+_.?#=&;,/:%!]|i', '', $location );
				$location = fs_kses_no_null( $location );

				// remove %0d and %0a from location
				$strip = array( '%0d', '%0a' );
				$found = true;
				while ( $found ) {
					$found = false;
					foreach ( (array) $strip as $val ) {
						while ( strpos( $location, $val ) !== false ) {
							$found    = true;
							$location = str_replace( $val, '', $location );
						}
					}
				}

				return $location;
			}
		}

		if ( ! function_exists( 'fs_kses_no_null' ) ) {
			/**
			 * Removes any NULL characters in $string.
			 *
			 * @since 1.0.0
			 *
			 * @param string $string
			 *
			 * @return string
			 */
			function fs_kses_no_null( $string ) {
				$string = preg_replace( '/\0+/', '', $string );
				$string = preg_replace( '/(\\\\0)+/', '', $string );

				return $string;
			}
		}
	}

	#endregion Core Redirect (copied from BuddyPress) -----------------------------------------

	if ( ! function_exists( '__fs' ) ) {
		global $fs_text_overrides;

		if ( ! isset( $fs_text_overrides ) ) {
			$fs_text_overrides = array();
		}

		/**
		 * Retrieve a translated text by key.
		 *
		 * @deprecated Use `fs_text()` instead since methods starting with `__` trigger warnings in Php 7.
         * @todo Remove this method in the future.
		 *
		 * @author     Vova Feldman (@svovaf)
		 * @since      1.1.4
		 *
		 * @param string $key
		 * @param string $slug
		 *
		 * @return string
		 *
		 * @global       $fs_text, $fs_text_overrides
		 */
		function __fs( $key, $slug = 'freemius' ) {
            _deprecated_function( __FUNCTION__, '2.0.0', 'fs_text()' );

			global $fs_text,
			       $fs_module_info_text,
			       $fs_text_overrides;

			if ( isset( $fs_text_overrides[ $slug ] ) ) {
				if ( isset( $fs_text_overrides[ $slug ][ $key ] ) ) {
					return $fs_text_overrides[ $slug ][ $key ];
				}

				$lower_key = strtolower( $key );
				if ( isset( $fs_text_overrides[ $slug ][ $lower_key ] ) ) {
					return $fs_text_overrides[ $slug ][ $lower_key ];
				}
			}

			if ( ! isset( $fs_text ) ) {
				$dir = defined( 'WP_FS__DIR_INCLUDES' ) ?
					WP_FS__DIR_INCLUDES :
					dirname( __FILE__ );

				require_once $dir . '/i18n.php';
			}

			if ( isset( $fs_text[ $key ] ) ) {
				return $fs_text[ $key ];
			}

			if ( isset( $fs_module_info_text[ $key ] ) ) {
				return $fs_module_info_text[ $key ];
			}

			return $key;
		}

		/**
		 * Output a translated text by key.
		 *
		 * @deprecated Use `fs_echo()` instead for consistency with `fs_text()`.
		 *
         * @todo Remove this method in the future.
         *
		 * @author     Vova Feldman (@svovaf)
		 * @since      1.1.4
		 *
		 * @param string $key
		 * @param string $slug
		 */
		function _efs( $key, $slug = 'freemius' ) {
			fs_echo( $key, $slug );
		}
	}

	if ( ! function_exists( 'fs_get_ip' ) ) {
		/**
		 * Get client IP.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.2
		 *
		 * @return string|null
		 */
		function fs_get_ip() {
			$fields = array(
				'HTTP_CF_CONNECTING_IP',
				'HTTP_CLIENT_IP',
				'HTTP_X_FORWARDED_FOR',
				'HTTP_X_FORWARDED',
				'HTTP_FORWARDED_FOR',
				'HTTP_FORWARDED',
				'REMOTE_ADDR',
			);

			foreach ( $fields as $ip_field ) {
				if ( ! empty( $_SERVER[ $ip_field ] ) ) {
					return $_SERVER[ $ip_field ];
				}
			}

			return null;
		}
	}

	/**
	 * Leverage backtrace to find caller plugin main file path.
	 *
	 * @author Vova Feldman (@svovaf)
	 * @since  1.0.6
	 *
	 * @return string
	 */
	function fs_find_caller_plugin_file() {
		/**
		 * All the code below will be executed once on activation.
		 * If the user changes the main plugin's file name, the file_exists()
		 * will catch it.
		 */
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins       = fs_get_plugins( true );
		$all_plugins_paths = array();

		// Get active plugin's main files real full names (might be symlinks).
		foreach ( $all_plugins as $relative_path => $data ) {
			$all_plugins_paths[] = fs_normalize_path( realpath( WP_PLUGIN_DIR . '/' . $relative_path ) );
		}

		$plugin_file = null;
		for ( $i = 1, $bt = debug_backtrace(), $len = count( $bt ); $i < $len; $i ++ ) {
			if ( empty( $bt[ $i ]['file'] ) ) {
				continue;
			}

			if ( in_array( fs_normalize_path( $bt[ $i ]['file'] ), $all_plugins_paths ) ) {
				$plugin_file = $bt[ $i ]['file'];
				break;
			}
		}

		if ( is_null( $plugin_file ) ) {
			// Throw an error to the developer in case of some edge case dev environment.
			wp_die(
				'Freemius SDK couldn\'t find the plugin\'s main file. Please contact sdk@freemius.com with the current error.',
				'Error',
				array( 'back_link' => true )
			);
		}

		return $plugin_file;
	}

	require_once dirname( __FILE__ ) . '/supplements/fs-essential-functions-1.1.7.1.php';

	/**
	 * Update SDK newest version reference.
	 *
	 * @author Vova Feldman (@svovaf)
	 * @since  1.1.6
	 *
	 * @param string      $sdk_relative_path
	 * @param string|bool $plugin_file
	 *
	 * @global            $fs_active_plugins
	 */
	function fs_update_sdk_newest_version( $sdk_relative_path, $plugin_file = false ) {
		/**
		 * If there is a plugin running an older version of FS (1.2.1 or below), the `fs_update_sdk_newest_version()`
		 * function in the older version will be used instead of this one. But since the older version is using
		 * the `is_plugin_active` function to check if a plugin is active, passing the theme's `plugin_path` to the
		 * `is_plugin_active` function will return false since the path is not a plugin path, so `in_activation` will be
		 * `true` for theme modules and the upgrading of the SDK version to 1.2.2 or newer version will work fine.
		 *
		 * Future versions that will call this function will use the proper logic here instead of just relying on the
		 * `is_plugin_active` function to fail for themes.
		 *
		 * @author Leo Fajardo (@leorw)
		 * @since  1.2.2
		 */

		global $fs_active_plugins;

		$newest_sdk = $fs_active_plugins->plugins[ $sdk_relative_path ];

		if ( ! is_string( $plugin_file ) ) {
			$plugin_file = plugin_basename( fs_find_caller_plugin_file() );
		}

		if ( ! isset( $newest_sdk->type ) || 'theme' !== $newest_sdk->type ) {
            if ( ! function_exists( 'is_plugin_active' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $in_activation = ( ! is_plugin_active( $plugin_file ) );
		} else {
			$theme         = wp_get_theme();
			$in_activation = ( $newest_sdk->plugin_path == $theme->stylesheet );
		}

		$fs_active_plugins->newest = (object) array(
			'plugin_path'   => $plugin_file,
			'sdk_path'      => $sdk_relative_path,
			'version'       => $newest_sdk->version,
			'in_activation' => $in_activation,
			'timestamp'     => time(),
		);

		// Update DB with latest SDK version and path.
		update_option( 'fs_active_plugins', $fs_active_plugins );
	}

	/**
	 * Reorder the plugins load order so the plugin with the newest Freemius SDK is loaded first.
	 *
	 * @author Vova Feldman (@svovaf)
	 * @since  1.1.6
	 *
	 * @return bool Was plugin order changed. Return false if plugin was loaded first anyways.
	 *
	 * @global $fs_active_plugins
	 */
	function fs_newest_sdk_plugin_first() {
        global $fs_active_plugins;

        /**
         * @todo Multi-site network activated plugin are always loaded prior to site plugins so if there's a plugin activated in the network mode that has an older version of the SDK of another plugin which is site activated that has new SDK version, the fs-essential-functions.php will be loaded from the older SDK. Same thing about MU plugins (loaded even before network activated plugins).
         *
         * @link https://github.com/Freemius/wordpress-sdk/issues/26
         */

        $newest_sdk_plugin_path = $fs_active_plugins->newest->plugin_path;

        $active_plugins         = get_option( 'active_plugins', array() );
        $updated_active_plugins = array( $newest_sdk_plugin_path );

        $plugin_found  = false;
        $is_first_path = true;

        foreach ( $active_plugins as $key => $plugin_path ) {
            if ( $plugin_path === $newest_sdk_plugin_path ) {
                if ( $is_first_path ) {
                    // if it's the first plugin already, no need to continue
                    return false;
                }

                $plugin_found = true;

                // Skip the plugin (it is already added as the 1st item of $updated_active_plugins).
                continue;
            }

            $updated_active_plugins[] = $plugin_path;

            if ( $is_first_path ) {
                $is_first_path = false;
            }
        }

        if ( $plugin_found ) {
            update_option( 'active_plugins', $updated_active_plugins );

            return true;
        }

        if ( is_multisite() ) {
            // Plugin is network active.
            $network_active_plugins = get_site_option( 'active_sitewide_plugins', array() );

            if ( isset( $network_active_plugins[ $newest_sdk_plugin_path ] ) ) {
                reset( $network_active_plugins );
                if ( $newest_sdk_plugin_path === key( $network_active_plugins ) ) {
                    // Plugin is already activated first on the network level.
                    return false;
                } else {
                    $time = $network_active_plugins[ $newest_sdk_plugin_path ];

                    // Remove plugin from its current position.
                    unset( $network_active_plugins[ $newest_sdk_plugin_path ] );

                    // Set it to be included first.
                    $network_active_plugins = array( $newest_sdk_plugin_path => $time ) + $network_active_plugins;

                    update_site_option( 'active_sitewide_plugins', $network_active_plugins );

                    return true;
                }
            }
        }

        return false;
    }

	/**
	 * Go over all Freemius SDKs in the system and find and "remember"
	 * the newest SDK which is associated with an active plugin.
	 *
	 * @author Vova Feldman (@svovaf)
	 * @since  1.1.6
	 *
	 * @global $fs_active_plugins
	 */
	function fs_fallback_to_newest_active_sdk() {
		global $fs_active_plugins;

		/**
		 * @var object $newest_sdk_data
		 */
		$newest_sdk_data = null;
		$newest_sdk_path = null;

		foreach ( $fs_active_plugins->plugins as $sdk_relative_path => $data ) {
			if ( is_null( $newest_sdk_data ) || version_compare( $data->version, $newest_sdk_data->version, '>' )
			) {
				// If plugin inactive or SDK starter file doesn't exist, remove SDK reference.
				if ( 'plugin' === $data->type ) {
					$is_module_active = is_plugin_active( $data->plugin_path );
				} else {
					$active_theme     = wp_get_theme();
					$is_module_active = ( $data->plugin_path === $active_theme->get_template() );
				}

				$is_sdk_exists = file_exists( fs_normalize_path( WP_PLUGIN_DIR . '/' . $sdk_relative_path . '/start.php' ) );

				if ( ! $is_module_active || ! $is_sdk_exists ) {
					unset( $fs_active_plugins->plugins[ $sdk_relative_path ] );

					// No need to store the data since it will be stored in fs_update_sdk_newest_version()
					// or explicitly with update_option().
				} else {
					$newest_sdk_data = $data;
					$newest_sdk_path = $sdk_relative_path;
				}
			}
		}

		if ( is_null( $newest_sdk_data ) ) {
			// Couldn't find any SDK reference.
			$fs_active_plugins = new stdClass();
			update_option( 'fs_active_plugins', $fs_active_plugins );
		} else {
			fs_update_sdk_newest_version( $newest_sdk_path, $newest_sdk_data->plugin_path );
		}
	}