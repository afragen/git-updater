<?php
    /**
     * @package     Freemius
     * @copyright   Copyright (c) 2015, Freemius, Inc.
     * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since       2.1.0
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * Class FS_User_Lock
     */
    class FS_User_Lock {
        /**
         * @var int
         */
        private $_wp_user_id;
        /**
         * @var int
         */
        private $_thread_id;

        #--------------------------------------------------------------------------------
        #region Singleton
        #--------------------------------------------------------------------------------

        /**
         * @var FS_User_Lock
         */
        private static $_instance;

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.1.0
         *
         * @return FS_User_Lock
         */
        static function instance() {
            if ( ! isset( self::$_instance ) ) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }

        #endregion

        private function __construct() {
            $this->_wp_user_id = Freemius::get_current_wp_user_id();
            $this->_thread_id  = mt_rand( 0, 32000 );
        }


        /**
         * Try to acquire lock. If the lock is already set or is being acquired by another locker, don't do anything.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.1.0
         *
         * @param int $expiration
         *
         * @return bool TRUE if successfully acquired lock.
         */
        function try_lock( $expiration = 0 ) {
            if ( $this->is_locked() ) {
                // Already locked.
                return false;
            }

            set_site_transient( "locked_{$this->_wp_user_id}", $this->_thread_id, $expiration );

            if ( $this->has_lock() ) {
                set_site_transient( "locked_{$this->_wp_user_id}", true, $expiration );

                return true;
            }

            return false;
        }

        /**
         * Acquire lock regardless if it's already acquired by another locker or not.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.1.0
         *
         * @param int $expiration
         */
        function lock( $expiration = 0 ) {
            set_site_transient( "locked_{$this->_wp_user_id}", true, $expiration );
        }

        /**
         * Checks if lock is currently acquired.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.1.0
         *
         * @return bool
         */
        function is_locked() {
            return ( false !== get_site_transient( "locked_{$this->_wp_user_id}" ) );
        }

        /**
         * Unlock the lock.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.1.0
         */
        function unlock() {
            delete_site_transient( "locked_{$this->_wp_user_id}" );
        }

        /**
         * Checks if lock is currently acquired by the current locker.
         *
         * @return bool
         */
        private function has_lock() {
            return ( $this->_thread_id == get_site_transient( "locked_{$this->_wp_user_id}" ) );
        }
    }