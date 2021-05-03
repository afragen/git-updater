<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.2.3
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	class FS_AffiliateTerms extends FS_Scope_Entity {

        #region Properties

        /**
         * @var bool
         */
        public $is_active;
        /**
         * @var string Enum: `affiliation` or `rewards`. Defaults to `affiliation`.
         */
        public $type;
        /**
         * @var string Enum: `payout` or `credit`. Defaults to `payout`.
         */
        public $reward_type;
        /**
         * If `first`, the referral will be attributed to the first visited source containing the affiliation link that
         * was clicked.
         *
         * @var string Enum: `first` or `last`. Defaults to `first`.
         */
        public $referral_attribution;
        /**
         * @var int Defaults to `30`, `0` for session cookie, and `null` for endless cookie (until cookies are cleaned).
         */
        public $cookie_days;
        /**
         * @var int
         */
        public $commission;
        /**
         * @var string Enum: `percentage` or `dollar`. Defaults to `percentage`.
         */
        public $commission_type;
        /**
         * @var null|int Defaults to `0` (affiliate only on first payment). `null` for commission for all renewals. If
         *          greater than `0`, affiliate will get paid for all renewals for `commission_renewals_days` days after
         *          the initial upgrade/purchase.
         */
        public $commission_renewals_days;
        /**
         * @var int Only cents and no percentage. In US cents, e.g.: 100 = $1.00. Defaults to `null`.
         */
        public $install_commission;
        /**
         * @var string Required default target link, e.g.: pricing page.
         */
        public $default_url;
		/**
		 * @var string One of the following: 'all', 'new_customer', 'new_user'.
		 *             If 'all' - reward for any user type.
		 *             If 'new_customer' - reward only for new customers.
		 *             If 'new_user' - reward only for new users.
		 */
        public $reward_customer_type;
        /**
         * @var int Defaults to `0` (affiliate only on directly affiliated links). `null` if an affiliate will get
         *          paid for all customers' lifetime payments. If greater than `0`, an affiliate will get paid for all
         *          customer payments for `future_payments_days` days after the initial payment.
         */
        public $future_payments_days;
        /**
         * @var bool If `true`, allow referrals from social sites.
         */
        public $is_social_allowed;
        /**
         * @var bool If `true`, allow conversions without HTTP referrer header at all.
         */
        public $is_app_allowed;
        /**
         * @var bool If `true`, allow referrals from any site.
         */
        public $is_any_site_allowed;

        #endregion Properties

        /**
         * @author Leo Fajardo (@leorw)
         *
         * @return string
         */
        function get_formatted_commission()
        {
            return ( 'dollar' === $this->commission_type ) ?
                ( '$' . $this->commission ) :
                ( $this->commission . '%' );
        }

        /**
         * @author Leo Fajardo (@leorw)
         *
         * @return bool
         */
        function has_lifetime_commission() {
            return ( 0 !== $this->future_payments_days );
        }

        /**
         * @author Leo Fajardo (@leorw)
         *
         * @return bool
         */
        function is_session_cookie() {
            return ( 0 == $this->cookie_days );
        }

        /**
         * @author Leo Fajardo (@leorw)
         *
         * @return bool
         */
        function has_renewals_commission() {
            return ( is_null( $this->commission_renewals_days ) || $this->commission_renewals_days > 0 );
        }
    }