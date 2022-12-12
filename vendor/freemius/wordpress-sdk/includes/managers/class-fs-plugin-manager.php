<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.0.6
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	class FS_Plugin_Manager {
		/**
		 * @since 1.2.2
		 *
		 * @var string|number
		 */
		protected $_module_id;
		/**
		 * @since 1.2.2
		 *
		 * @var FS_Plugin
		 */
		protected $_module;

		/**
		 * @var FS_Plugin_Manager[]
		 */
		private static $_instances = array();
		/**
		 * @var FS_Logger
		 */
		protected $_logger;

		/**
		 * Option names
		 *
		 * @author Leo Fajardo (@leorw)
		 * @since  1.2.2
		 */
		const OPTION_NAME_PLUGINS = 'plugins';
		const OPTION_NAME_THEMES  = 'themes';

		/**
		 * @param  string|number $module_id
		 *
		 * @return FS_Plugin_Manager
		 */
		static function instance( $module_id ) {
			$key = 'm_' . $module_id;

			if ( ! isset( self::$_instances[ $key ] ) ) {
				self::$_instances[ $key ] = new FS_Plugin_Manager( $module_id );
			}

			return self::$_instances[ $key ];
        }

		/**
		 * @param string|number $module_id
		 */
		protected function __construct( $module_id ) {
			$this->_logger    = FS_Logger::get_logger( WP_FS__SLUG . '_' . $module_id . '_' . 'plugins', WP_FS__DEBUG_SDK, WP_FS__ECHO_DEBUG_SDK );
			$this->_module_id = $module_id;

			$this->load();
		}

		protected function get_option_manager() {
			return FS_Option_Manager::get_manager( WP_FS__ACCOUNTS_OPTION_NAME, true, true );
		}

		/**
		 * @author Leo Fajardo (@leorw)
		 * @since  1.2.2
		 *
		 * @param  string|bool $module_type "plugin", "theme", or "false" for all modules.
		 *
		 * @return array
		 */
		protected function get_all_modules( $module_type = false ) {
			$option_manager = $this->get_option_manager();

			if ( false !== $module_type ) {
				return fs_get_entities( $option_manager->get_option( $module_type . 's', array() ), FS_Plugin::get_class_name() );
			}

			return array(
				self::OPTION_NAME_PLUGINS => fs_get_entities( $option_manager->get_option( self::OPTION_NAME_PLUGINS, array() ), FS_Plugin::get_class_name() ),
				self::OPTION_NAME_THEMES  => fs_get_entities( $option_manager->get_option( self::OPTION_NAME_THEMES, array() ), FS_Plugin::get_class_name() ),
			);
		}

		/**
		 * Load plugin data from local DB.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.6
		 */
		function load() {
			$all_modules = $this->get_all_modules();

			if ( ! is_numeric( $this->_module_id ) ) {
				unset( $all_modules[ self::OPTION_NAME_THEMES ] );
			}

			foreach ( $all_modules as $modules ) {
				/**
				 * @since 1.2.2
				 *
				 * @var $modules FS_Plugin[]
				 */
				foreach ( $modules as $module ) {
					$found_module = false;

					/**
					 * If module ID is not numeric, it must be a plugin's slug.
					 *
					 * @author Leo Fajardo (@leorw)
					 * @since  1.2.2
					 */
					if ( ! is_numeric( $this->_module_id ) ) {
						if ( $this->_module_id === $module->slug ) {
							$this->_module_id = $module->id;
							$found_module     = true;
						}
					} else if ( $this->_module_id == $module->id ) {
						$found_module = true;
					}

					if ( $found_module ) {
						$this->_module = $module;
						break;
					}
				}
			}
		}

		/**
		 * Store plugin on local DB.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.6
		 *
		 * @param bool|FS_Plugin $module
		 * @param bool           $flush
		 *
		 * @return bool|\FS_Plugin
		 */
		function store( $module = false, $flush = true ) {
			if ( false !== $module ) {
				$this->_module = $module;
			}

			$all_modules = $this->get_all_modules( $this->_module->type );
			$all_modules[ $this->_module->slug ] = $this->_module;

			$options_manager = $this->get_option_manager();
			$options_manager->set_option( $this->_module->type . 's', $all_modules, $flush );

			return $this->_module;
		}

		/**
		 * Update local plugin data if different.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.6
		 *
		 * @param \FS_Plugin $plugin
		 * @param bool       $store
		 *
		 * @return bool True if plugin was updated.
		 */
		function update( FS_Plugin $plugin, $store = true ) {
			if ( ! ($this->_module instanceof FS_Plugin ) ||
			     $this->_module->slug != $plugin->slug ||
			     $this->_module->public_key != $plugin->public_key ||
			     $this->_module->secret_key != $plugin->secret_key ||
			     $this->_module->parent_plugin_id != $plugin->parent_plugin_id ||
			     $this->_module->title != $plugin->title
			) {
				$this->store( $plugin, $store );

				return true;
			}

			return false;
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.6
		 *
		 * @param FS_Plugin $plugin
		 * @param bool      $store
		 */
		function set( FS_Plugin $plugin, $store = false ) {
			$this->_module = $plugin;

			if ( $store ) {
				$this->store();
			}
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.6
		 *
		 * @return bool|\FS_Plugin
		 */
		function get() {
            if ( isset( $this->_module ) ) {
                return $this->_module;
            }

            if ( empty( $this->_module_id ) ) {
                return false;
            }

            /**
             * Return an FS_Plugin entity that has its `id` and `is_live` properties set (`is_live` is initialized in the FS_Plugin constructor) to avoid triggering an error that is relevant to these properties when the FS_Plugin entity is used before the `parse_settings()` method is called. This can happen when creating a regular WordPress site by cloning a subsite of a multisite network and the data that is stored in the network-level storage is not cloned.
             *
             * @author Leo Fajardo (@leorw)
             * @since 2.5.0
             */
            $plugin     = new FS_Plugin();
            $plugin->id = $this->_module_id;

            return $plugin;
		}
	}