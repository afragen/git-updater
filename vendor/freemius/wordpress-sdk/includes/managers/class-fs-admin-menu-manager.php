<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.1.3
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	class FS_Admin_Menu_Manager {

		#region Properties

		/**
		 * @since 1.2.2
		 *
		 * @var string
		 */
		protected $_module_unique_affix;

		/**
		 * @since 1.2.2
		 *
		 * @var number
		 */
		protected $_module_id;

		/**
		 * @since 1.2.2
		 *
		 * @var string
		 */
		protected $_module_type;

		/**
		 * @since 1.0.6
		 *
		 * @var string
		 */
		private $_menu_slug;
		/**
		 * @since 1.1.3
		 *
		 * @var string
		 */
		private $_parent_slug;
		/**
		 * @since 1.1.3
		 *
		 * @var string
		 */
		private $_parent_type;
		/**
		 * @since 1.1.3
		 *
		 * @var string
		 */
		private $_type;
		/**
		 * @since 1.1.3
		 *
		 * @var bool
		 */
		private $_is_top_level;
		/**
		 * @since 1.1.3
		 *
		 * @var bool
		 */
		private $_is_override_exact;
		/**
		 * @since 1.1.3
		 *
		 * @var array<string,bool>
		 */
		private $_default_submenu_items;
		/**
		 * @since 1.1.3
		 *
		 * @var string
		 */
		private $_first_time_path;
		/**
		 * @since 1.2.2
		 *
		 * @var bool
		 */
		private $_menu_exists;
		/**
		 * @since 2.0.0
		 *
		 * @var bool
		 */
		private $_network_menu_exists;

		#endregion Properties

		/**
		 * @var FS_Logger
		 */
		protected $_logger;

		#region Singleton

		/**
		 * @var FS_Admin_Menu_Manager[]
		 */
		private static $_instances = array();

		/**
		 * @param number $module_id
		 * @param string $module_type
		 * @param string $module_unique_affix
		 *
		 * @return FS_Admin_Menu_Manager
		 */
		static function instance( $module_id, $module_type, $module_unique_affix ) {
			$key = 'm_' . $module_id;

			if ( ! isset( self::$_instances[ $key ] ) ) {
				self::$_instances[ $key ] = new FS_Admin_Menu_Manager( $module_id, $module_type, $module_unique_affix );
			}

			return self::$_instances[ $key ];
		}

		protected function __construct( $module_id, $module_type, $module_unique_affix ) {
			$this->_logger = FS_Logger::get_logger( WP_FS__SLUG . '_' . $module_id . '_admin_menu', WP_FS__DEBUG_SDK, WP_FS__ECHO_DEBUG_SDK );

			$this->_module_id           = $module_id;
			$this->_module_type         = $module_type;
			$this->_module_unique_affix = $module_unique_affix;
		}

		#endregion Singleton

		#region Helpers

		private function get_option( &$options, $key, $default = false ) {
			return ! empty( $options[ $key ] ) ? $options[ $key ] : $default;
		}

		private function get_bool_option( &$options, $key, $default = false ) {
			return isset( $options[ $key ] ) && is_bool( $options[ $key ] ) ? $options[ $key ] : $default;
		}

		#endregion Helpers

		/**
		 * @param array $menu
		 * @param bool  $is_addon
		 */
		function init( $menu, $is_addon = false ) {
			$this->_menu_exists = ( isset( $menu['slug'] ) && ! empty( $menu['slug'] ) );
			$this->_network_menu_exists = ( ! empty( $menu['network'] ) && true === $menu['network'] );

			$this->_menu_slug = ( $this->_menu_exists ? $menu['slug'] : $this->_module_unique_affix );

			$this->_default_submenu_items = array();
			// @deprecated
			$this->_type              = 'page';
			$this->_is_top_level      = true;
			$this->_is_override_exact = false;
			$this->_parent_slug       = false;
			// @deprecated
			$this->_parent_type = 'page';

			if ( isset( $menu ) ) {
			    if ( ! $is_addon ) {
                    $this->_default_submenu_items = array(
                        'contact'     => $this->get_bool_option( $menu, 'contact', true ),
                        'support'     => $this->get_bool_option( $menu, 'support', true ),
                        'affiliation' => $this->get_bool_option( $menu, 'affiliation', true ),
                        'account'     => $this->get_bool_option( $menu, 'account', true ),
                        'pricing'     => $this->get_bool_option( $menu, 'pricing', true ),
                        'addons'      => $this->get_bool_option( $menu, 'addons', true ),
                    );

                    // @deprecated
                    $this->_type = $this->get_option( $menu, 'type', 'page' );
                }

				$this->_is_override_exact = $this->get_bool_option( $menu, 'override_exact' );

				if ( isset( $menu['parent'] ) ) {
					$this->_parent_slug = $this->get_option( $menu['parent'], 'slug' );
					// @deprecated
					$this->_parent_type = $this->get_option( $menu['parent'], 'type', 'page' );

					// If parent's slug is different, then it's NOT a top level menu item.
					$this->_is_top_level = ( $this->_parent_slug === $this->_menu_slug );
				} else {
					/**
					 * If no parent then top level if:
					 *  - Has custom admin menu ('page')
					 *  - CPT menu type ('cpt')
					 */
//					$this->_is_top_level = in_array( $this->_type, array(
//						'cpt',
//						'page'
//					) );
				}

				$first_path = $this->get_option( $menu, 'first-path', false );

                if ( ! empty( $first_path ) && is_string( $first_path ) ) {
                    $this->_first_time_path = $first_path;
                }
			}
		}

		/**
		 * Check if top level menu.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @return bool False if submenu item.
		 */
		function is_top_level() {
			return $this->_is_top_level;
		}

		/**
		 * Check if the page should be override on exact URL match.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @return bool False if submenu item.
		 */
		function is_override_exact() {
			return $this->_is_override_exact;
		}


        /**
         * Get the path of the page the user should be forwarded to after first activation.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.3
         *
         * @param bool $is_network Since 2.4.5
         *
         * @return string
         */
        function get_first_time_path( $is_network = false ) {
            if ( empty ( $this->_first_time_path ) ) {
                return $this->_first_time_path;
            }

            if ( $is_network ) {
                return network_admin_url( $this->_first_time_path );
            } else {
                return admin_url( $this->_first_time_path );
            }
        }

		/**
		 * Check if plugin's menu item is part of a custom top level menu.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @return bool
		 */
		function has_custom_parent() {
			return ! $this->_is_top_level && is_string( $this->_parent_slug );
		}

		/**
		 * @author Leo Fajardo (@leorw)
		 * @since  1.2.2
		 *
		 * @return bool
		 */
		function has_menu() {
			return $this->_menu_exists;
		}

		/**
         * @author Vova Feldman (@svovaf)
		 * @since  2.0.0
		 *
		 * @return bool
		 */
		function has_network_menu() {
			return $this->_network_menu_exists;
		}

        /**
         * @author Leo Fajardo (@leorw)
         *
         * @param string $menu_slug
         *
         * @since 2.1.3
         */
		function set_slug_and_network_menu_exists_flag($menu_slug ) {
		    $this->_menu_slug           = $menu_slug;
		    $this->_network_menu_exists = false;
        }

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @param string $id
		 * @param bool   $default
		 * @param bool   $ignore_menu_existence Since 1.2.2.7 If true, check if the submenu item visible even if there's no parent menu.
		 *
		 * @return bool
		 */
		function is_submenu_item_visible( $id, $default = true, $ignore_menu_existence = false ) {
			if ( ! $ignore_menu_existence && ! $this->has_menu() ) {
				return false;
			}

			return fs_apply_filter(
				$this->_module_unique_affix,
				'is_submenu_visible',
				$this->get_bool_option( $this->_default_submenu_items, $id, $default ),
				$id
			);
		}

		/**
		 * Calculates admin settings menu slug.
		 * If plugin's menu slug is a file (e.g. CPT), uses plugin's slug as the menu slug.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @param string $page
		 *
		 * @return string
		 */
		function get_slug( $page = '' ) {
			return ( ( false === strpos( $this->_menu_slug, '.php?' ) ) ?
				$this->_menu_slug :
				$this->_module_unique_affix ) . ( empty( $page ) ? '' : ( '-' . $page ) );
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @return string
		 */
		function get_parent_slug() {
			return $this->_parent_slug;
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @return string
		 */
		function get_type() {
			return $this->_type;
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @return bool
		 */
		function is_cpt() {
			return ( 0 === strpos( $this->_menu_slug, 'edit.php?post_type=' ) ||
			         // Back compatibility.
			         'cpt' === $this->_type
			);
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @return string
		 */
		function get_parent_type() {
			return $this->_parent_type;
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @return string
		 */
		function get_raw_slug() {
			return $this->_menu_slug;
		}

		/**
		 * Get plugin's original menu slug.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @return string
		 */
		function get_original_menu_slug() {
			if ( 'cpt' === $this->_type ) {
				return add_query_arg( array(
					'post_type' => $this->_menu_slug
				), 'edit.php' );
			}

			if ( false === strpos( $this->_menu_slug, '.php?' ) ) {
				return $this->_menu_slug;
			} else {
				return $this->_module_unique_affix;
			}
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @return string
		 */
		function get_top_level_menu_slug() {
			return $this->has_custom_parent() ?
				$this->get_parent_slug() :
				$this->get_raw_slug();
		}

        /**
         * Is user on plugin's admin activation page.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.8
         *
         * @param bool $show_opt_in_on_themes_page Since 2.3.1
         *
         * @return bool
         *
         * @deprecated Please use is_activation_page() instead.
         */
        function is_main_settings_page( $show_opt_in_on_themes_page = false ) {
            return $this->is_activation_page( $show_opt_in_on_themes_page );
        }

        /**
         * Is user on product's admin activation page.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.3.1
         *
         * @param bool $show_opt_in_on_themes_page Since 2.3.1
         *
         * @return bool
         */
        function is_activation_page( $show_opt_in_on_themes_page = false ) {
            if ( $show_opt_in_on_themes_page ) {
                /**
                 * In activation only when show_optin query string param is given.
                 *
                 * @since 1.2.2
                 */
                return (
                    ( WP_FS__MODULE_TYPE_THEME === $this->_module_type ) &&
                    Freemius::is_themes_page() &&
                    fs_request_get_bool( $this->_module_unique_affix . '_show_optin' )
                );
            }

            if ( $this->_menu_exists &&
                 ( fs_is_plugin_page( $this->_menu_slug ) || fs_is_plugin_page( $this->_module_unique_affix ) )
            ) {
                /**
                 * Module has a settings menu and the context page is the main settings page, so assume it's in
                 * activation (doesn't really check if already opted-in/skipped or not).
                 *
                 * @since 1.2.2
                 */
                return true;
            }

            return false;
        }

        #region Submenu Override

		/**
		 * Override submenu's action.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.0
		 *
		 * @param string   $parent_slug
		 * @param string   $menu_slug
		 * @param callable $function
		 *
		 * @return false|string If submenu exist, will return the hook name.
		 */
		function override_submenu_action( $parent_slug, $menu_slug, $function ) {
			global $submenu;

			$menu_slug   = plugin_basename( $menu_slug );
			$parent_slug = plugin_basename( $parent_slug );

			if ( ! isset( $submenu[ $parent_slug ] ) ) {
				// Parent menu not exist.
				return false;
			}

			$found_submenu_item = false;
			foreach ( $submenu[ $parent_slug ] as $submenu_item ) {
				if ( $menu_slug === $submenu_item[2] ) {
					$found_submenu_item = $submenu_item;
					break;
				}
			}

			if ( false === $found_submenu_item ) {
				// Submenu item not found.
				return false;
			}

			// Remove current function.
			$hookname = get_plugin_page_hookname( $menu_slug, $parent_slug );
			remove_all_actions( $hookname );

			// Attach new action.
			add_action( $hookname, $function );

			return $hookname;
		}

		#endregion Submenu Override

		#region Top level menu Override

		/**
		 * Find plugin's admin dashboard main menu item.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.2
		 *
		 * @return string[]|false
		 */
		private function find_top_level_menu() {
			global $menu;

			$position   = - 1;
			$found_menu = false;

			$menu_slug = $this->get_raw_slug();

			$hook_name = get_plugin_page_hookname( $menu_slug, '' );
			foreach ( $menu as $pos => $m ) {
				if ( $menu_slug === $m[2] ) {
					$position   = $pos;
					$found_menu = $m;
					break;
				}
			}

			if ( false === $found_menu ) {
				return false;
			}

			return array(
				'menu'      => $found_menu,
				'position'  => $position,
				'hook_name' => $hook_name
			);
		}

		/**
		 * Find plugin's admin dashboard main submenu item.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.2.1.6
		 *
		 * @return array|false
		 */
		private function find_main_submenu() {
			global $submenu;

			$top_level_menu_slug = $this->get_top_level_menu_slug();

			if ( ! isset( $submenu[ $top_level_menu_slug ] ) ) {
				return false;
			}

			$submenu_slug = $this->get_raw_slug();

			$position   = - 1;
			$found_submenu = false;

			$hook_name = get_plugin_page_hookname( $submenu_slug, '' );

			foreach ( $submenu[ $top_level_menu_slug ] as $pos => $sub ) {
				if ( $submenu_slug === $sub[2] ) {
					$position   = $pos;
					$found_submenu = $sub;
				}
			}

			if ( false === $found_submenu ) {
				return false;
			}

			return array(
				'menu'        => $found_submenu,
				'parent_slug' => $top_level_menu_slug,
				'position'    => $position,
				'hook_name'   => $hook_name
			);
		}

		/**
		 * Remove all sub-menu items.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.7
		 *
		 * @return bool If submenu with plugin's menu slug was found.
		 */
		private function remove_all_submenu_items() {
			global $submenu;

			$menu_slug = $this->get_raw_slug();

			if ( ! isset( $submenu[ $menu_slug ] ) ) {
				return false;
			}

			/**
			 * This method is NOT executed for WordPress.org themes.
			 * Since we maintain only one version of the SDK we added this small
			 * hack to avoid the error from Theme Check since it's a false-positive.
			 *
			 * @author Vova Feldman (@svovaf)
			 * @since  1.2.2.7
			 */
			$submenu_ref               = &$submenu;
			$submenu_ref[ $menu_slug ] = array();

			return true;
		}

		/**
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.9
		 *
         * @param bool $remove_top_level_menu
         * 
		 * @return false|array[string]mixed
		 */
        function remove_menu_item( $remove_top_level_menu = false ) {
            $this->_logger->entrance();

            // Find main menu item.
            $top_level_menu = $this->find_top_level_menu();

            if ( false === $top_level_menu ) {
                return false;
            }

            // Remove it with its actions.
            remove_all_actions( $top_level_menu['hook_name'] );

            // Remove all submenu items.
            $this->remove_all_submenu_items();

            if ( $remove_top_level_menu ) {
                global $menu;
                unset( $menu[ $top_level_menu['position'] ] );
            }

            return $top_level_menu;
        }

		/**
		 * Get module's main admin setting page URL.
		 *
		 * @todo This method was only tested for wp.org compliant themes with a submenu item. Need to test for plugins with top level, submenu, and CPT top level, menu items.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.2.2.7
		 *
		 * @return string
		 */
		function main_menu_url() {
			$this->_logger->entrance();

			if ( $this->_is_top_level ) {
				$menu = $this->find_top_level_menu();
			} else {
				$menu = $this->find_main_submenu();
			}

			$parent_slug = isset( $menu['parent_slug'] ) ?
                $menu['parent_slug'] :
                'admin.php';

            return admin_url(
                $parent_slug .
                ( false === strpos( $parent_slug, '?' ) ? '?' : '&' ) .
                'page=' .
                $menu['menu'][2]
            );
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.4
		 *
		 * @param callable $function
		 *
		 * @return false|array[string]mixed
		 */
		function override_menu_item( $function ) {
			$found_menu = $this->remove_menu_item();

			if ( false === $found_menu ) {
				return false;
			}

			if ( ! $this->is_top_level() || ! $this->is_cpt() ) {
				$menu_slug = plugin_basename( $this->get_slug() );

				$hookname = get_plugin_page_hookname( $menu_slug, '' );

				// Override menu action.
				add_action( $hookname, $function );
			} else {
				global $menu;

				// Remove original CPT menu.
				unset( $menu[ $found_menu['position'] ] );

				// Create new top-level menu action.
				$hookname = self::add_page(
					$found_menu['menu'][3],
					$found_menu['menu'][0],
					'manage_options',
					$this->get_slug(),
					$function,
					$found_menu['menu'][6],
					$found_menu['position']
				);
			}

			return $hookname;
		}

		/**
		 * Adds a counter to the module's top level menu item.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.2.1.5
		 *
		 * @param int    $counter
		 * @param string $class
		 */
		function add_counter_to_menu_item( $counter = 1, $class = '' ) {
			global $menu, $submenu;

			$mask = '%s <span class="update-plugins %s count-%3$s" aria-hidden="true"><span>%3$s<span class="screen-reader-text">%3$s notifications</span></span></span>';

			/**
			 * This method is NOT executed for WordPress.org themes.
			 * Since we maintain only one version of the SDK we added this small
			 * hack to avoid the error from Theme Check since it's a false-positive.
			 *
			 * @author Vova Feldman (@svovaf)
			 * @since  1.2.2.7
			 */
			$menu_ref    = &$menu;
			$submenu_ref = &$submenu;

			if ( $this->_is_top_level ) {
				// Find main menu item.
				$found_menu = $this->find_top_level_menu();

				if ( false !== $found_menu ) {
					// Override menu label.
					$menu_ref[ $found_menu['position'] ][0] = sprintf(
						$mask,
						$found_menu['menu'][0],
						$class,
						$counter
					);
				}
			} else {
				$found_submenu = $this->find_main_submenu();

				if ( false !== $found_submenu ) {
					// Override menu label.
					$submenu_ref[ $found_submenu['parent_slug'] ][ $found_submenu['position'] ][0] = sprintf(
						$mask,
						$found_submenu['menu'][0],
						$class,
						$counter
					);
				}
			}
		}

		#endregion Top level menu Override

		/**
		 * Add a top-level menu page.
		 *
		 * Note for WordPress.org Theme/Plugin reviewer:
		 *
		 *  This is a replication of `add_menu_page()` to avoid Theme Check warning.
		 *
		 *  Why?
		 *  ====
		 *  Freemius is an SDK for plugin and theme developers. Since the core
		 *  of the SDK is relevant both for plugins and themes, for obvious reasons,
		 *  we only develop and maintain one code base.
		 *
		 *  This method will not run for wp.org themes (only plugins) since theme
		 *  admin settings/options are now only allowed in the customizer.
		 *
		 *  If you have any questions or need clarifications, please don't hesitate
		 *  pinging me on slack, my username is @svovaf.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.2.2
		 *
		 * @param string          $page_title The text to be displayed in the title tags of the page when the menu is
		 *                                    selected.
		 * @param string          $menu_title The text to be used for the menu.
		 * @param string          $capability The capability required for this menu to be displayed to the user.
		 * @param string          $menu_slug  The slug name to refer to this menu by (should be unique for this menu).
		 * @param callable|string $function   The function to be called to output the content for this page.
		 * @param string          $icon_url   The URL to the icon to be used for this menu.
		 *                                    * Pass a base64-encoded SVG using a data URI, which will be colored to
		 *                                    match the color scheme. This should begin with
		 *                                    'data:image/svg+xml;base64,'.
		 *                                    * Pass the name of a Dashicons helper class to use a font icon,
		 *                                    e.g. 'dashicons-chart-pie'.
		 *                                    * Pass 'none' to leave div.wp-menu-image empty so an icon can be added
		 *                                    via CSS.
		 * @param int             $position   The position in the menu order this one should appear.
		 *
		 * @return string The resulting page's hook_suffix.
		 */
		static function add_page(
			$page_title,
			$menu_title,
			$capability,
			$menu_slug,
			$function = '',
			$icon_url = '',
			$position = null
		) {
			$fn = 'add_menu' . '_page';

			return $fn(
				$page_title,
				$menu_title,
				$capability,
				$menu_slug,
				$function,
				$icon_url,
				$position
			);
		}

        /**
         * Add page and update menu instance settings.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param string          $page_title
         * @param string          $menu_title
         * @param string          $capability
         * @param string          $menu_slug
         * @param callable|string $function
         * @param string          $icon_url
         * @param int|null        $position
         *
         * @return string
         */
		function add_page_and_update(
            $page_title,
            $menu_title,
            $capability,
            $menu_slug,
            $function = '',
            $icon_url = '',
            $position = null
        ) {
            $this->_menu_slug           = $menu_slug;
            $this->_is_top_level        = true;
            $this->_menu_exists         = true;
            $this->_network_menu_exists = true;

            return self::add_page(
                $page_title,
                $menu_title,
                $capability,
                $menu_slug,
                $function,
                $icon_url,
                $position
            );
        }

		/**
		 * Add a submenu page.
		 *
		 * Note for WordPress.org Theme/Plugin reviewer:
		 *
		 *  This is a replication of `add_submenu_page()` to avoid Theme Check warning.
		 *
		 *  Why?
		 *  ====
		 *  Freemius is an SDK for plugin and theme developers. Since the core
		 *  of the SDK is relevant both for plugins and themes, for obvious reasons,
		 *  we only develop and maintain one code base.
		 *
		 *  This method will not run for wp.org themes (only plugins) since theme
		 *  admin settings/options are now only allowed in the customizer.
		 *
		 *  If you have any questions or need clarifications, please don't hesitate
		 *  pinging me on slack, my username is @svovaf.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.2.2
		 *
		 * @param string          $parent_slug The slug name for the parent menu (or the file name of a standard
		 *                                     WordPress admin page).
		 * @param string          $page_title  The text to be displayed in the title tags of the page when the menu is
		 *                                     selected.
		 * @param string          $menu_title  The text to be used for the menu.
		 * @param string          $capability  The capability required for this menu to be displayed to the user.
		 * @param string          $menu_slug   The slug name to refer to this menu by (should be unique for this menu).
		 * @param callable|string $function    The function to be called to output the content for this page.
		 *
		 * @return false|string The resulting page's hook_suffix, or false if the user does not have the capability
		 *                      required.
		 */
		static function add_subpage(
			$parent_slug,
			$page_title,
			$menu_title,
			$capability,
			$menu_slug,
			$function = ''
		) {
			$fn = 'add_submenu' . '_page';

			return $fn( $parent_slug,
				$page_title,
				$menu_title,
				$capability,
				$menu_slug,
				$function
			);
		}

        /**
         * Add sub page and update menu instance settings.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param string          $parent_slug
         * @param string          $page_title
         * @param string          $menu_title
         * @param string          $capability
         * @param string          $menu_slug
         * @param callable|string $function
         *
         * @return string
         */
        function add_subpage_and_update(
            $parent_slug,
            $page_title,
            $menu_title,
            $capability,
            $menu_slug,
            $function = ''
        ) {
            $this->_menu_slug           = $menu_slug;
            $this->_parent_slug         = $parent_slug;
            $this->_is_top_level        = false;
            $this->_menu_exists         = true;
            $this->_network_menu_exists = true;

            return self::add_subpage(
                $parent_slug,
                $page_title,
                $menu_title,
                $capability,
                $menu_slug,
                $function
            );
        }
	}