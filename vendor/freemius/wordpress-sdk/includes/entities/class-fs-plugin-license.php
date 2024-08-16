<?php
    /**
     * @package     Freemius
     * @copyright   Copyright (c) 2015, Freemius, Inc.
     * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since       1.0.5
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * Class FS_Plugin_License
     */
    class FS_Plugin_License extends FS_Entity {

        #region Properties

        /**
         * @var number
         */
        public $plugin_id;
        /**
         * @var number
         */
        public $user_id;
        /**
         * @var number
         */
        public $plan_id;
        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @var string
         */
        public $parent_plan_name;
        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @var string
         */
        public $parent_plan_title;
        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @var number
         */
        public $parent_license_id;
        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.4.0
         *
         * @var array
         */
        public $products;
        /**
         * @var number
         */
        public $pricing_id;
        /**
         * @var int|null
         */
        public $quota;
        /**
         * @var int
         */
        public $activated;
        /**
         * @var int
         */
        public $activated_local;
        /**
         * @var string
         */
        public $expiration;
        /**
         * @var string
         */
        public $secret_key;
        /**
         * @var bool
         */
        public $is_whitelabeled;
        /**
         * @var bool $is_free_localhost Defaults to true. If true, allow unlimited localhost installs with the same
         *      license.
         */
        public $is_free_localhost;
        /**
         * @var bool $is_block_features Defaults to true. If false, don't block features after license expiry - only
         *      block updates and support.
         */
        public $is_block_features;
        /**
         * @var bool
         */
        public $is_cancelled;

        #endregion Properties

        /**
         * @param stdClass|bool $license
         */
        function __construct( $license = false ) {
            parent::__construct( $license );
        }

        /**
         * Get entity type.
         *
         * @return string
         */
        static function get_type() {
            return 'license';
        }

        /**
         * Check how many site activations left.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.5
         *
         * @return int
         */
        function left() {
            if ( ! $this->is_features_enabled() ) {
                return 0;
            }

            if ( $this->is_unlimited() ) {
                return 999;
            }

            return ( $this->quota - $this->activated - ( $this->is_free_localhost ? 0 : $this->activated_local ) );
        }

        /**
         * Check if single site license.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.8.1
         *
         * @return bool
         */
        function is_single_site() {
            return ( is_numeric( $this->quota ) && 1 == $this->quota );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.5
         *
         * @return bool
         */
        function is_expired() {
            return ! $this->is_lifetime() && ( strtotime( $this->expiration ) < WP_FS__SCRIPT_START_TIME );
        }

        /**
         * Check if license is not expired.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1
         *
         * @return bool
         */
        function is_valid() {
            return ! $this->is_expired();
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @return bool
         */
        function is_lifetime() {
            return is_null( $this->expiration );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.0
         *
         * @return bool
         */
        function is_unlimited() {
            return is_null( $this->quota );
        }

        /**
         * Check if license is fully utilized.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @param bool|null $is_localhost
         *
         * @return bool
         */
        function is_utilized( $is_localhost = null ) {
            if ( is_null( $is_localhost ) ) {
                $is_localhost = WP_FS__IS_LOCALHOST_FOR_SERVER;
            }

            if ( $this->is_unlimited() ) {
                return false;
            }

            return ! ( $this->is_free_localhost && $is_localhost ) &&
                   ( $this->quota <= $this->activated + ( $this->is_free_localhost ? 0 : $this->activated_local ) );
        }

        /**
         * Check if license can be activated.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param bool|null $is_localhost
         *
         * @return bool
         */
        function can_activate( $is_localhost = null ) {
            return ! $this->is_utilized( $is_localhost ) && $this->is_features_enabled();
        }

        /**
         * Check if license can be activated on a given number of production and localhost sites.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param int $production_count
         * @param int $localhost_count
         *
         * @return bool
         */
        function can_activate_bulk( $production_count, $localhost_count ) {
            if ( $this->is_unlimited() ) {
                return true;
            }

            /**
             * For simplicity, the logic will work as following: when given X sites to activate the license on, if it's
             * possible to activate on ALL of them, do the activation. If it's not possible to activate on ALL of them,
             * do NOT activate on any of them.
             */
            return ( $this->quota >= $this->activated + $production_count + ( $this->is_free_localhost ? 0 : $this->activated_local + $localhost_count ) );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1
         *
         * @return bool
         */
        function is_active() {
            return ( ! $this->is_cancelled );
        }

        /**
         * Check if license's plan features are enabled.
         *
         *  - Either if plan not expired
         *  - If expired, based on the configuration to block features or not.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @return bool
         */
        function is_features_enabled() {
            return $this->is_active() && ( ! $this->is_block_features || ! $this->is_expired() );
        }

        /**
         * Subscription considered to be new without any payments
         * if the license expires in less than 24 hours
         * from the license creation.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @return bool
         */
        function is_first_payment_pending() {
            if ( $this->is_lifetime() ) {
                return false;
            }

            return ( WP_FS__TIME_24_HOURS_IN_SEC >= strtotime( $this->expiration ) - strtotime( $this->created ) );
        }

        /**
         * @return int
         */
        function total_activations() {
            return ( $this->activated + $this->activated_local );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since 2.3.1
         *
         * @return string
         */
        function get_html_escaped_masked_secret_key() {
            return self::mask_secret_key_for_html( $this->secret_key );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.3.1
         *
         * @param string $secret_key
         *
         * @return string
         */
        static function mask_secret_key_for_html( $secret_key ) {
            return (
                // Initial 6 chars - sk_ABC
                htmlspecialchars( substr( $secret_key, 0, 6 ) ) .
                // Masking
                str_pad( '', ( strlen( $secret_key ) - 9 ) * 6, '&bull;' ) .
                // Last 3 chars.
                htmlspecialchars( substr( $secret_key, - 3 ) )
            );
        }
    }
