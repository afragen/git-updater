<?php
    /**
     * @package     Freemius
     * @copyright   Copyright (c) 2015, Freemius, Inc.
     * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since       2.2.1
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    if ( ! function_exists( 'fs_get_plugins' ) ) {
        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.2.1
         *
         * @param bool $delete_cache
         *
         * @return array
         */
        function fs_get_plugins( $delete_cache = false ) {
            $cached_plugins = wp_cache_get( 'plugins', 'plugins' );
            if ( ! is_array( $cached_plugins ) ) {
                $cached_plugins = array();
            }

            $plugin_folder = '';
            if ( isset( $cached_plugins[ $plugin_folder ] ) ) {
                $plugins = $cached_plugins[ $plugin_folder ];
            } else {
                if ( ! function_exists( 'get_plugins' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }

                $plugins = get_plugins();

                if ( $delete_cache && is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
                    wp_cache_delete( 'plugins', 'plugins' );
                }
            }

            return $plugins;
        }
    }