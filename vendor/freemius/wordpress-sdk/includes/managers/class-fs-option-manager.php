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
     * 2-layer lazy options manager.
     *      layer 2: Memory
     *      layer 1: Database (options table). All options stored as one option record in the DB to reduce number of DB queries.
     *
     * If load() is not explicitly called, starts as empty manager. Same thing about saving the data - you have to explicitly call store().
     *
     * Class Freemius_Option_Manager
     */
    class FS_Option_Manager {
        /**
         * @var string
         */
        private $_id;
        /**
         * @var array|object
         */
        private $_options;
        /**
         * @var FS_Logger
         */
        private $_logger;

        /**
         * @since 2.0.0
         * @var int The ID of the blog that is associated with the current site level options.
         */
        private $_blog_id = 0;

        /**
         * @since 2.0.0
         * @var bool
         */
        private $_is_network_storage;

        /**
         * @var bool|null
         */
        private $_autoload;

        /**
         * @var array[string]FS_Option_Manager {
         * @key   string
         * @value FS_Option_Manager
         * }
         */
        private static $_MANAGERS = array();

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.3
         *
         * @param string    $id
         * @param bool      $load
         * @param bool|int  $network_level_or_blog_id Since 2.0.0
         * @param bool|null $autoload
         */
        private function __construct(
            $id,
            $load = false,
            $network_level_or_blog_id = false,
            $autoload = null
        ) {
            $id = strtolower( $id );

            $this->_logger = FS_Logger::get_logger( WP_FS__SLUG . '_opt_mngr_' . $id, WP_FS__DEBUG_SDK, WP_FS__ECHO_DEBUG_SDK );

            $this->_logger->entrance();
            $this->_logger->log( 'id = ' . $id );

            $this->_id = $id;

            $this->_autoload = $autoload;

            if ( is_multisite() ) {
                $this->_is_network_storage = ( true === $network_level_or_blog_id );

                if ( is_numeric( $network_level_or_blog_id ) ) {
                    $this->_blog_id = $network_level_or_blog_id;
                }
            } else {
                $this->_is_network_storage = false;
            }

            if ( $load ) {
                $this->load();
            }
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.3
         *
         * @param string    $id
         * @param bool      $load
         * @param bool|int  $network_level_or_blog_id Since 2.0.0
         * @param bool|null $autoload
         *
         * @return \FS_Option_Manager
         */
        static function get_manager(
            $id,
            $load = false,
            $network_level_or_blog_id = false,
            $autoload = null
        ) {
            $key = strtolower( $id );

            if ( is_multisite() ) {
                if ( true === $network_level_or_blog_id ) {
                    $key .= ':ms';
                } else if ( is_numeric( $network_level_or_blog_id ) && $network_level_or_blog_id > 0 ) {
                    $key .= ":{$network_level_or_blog_id}";
                } else {
                    $network_level_or_blog_id = get_current_blog_id();

                    $key .= ":{$network_level_or_blog_id}";
                }
            }

            if ( ! isset( self::$_MANAGERS[ $key ] ) ) {
                self::$_MANAGERS[ $key ] = new FS_Option_Manager(
                    $id,
                    $load,
                    $network_level_or_blog_id,
                    $autoload
                );
            } // If load required but not yet loaded, load.
            else if ( $load && ! self::$_MANAGERS[ $key ]->is_loaded() ) {
                self::$_MANAGERS[ $key ]->load();
            }

            return self::$_MANAGERS[ $key ];
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.3
         *
         * @param bool $flush
         */
        function load( $flush = false ) {
            $this->_logger->entrance();

            if ( ! $flush && isset( $this->_options ) ) {
                return;
            }

            if ( isset( $this->_options ) ) {
                // Clear prev options.
                $this->clear();
            }

            $option_name = $this->get_option_manager_name();

            if ( $this->_is_network_storage ) {
                $this->_options = get_site_option( $option_name );
            } else if ( $this->_blog_id > 0 ) {
                $this->_options = get_blog_option( $this->_blog_id, $option_name );
            } else {
                $this->_options = get_option( $option_name );
            }

            if ( is_string( $this->_options ) ) {
                $this->_options = json_decode( $this->_options );
            }

//					$this->_logger->info('get_option = ' . var_export($this->_options, true));

            if ( false === $this->_options ) {
                $this->clear();
            }
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.3
         *
         * @return bool
         */
        function is_loaded() {
            return isset( $this->_options );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.3
         *
         * @return bool
         */
        function is_empty() {
            return ( $this->is_loaded() && false === $this->_options );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @param bool $flush
         */
        function clear( $flush = false ) {
            $this->_logger->entrance();

            $this->_options = array();

            if ( $flush ) {
                $this->store();
            }
        }

        /**
         * Delete options manager from DB.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         */
        function delete() {
            $option_name = $this->get_option_manager_name();

            if ( $this->_is_network_storage ) {
                delete_site_option( $option_name );
            } else if ( $this->_blog_id > 0 ) {
                delete_blog_option( $this->_blog_id, $option_name );
            } else {
                delete_option( $option_name );
            }
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @param string $option
         * @param bool   $flush
         *
         * @return bool
         */
        function has_option( $option, $flush = false ) {
            if ( ! $this->is_loaded() || $flush ) {
                $this->load( $flush );
            }

            return array_key_exists( $option, $this->_options );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.3
         *
         * @param string $option
         * @param mixed  $default
         * @param bool   $flush
         *
         * @return mixed
         */
        function get_option( $option, $default = null, $flush = false ) {
            $this->_logger->entrance( 'option = ' . $option );

            if ( ! $this->is_loaded() || $flush ) {
                $this->load( $flush );
            }

            if ( is_array( $this->_options ) ) {
                $value = isset( $this->_options[ $option ] ) ?
                    $this->_options[ $option ] :
                    $default;
            } else if ( is_object( $this->_options ) ) {
                $value = isset( $this->_options->{$option} ) ?
                    $this->_options->{$option} :
                    $default;
            } else {
                $value = $default;
            }

            /**
             * If it's an object, return a clone of the object, otherwise,
             * external changes of the object will actually change the value
             * of the object in the option manager which may lead to an unexpected
             * behaviour and data integrity when a store() call is triggered.
             *
             * Example:
             *      $object1    = $options->get_option( 'object1' );
             *      $object1->x = 123;
             *
             *      $object2    = $options->get_option( 'object2' );
             *      $object2->y = 'dummy';
             *
             *      $options->set_option( 'object2', $object2, true );
             *
             * If we don't return a clone of option 'object1', setting 'object2'
             * will also store the updated value of 'object1' which is quite not
             * an expected behaviour.
             *
             * @author Vova Feldman
             */
            return is_object( $value ) ? clone $value : $value;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.3
         *
         * @param string $option
         * @param mixed  $value
         * @param bool   $flush
         */
        function set_option( $option, $value, $flush = false ) {
            $this->_logger->entrance( 'option = ' . $option );

            if ( ! $this->is_loaded() ) {
                $this->clear();
            }

            /**
             * If it's an object, store a clone of the object, otherwise,
             * external changes of the object will actually change the value
             * of the object in the options manager which may lead to an unexpected
             * behaviour and data integrity when a store() call is triggered.
             *
             * Example:
             *      $object1    = new stdClass();
             *      $object1->x = 123;
             *
             *      $options->set_option( 'object1', $object1 );
             *
             *      $object1->x = 456;
             *
             *      $options->set_option( 'object2', $object2, true );
             *
             * If we don't set the option as a clone of option 'object1', setting 'object2'
             * will also store the updated value of 'object1' ($object1->x = 456 instead of
             * $object1->x = 123) which is quite not an expected behaviour.
             *
             * @author Vova Feldman
             */
            $copy = is_object( $value ) ? clone $value : $value;

            if ( is_array( $this->_options ) ) {
                $this->_options[ $option ] = $copy;
            } else if ( is_object( $this->_options ) ) {
                $this->_options->{$option} = $copy;
            }

            if ( $flush ) {
                $this->store();
            }
        }

        /**
         * Unset option.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.3
         *
         * @param string $option
         * @param bool   $flush
         */
        function unset_option( $option, $flush = false ) {
            $this->_logger->entrance( 'option = ' . $option );

            if ( is_array( $this->_options ) ) {
                if ( ! isset( $this->_options[ $option ] ) ) {
                    return;
                }

                unset( $this->_options[ $option ] );

            } else if ( is_object( $this->_options ) ) {
                if ( ! isset( $this->_options->{$option} ) ) {
                    return;
                }

                unset( $this->_options->{$option} );
            }

            if ( $flush ) {
                $this->store();
            }
        }

        /**
         * Dump options to database.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.3
         */
        function store() {
            $this->_logger->entrance();

            $option_name = $this->get_option_manager_name();

            if ( $this->_logger->is_on() ) {
                $this->_logger->info( $option_name . ' = ' . var_export( $this->_options, true ) );
            }

            // Update DB.
            if ( $this->_is_network_storage ) {
                update_site_option( $option_name, $this->_options );
            } else if ( $this->_blog_id > 0 ) {
                update_blog_option( $this->_blog_id, $option_name, $this->_options );
            } else {
                update_option( $option_name, $this->_options, $this->_autoload );
            }
        }

        /**
         * Get options keys.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.3
         *
         * @return string[]
         */
        function get_options_keys() {
            if ( is_array( $this->_options ) ) {
                return array_keys( $this->_options );
            } else if ( is_object( $this->_options ) ) {
                return array_keys( get_object_vars( $this->_options ) );
            }

            return array();
        }

        #--------------------------------------------------------------------------------
        #region Migration
        #--------------------------------------------------------------------------------

        /**
         * Migrate options from site level.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         */
        function migrate_to_network() {
            $site_options = FS_Option_Manager::get_manager($this->_id, true, false);

            $options = is_object( $site_options->_options ) ?
                get_object_vars( $site_options->_options ) :
                $site_options->_options;

            if ( ! empty( $options ) ) {
                foreach ( $options as $key => $val ) {
                    $this->set_option( $key, $val, false );
                }

                $this->store();
            }
        }

        #endregion

        #--------------------------------------------------------------------------------
        #region Helper Methods
        #--------------------------------------------------------------------------------

        /**
         * @return string
         */
        private function get_option_manager_name() {
            return $this->_id;
        }

        #endregion
    }
