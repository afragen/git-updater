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

    require_once WP_FS__DIR_INCLUDES . '/class-fs-lock.php';

    /**
     * Class FS_User_Lock
     */
    class FS_User_Lock {
        /**
         * @var FS_Lock
         */
        private $_lock;

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
            $current_user_id = Freemius::get_current_wp_user_id();

            $this->_lock = new FS_Lock( "locked_{$current_user_id}" );
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
            return $this->_lock->try_lock( $expiration );
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
            $this->_lock->lock( $expiration );
        }

        /**
         * Unlock the lock.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.1.0
         */
        function unlock() {
            $this->_lock->unlock();
        }
    }