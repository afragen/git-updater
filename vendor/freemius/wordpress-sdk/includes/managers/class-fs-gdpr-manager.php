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

    class FS_GDPR_Manager {
        /**
         * @var FS_Option_Manager
         */
        private $_storage;
        /**
         * @var array {
         * @type bool $required           Are GDPR rules apply on the current context admin.
         * @type bool $show_opt_in_notice Should the marketing and offers opt-in message be shown to the admin or not. If not set, defaults to `true`.
         * @type int  $notice_shown_at    Last time the special GDPR opt-in message was shown to the current admin.
         * }
         */
        private $_data;
        /**
         * @var int
         */
        private $_wp_user_id;
        /**
         * @var string
         */
        private $_option_name;
        /**
         * @var FS_Admin_Notices
         */
        private $_notices;

        #--------------------------------------------------------------------------------
        #region Singleton
        #--------------------------------------------------------------------------------

        /**
         * @var FS_GDPR_Manager
         */
        private static $_instance;

        /**
         * @return FS_GDPR_Manager
         */
        public static function instance() {
            if ( ! isset( self::$_instance ) ) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }

        #endregion

        private function __construct() {
            $this->_storage     = FS_Option_Manager::get_manager( WP_FS__GDPR_OPTION_NAME, true, true );
            $this->_wp_user_id  = Freemius::get_current_wp_user_id();
            $this->_option_name = "u{$this->_wp_user_id}";
            $this->_data        = $this->_storage->get_option( $this->_option_name, array() );
            $this->_notices     = FS_Admin_Notices::instance( 'all_admins', '', '', true );

            if ( ! is_array( $this->_data ) ) {
                $this->_data = array();
            }
        }

        /**
         * Update a GDPR option for the current admin and store it.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.1.0
         *
         * @param string $name
         * @param mixed  $value
         */
        private function update_option( $name, $value ) {
            $this->_data[ $name ] = $value;

            $this->_storage->set_option( $this->_option_name, $this->_data, true );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.1.0
         *
         * @return bool|null
         */
        public function is_required() {
            return isset( $this->_data['required'] ) ?
                $this->_data['required'] :
                null;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.1.0
         *
         * @param bool $is_required
         */
        public function store_is_required( $is_required ) {
            $this->update_option( 'required', $is_required );
        }

        /**
         * Checks if the GDPR opt-in sticky notice is currently shown.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.1.0
         *
         * @return bool
         */
        public function is_opt_in_notice_shown() {
            return $this->_notices->has_sticky( "gdpr_optin_actions_{$this->_wp_user_id}", true );
        }

        /**
         * Remove the GDPR opt-in sticky notice.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.1.0
         */
        public function remove_opt_in_notice() {
            $this->_notices->remove_sticky( "gdpr_optin_actions_{$this->_wp_user_id}", true );

            $this->disable_opt_in_notice();
        }

        /**
         * Prevents the opt-in message from being added/shown.
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.1.0
         */
        public function disable_opt_in_notice() {
            $this->update_option( 'show_opt_in_notice', false );
        }

        /**
         * Checks if a GDPR opt-in message needs to be shown to the current admin.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.1.0
         *
         * @return bool
         */
        public function should_show_opt_in_notice() {
            return (
                ! isset( $this->_data['show_opt_in_notice'] ) ||
                true === $this->_data['show_opt_in_notice']
            );
        }

        /**
         * Get the last time the GDPR opt-in notice was shown.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.1.0
         *
         * @return false|int
         */
        public function last_time_notice_was_shown() {
            return isset( $this->_data['notice_shown_at'] ) ?
                $this->_data['notice_shown_at'] :
                false;
        }

        /**
         * Update the timestamp of the last time the GDPR opt-in message was shown to now.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.1.0
         */
        public function notice_was_just_shown() {
            $this->update_option( 'notice_shown_at', WP_FS__SCRIPT_START_TIME );
        }

        /**
         * @param string      $message
         * @param string|null $plugin_title
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.1.0
         */
        public function add_opt_in_sticky_notice( $message, $plugin_title = null ) {
            $this->_notices->add_sticky(
                $message,
                "gdpr_optin_actions_{$this->_wp_user_id}",
                '',
                'promotion',
                true,
                $this->_wp_user_id,
                $plugin_title,
                true
            );
        }
    }