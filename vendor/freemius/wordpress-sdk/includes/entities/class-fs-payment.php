<?php
    /**
     * @package     Freemius
     * @copyright   Copyright (c) 2016, Freemius, Inc.
     * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since       1.0.0
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    class FS_Payment extends FS_Entity {

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
        public $install_id;
        /**
         * @var number
         */
        public $subscription_id;
        /**
         * @var number
         */
        public $plan_id;
        /**
         * @var number
         */
        public $license_id;
        /**
         * @var float
         */
        public $gross;
        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @var string One of the following: `usd`, `gbp`, `eur`.
         */
        public $currency;
        /**
         * @var number
         */
        public $bound_payment_id;
        /**
         * @var string
         */
        public $external_id;
        /**
         * @var string
         */
        public $gateway;
        /**
         * @var string ISO 3166-1 alpha-2 - two-letter country code.
         *
         * @link http://www.wikiwand.com/en/ISO_3166-1_alpha-2
         */
        public $country_code;
        /**
         * @var string
         */
        public $vat_id;
        /**
         * @var float Actual Tax / VAT in $$$
         */
        public $vat;
        /**
         * @var int Payment source.
         */
        public $source = 0;

        #endregion Properties

        const CURRENCY_USD = 'usd';
        const CURRENCY_GBP = 'gbp';
        const CURRENCY_EUR = 'eur';

        /**
         * @param object|bool $payment
         */
        function __construct( $payment = false ) {
            parent::__construct( $payment );
        }

        static function get_type() {
            return 'payment';
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.0
         *
         * @return bool
         */
        function is_refund() {
            return ( parent::is_valid_id( $this->bound_payment_id ) && 0 > $this->gross );
        }

        /**
         * Checks if the payment was migrated from another platform.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.2
         *
         * @return bool
         */
        function is_migrated() {
            return ( 0 != $this->source );
        }

        /**
         * Returns the gross in this format:
         *  `{symbol}{amount | 2 decimal digits} {currency | uppercase}`
         *
         * Examples: £9.99 GBP, -£9.99 GBP.
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @return string
         */
        function formatted_gross()
        {
            return (
                ( $this->gross < 0 ? '-' : '' ) .
                $this->get_symbol() .
                number_format( abs( $this->gross ), 2, '.', ',' ) . ' ' .
                strtoupper( $this->currency )
            );
        }

        /**
         * A map between supported currencies with their symbols.
         *
         * @var array<string,string>
         */
        static $CURRENCY_2_SYMBOL;

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @return string
         */
        private function get_symbol() {
            if ( ! isset( self::$CURRENCY_2_SYMBOL ) ) {
                // Lazy load.
                self::$CURRENCY_2_SYMBOL = array(
                    self::CURRENCY_USD => '$',
                    self::CURRENCY_GBP => '&pound;',
                    self::CURRENCY_EUR => '&euro;',
                );
            }

            return self::$CURRENCY_2_SYMBOL[ $this->currency ];
        }
    }