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

	class FS_Plan_Manager {
		/**
		 * @var FS_Plan_Manager
		 */
		private static $_instance;

		/**
		 * @return FS_Plan_Manager
		 */
		static function instance() {
			if ( ! isset( self::$_instance ) ) {
				self::$_instance = new FS_Plan_Manager();
			}

			return self::$_instance;
		}

		private function __construct() {
		}

		/**
		 * @param FS_Plugin_License[] $licenses
		 *
		 * @return bool
		 */
		function has_premium_license( $licenses ) {
			if ( is_array( $licenses ) ) {
				/**
				 * @var FS_Plugin_License[] $licenses
				 */
				foreach ( $licenses as $license ) {
					if ( ! $license->is_utilized() && $license->is_features_enabled() ) {
						return true;
					}
				}
			}

			return false;
		}

		/**
		 * Check if plugin has any paid plans.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.7
		 *
		 * @param FS_Plugin_Plan[] $plans
		 *
		 * @return bool
		 */
		function has_paid_plan( $plans ) {
			if ( ! is_array( $plans ) || 0 === count( $plans ) ) {
				return false;
			}

			/**
			 * @var FS_Plugin_Plan[] $plans
			 */
			for ( $i = 0, $len = count( $plans ); $i < $len; $i ++ ) {
				if ( ! $plans[ $i ]->is_free() ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Check if plugin has any free plan, or is it premium only.
		 *
		 * Note: If no plans configured, assume plugin is free.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.7
		 *
		 * @param FS_Plugin_Plan[] $plans
		 *
		 * @return bool
		 */
		function has_free_plan( $plans ) {
			if ( ! is_array( $plans ) || 0 === count( $plans ) ) {
				return true;
			}

			/**
			 * @var FS_Plugin_Plan[] $plans
			 */
			for ( $i = 0, $len = count( $plans ); $i < $len; $i ++ ) {
				if ( $plans[ $i ]->is_free() ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Find all plans that have trial.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.9
		 *
		 * @param FS_Plugin_Plan[] $plans
		 *
		 * @return FS_Plugin_Plan[]
		 */
		function get_trial_plans( $plans ) {
			$trial_plans = array();

			if ( is_array( $plans ) && 0 < count( $plans ) ) {
				/**
				 * @var FS_Plugin_Plan[] $plans
				 */
				for ( $i = 0, $len = count( $plans ); $i < $len; $i ++ ) {
					if ( $plans[ $i ]->has_trial() ) {
						$trial_plans[] = $plans[ $i ];
					}
				}
			}

			return $trial_plans;
		}

		/**
		 * Check if plugin has any trial plan.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.9
		 *
		 * @param FS_Plugin_Plan[] $plans
		 *
		 * @return bool
		 */
		function has_trial_plan( $plans ) {
			if ( ! is_array( $plans ) || 0 === count( $plans ) ) {
				return true;
			}

			/**
			 * @var FS_Plugin_Plan[] $plans
			 */
			for ( $i = 0, $len = count( $plans ); $i < $len; $i ++ ) {
				if ( $plans[ $i ]->has_trial() ) {
					return true;
				}
			}

			return false;
		}
	}