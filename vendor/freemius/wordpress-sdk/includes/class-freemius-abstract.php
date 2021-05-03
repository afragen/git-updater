<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.0.7
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}


	/**
	 * - Each instance of Freemius class represents a single plugin
	 * install by a single user (the installer of the plugin).
	 *
	 * - Each website can only have one install of the same plugin.
	 *
	 * - Install entity is only created after a user connects his account with Freemius.
	 *
	 * Class Freemius_Abstract
	 */
	abstract class Freemius_Abstract {

		#----------------------------------------------------------------------------------
		#region Identity
		#----------------------------------------------------------------------------------

		/**
		 * Check if user has connected his account (opted-in).
		 *
		 * Note:
		 *      If the user opted-in and opted-out on a later stage,
		 *      this will still return true. If you want to check if the
		 *      user is currently opted-in, use:
		 *          `$fs->is_registered() && $fs->is_tracking_allowed()`
		 *
		 * @since 1.0.1
		 * @return bool
		 */
		abstract function is_registered();

		/**
		 * Check if the user skipped connecting the account with Freemius.
		 *
		 * @since 1.0.7
		 *
		 * @return bool
		 */
		abstract function is_anonymous();

		/**
		 * Check if the user currently in activation mode.
		 *
		 * @since 1.0.7
		 *
		 * @return bool
		 */
		abstract function is_activation_mode();

		#endregion

		#----------------------------------------------------------------------------------
		#region Usage Tracking
		#----------------------------------------------------------------------------------

		/**
		 * Returns TRUE if the user opted-in and didn't disconnect (opt-out).
		 *
		 * @author Leo Fajardo (@leorw)
		 * @since 1.2.1.5
		 *
		 * @return bool
		 */
		abstract function is_tracking_allowed();

		/**
		 * Returns TRUE if the user never opted-in or manually opted-out.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since 1.2.1.5
		 *
		 * @return bool
		 */
		function is_tracking_prohibited() {
			return ! $this->is_registered() || ! $this->is_tracking_allowed();
		}

		/**
		 * Opt-out from usage tracking.
		 *
		 * Note: This will not delete the account information but will stop all tracking.
		 *
		 * Returns:
		 *  1. FALSE  - If the user never opted-in.
		 *  2. TRUE   - If successfully opted-out.
		 *  3. object - API Result on failure.
		 *
		 * @author Leo Fajardo (@leorw)
		 * @since  1.2.1.5
		 *
		 * @return bool|object
		 */
		abstract function stop_tracking();

		/**
		 * Opt-in back into usage tracking.
		 *
		 * Note: This will only work if the user opted-in previously.
		 *
		 * Returns:
		 *  1. FALSE  - If the user never opted-in.
		 *  2. TRUE   - If successfully opted-in back to usage tracking.
		 *  3. object - API result on failure.
		 *
		 * @author Leo Fajardo (@leorw)
		 * @since  1.2.1.5
		 *
		 * @return bool|object
		 */
		abstract function allow_tracking();

		#endregion

		#----------------------------------------------------------------------------------
		#region Module Type
		#----------------------------------------------------------------------------------

		/**
		 * Checks if the plugin's type is "plugin". The other type is "theme".
		 *
		 * @author Leo Fajardo (@leorw)
		 * @since  1.2.2
		 *
		 * @return bool
		 */
		abstract function is_plugin();

		/**
		 * Checks if the module type is "theme". The other type is "plugin".
		 *
		 * @author Leo Fajardo (@leorw)
		 * @since  1.2.2
		 *
		 * @return bool
		 */
		function is_theme() {
			return ( ! $this->is_plugin() );
		}

		#endregion

		#----------------------------------------------------------------------------------
		#region Permissions
		#----------------------------------------------------------------------------------

		/**
		 * Check if plugin must be WordPress.org compliant.
		 *
		 * @since 1.0.7
		 *
		 * @return bool
		 */
		abstract function is_org_repo_compliant();

		/**
		 * Check if plugin is allowed to install executable files.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.5
		 *
		 * @return bool
		 */
		function is_allowed_to_install() {
			return ( $this->is_premium() || ! $this->is_org_repo_compliant() );
		}

		#endregion

		/**
		 * Check if user in trial or in free plan (not paying).
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.4
		 *
		 * @return bool
		 */
		function is_not_paying() {
			return ( $this->is_trial() || $this->is_free_plan() );
		}

		/**
		 * Check if the user has an activated and valid paid license on current plugin's install.
		 *
		 * @since 1.0.9
		 *
		 * @return bool
		 */
		abstract function is_paying();

		/**
		 * Check if the user is paying or in trial.
		 *
		 * @since 1.0.9
		 *
		 * @return bool
		 */
		function is_paying_or_trial() {
			return ( $this->is_paying() || $this->is_trial() );
		}

		/**
		 * Check if user in a trial or have feature enabled license.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.7
		 *
		 * @return bool
		 */
		abstract function can_use_premium_code();

		#----------------------------------------------------------------------------------
		#region Premium Only
		#----------------------------------------------------------------------------------

		/**
		 * All logic wrapped in methods with "__premium_only()" suffix will be only
		 * included in the premium code.
		 *
		 * Example:
		 *      if ( freemius()->is__premium_only() ) {
		 *          ...
		 *      }
		 */

		/**
		 * Returns true when running premium plugin code.
		 *
		 * @since 1.0.9
		 *
		 * @return bool
		 */
		function is__premium_only() {
			return $this->is_premium();
		}

		/**
		 * Check if the user has an activated and valid paid license on current plugin's install.
		 *
		 * @since 1.0.9
		 *
		 * @return bool
		 *
		 */
		function is_paying__premium_only() {
			return ( $this->is__premium_only() && $this->is_paying() );
		}

		/**
		 * All code wrapped in this statement will be only included in the premium code.
		 *
		 * @since  1.0.9
		 *
		 * @param string $plan  Plan name.
		 * @param bool   $exact If true, looks for exact plan. If false, also check "higher" plans.
		 *
		 * @return bool
		 */
		function is_plan__premium_only( $plan, $exact = false ) {
			return ( $this->is_premium() && $this->is_plan( $plan, $exact ) );
		}

		/**
		 * Check if plan matches active license' plan or active trial license' plan.
		 *
		 * All code wrapped in this statement will be only included in the premium code.
		 *
		 * @since  1.0.9
		 *
		 * @param string $plan  Plan name.
		 * @param bool   $exact If true, looks for exact plan. If false, also check "higher" plans.
		 *
		 * @return bool
		 */
		function is_plan_or_trial__premium_only( $plan, $exact = false ) {
			return ( $this->is_premium() && $this->is_plan_or_trial( $plan, $exact ) );
		}

		/**
		 * Check if the user is paying or in trial.
		 *
		 * All code wrapped in this statement will be only included in the premium code.
		 *
		 * @since 1.0.9
		 *
		 * @return bool
		 */
		function is_paying_or_trial__premium_only() {
			return $this->is_premium() && $this->is_paying_or_trial();
		}

		/**
		 * Check if the user has an activated and valid paid license on current plugin's install.
		 *
		 * @since      1.0.4
		 *
		 * @return bool
		 *
		 * @deprecated Method name is confusing since it's not clear from the name the code will be removed.
		 * @using      Alias to is_paying__premium_only()
		 */
		function is_paying__fs__() {
			return $this->is_paying__premium_only();
		}

		/**
		 * Check if user in a trial or have feature enabled license.
		 *
		 * All code wrapped in this statement will be only included in the premium code.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.9
		 *
		 * @return bool
		 */
		function can_use_premium_code__premium_only() {
			return $this->is_premium() && $this->can_use_premium_code();
		}

		#endregion

		#----------------------------------------------------------------------------------
		#region Trial
		#----------------------------------------------------------------------------------

		/**
		 * Check if the user in a trial.
		 *
		 * @since 1.0.3
		 *
		 * @return bool
		 */
		abstract function is_trial();

		/**
		 * Check if trial already utilized.
		 *
		 * @since 1.0.9
		 *
		 * @return bool
		 */
		abstract function is_trial_utilized();

		#endregion

		#----------------------------------------------------------------------------------
		#region Plans
		#----------------------------------------------------------------------------------

		/**
		 * Check if the user is on the free plan of the product.
		 *
		 * @since 1.0.4
		 *
		 * @return bool
		 */
		abstract function is_free_plan();

		/**
		 * @since  1.0.2
		 *
		 * @param string $plan  Plan name.
		 * @param bool   $exact If true, looks for exact plan. If false, also check "higher" plans.
		 *
		 * @return bool
		 */
		abstract function is_plan( $plan, $exact = false );

		/**
		 * Check if plan based on trial. If not in trial mode, should return false.
		 *
		 * @since  1.0.9
		 *
		 * @param string $plan  Plan name.
		 * @param bool   $exact If true, looks for exact plan. If false, also check "higher" plans.
		 *
		 * @return bool
		 */
		abstract function is_trial_plan( $plan, $exact = false );

		/**
		 * Check if plan matches active license' plan or active trial license' plan.
		 *
		 * @since  1.0.9
		 *
		 * @param string $plan  Plan name.
		 * @param bool   $exact If true, looks for exact plan. If false, also check "higher" plans.
		 *
		 * @return bool
		 */
		function is_plan_or_trial( $plan, $exact = false ) {
			return $this->is_plan( $plan, $exact ) ||
			       $this->is_trial_plan( $plan, $exact );
		}

		/**
		 * Check if plugin has any paid plans.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.7
		 *
		 * @return bool
		 */
		abstract function has_paid_plan();

		/**
		 * Check if plugin has any free plan, or is it premium only.
		 *
		 * Note: If no plans configured, assume plugin is free.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.7
		 *
		 * @return bool
		 */
		abstract function has_free_plan();

		/**
		 * Check if plugin is premium only (no free plans).
		 *
		 * NOTE: is__premium_only() is very different method, don't get confused.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.9
		 *
		 * @return bool
		 */
		abstract function is_only_premium();

		/**
		 * Check if module has a premium code version.
		 *
		 * Serviceware module might be freemium without any
		 * premium code version, where the paid features
		 * are all part of the service.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.2.1.6
		 *
		 * @return bool
		 */
		abstract function has_premium_version();

		/**
		 * Check if module has any release on Freemius,
		 * or all plugin's code is on WordPress.org (Serviceware).
		 *
		 * @return bool
		 */
		function has_release_on_freemius() {
			return ! $this->is_org_repo_compliant() ||
			       $this->has_premium_version();
		}

		/**
		 * Checks if it's a freemium plugin.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.9
		 *
		 * @return bool
		 */
		function is_freemium() {
			return $this->has_paid_plan() &&
			       $this->has_free_plan();
		}

		/**
		 * Check if module has only one plan.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.2.1.7
		 *
		 * @return bool
		 */
		abstract function is_single_plan();

		#endregion

		/**
		 * Check if running payments in sandbox mode.
		 *
		 * @since 1.0.4
		 *
		 * @return bool
		 */
		abstract function is_payments_sandbox();

		/**
		 * Check if running test vs. live plugin.
		 *
		 * @since 1.0.5
		 *
		 * @return bool
		 */
		abstract function is_live();

		/**
		 * Check if running premium plugin code.
		 *
		 * @since 1.0.5
		 *
		 * @return bool
		 */
		abstract function is_premium();

		/**
		 * Get upgrade URL.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.2
		 *
		 * @param string $period Billing cycle.
		 *
		 * @return string
		 */
		abstract function get_upgrade_url( $period = WP_FS__PERIOD_ANNUALLY );

		/**
		 * Check if Freemius was first added in a plugin update.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.5
		 *
		 * @return bool
		 */
		function is_plugin_update() {
			return ! $this->is_plugin_new_install();
		}

		/**
		 * Check if Freemius was part of the plugin when the user installed it first.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.5
		 *
		 * @return bool
		 */
		abstract function is_plugin_new_install();

		#----------------------------------------------------------------------------------
		#region Marketing
		#----------------------------------------------------------------------------------

		/**
		 * Check if current user purchased any other plugins before.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.9
		 *
		 * @return bool
		 */
		abstract function has_purchased_before();

		/**
		 * Check if current user classified as an agency.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.9
		 *
		 * @return bool
		 */
		abstract function is_agency();

		/**
		 * Check if current user classified as a developer.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.9
		 *
		 * @return bool
		 */
		abstract function is_developer();

		/**
		 * Check if current user classified as a business.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.9
		 *
		 * @return bool
		 */
		abstract function is_business();

		#endregion
	}