<?php
    /**
     * @package     Freemius
     * @copyright   Copyright (c) 2015, Freemius, Inc.
     * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since       2.5.1
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * Class FS_Lock
     *
     * @author Vova Feldman (@svovaf)
     * @since  2.5.1
     */
    class FS_Lock {
        /**
         * @var int Random ID representing the current PHP thread.
         */
        private static $_thread_id;
        /**
         * @var string
         */
        private $_lock_id;

        /**
         * @param string $lock_id
         */
        function __construct( $lock_id ) {
            if ( ! fs_starts_with( $lock_id, WP_FS___OPTION_PREFIX ) ) {
                $lock_id = WP_FS___OPTION_PREFIX . $lock_id;
            }

            $this->_lock_id = $lock_id;

            if ( ! isset( self::$_thread_id ) ) {
                self::$_thread_id = mt_rand( 0, 32000 );
            }
        }

        /**
         * Try to acquire lock. If the lock is already set or is being acquired by another locker, don't do anything.
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

            set_site_transient( $this->_lock_id, self::$_thread_id, $expiration );

            if ( $this->has_lock() ) {
                $this->lock($expiration);

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
            set_site_transient( $this->_lock_id, true, $expiration );
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
            return ( false !== get_site_transient( $this->_lock_id ) );
        }

        /**
         * Unlock the lock.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.1.0
         */
        function unlock() {
            delete_site_transient( $this->_lock_id );
        }

        /**
         * Checks if lock is currently acquired by the current locker.
         *
         * @return bool
         */
        protected function has_lock() {
            return ( self::$_thread_id == get_site_transient( $this->_lock_id ) );
        }
    }