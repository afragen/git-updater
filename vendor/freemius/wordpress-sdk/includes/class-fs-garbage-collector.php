<?php
    /**
     * @package   Freemius
     * @copyright Copyright (c) 2015, Freemius, Inc.
     * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since     2.6.0
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    interface FS_I_Garbage_Collector {
        function clean();
    }

    class FS_Product_Garbage_Collector implements FS_I_Garbage_Collector {
        /**
         * @var FS_Options
         */
        private $_accounts;

        /**
         * @var string[]
         */
        private $_options_names;

        /**
         * @var string
         */
        private $_type;

        /**
         * @var string
         */
        private $_plural_type;

        /**
         * @var array<string, int> Map of product slugs to their last load timestamp, only for products that are not active.
         */
        private $_gc_timestamp;

        /**
         * @var array<string, array<string, mixed>> Map of product slugs to their data, as stored by the primary storage of `Freemius` class.
         */
        private $_storage_data;

        function __construct( FS_Options $_accounts, $option_names, $type ) {
            $this->_accounts      = $_accounts;
            $this->_options_names = $option_names;
            $this->_type          = $type;
            $this->_plural_type   = ( $type . 's' );
        }

        function clean() {
            $this->_gc_timestamp  = $this->_accounts->get_option( 'gc_timestamp', array() );
            $this->_storage_data  = $this->_accounts->get_option( $this->_type . '_data', array() );

            $options            = $this->load_options();
            $has_updated_option = false;

            $filtered_products         = $this->get_filtered_products();
            $products_to_clean         = $filtered_products['products_to_clean'];
            $active_products_by_id_map = $filtered_products['active_products_by_id_map'];

            foreach( $products_to_clean as $product ) {
                $slug = $product->slug;

                // Clear the product's data.
                foreach( $options as $option_name => $option ) {
                    $updated = false;

                    /**
                     * We expect to deal with only array like options here.
                     * @todo - Refactor this to create dedicated GC classes for every option, then we can make the code mode predictable.
                     *       For example, depending on data integrity of `plugins` we can still miss something entirely in the `plugin_data` or vice-versa.
                     *       A better algorithm is to iterate over all options individually in separate classes and check against primary storage to see if those can be garbage collected.
                     *       But given the chance of data integrity issue is very low, we let this run for now and gather feedback.
                     */
                    if ( ! is_array( $option ) ) {
                        continue;
                    }

                    if ( array_key_exists( $slug, $option ) ) {
                        unset( $option[ $slug ] );
                        $updated = true;
                    } else if ( array_key_exists( "{$slug}:{$this->_type}", $option ) ) { /* admin_notices */
                        unset( $option[ "{$slug}:{$this->_type}" ] );
                        $updated = true;
                    } else if ( isset( $product->id ) && array_key_exists( $product->id, $option ) ) { /* all_licenses, add-ons, and id_slug_type_path_map */
                        $is_inactive_by_id   = ! isset( $active_products_by_id_map[ $product->id ] );
                        $is_inactive_by_slug = (
                            'id_slug_type_path_map' === $option_name &&
                            (
                                ! isset( $option[ $product->id ]['slug'] ) ||
                                $slug === $option[ $product->id ]['slug']
                            )
                        );

                        if ( $is_inactive_by_id || $is_inactive_by_slug ) {
                            unset( $option[ $product->id ] );
                            $updated = true;
                        }
                    } else if ( /* file_slug_map */
                        isset( $product->file ) &&
                        array_key_exists( $product->file, $option ) &&
                        $slug === $option[ $product->file ]
                    ) {
                        unset( $option[ $product->file ] );
                        $updated = true;
                    }

                    if ( $updated ) {
                        $this->_accounts->set_option( $option_name, $option );

                        $options[ $option_name ] = $option;

                        $has_updated_option = true;
                    }
                }

                // Clear the product's data from the primary storage.
                if ( isset( $this->_storage_data[ $slug ] ) ) {
                    unset( $this->_storage_data[ $slug ] );
                    $has_updated_option = true;
                }

                // Clear from GC timestamp.
                // @todo - This perhaps needs a separate garbage collector for all expired products. But the chance of left-over is very slim.
                if ( isset( $this->_gc_timestamp[ $slug ] ) ) {
                    unset( $this->_gc_timestamp[ $slug ] );
                    $has_updated_option = true;
                }
            }

            $this->_accounts->set_option( 'gc_timestamp', $this->_gc_timestamp );
            $this->_accounts->set_option( $this->_type . '_data', $this->_storage_data );

            return $has_updated_option;
        }

        private function get_all_option_names() {
            return array_merge(
                array(
                    'admin_notices',
                    'updates',
                    'all_licenses',
                    'addons',
                    'id_slug_type_path_map',
                    'file_slug_map',
                ),
                $this->_options_names
            );
        }

        private function get_products() {
            $products = $this->_accounts->get_option( $this->_plural_type, array() );

            // Fill any missing product found in the primary storage.
            // @todo - This wouldn't be needed if we use dedicated GC design for every options. The options themselves would provide such information.
            foreach( $this->_storage_data as $slug => $product_data ) {
                if ( ! isset( $products[ $slug ] ) ) {
                    $products[ $slug ] = (object) $product_data;
                }

                // This is needed to handle a scenario in which there are duplicate sets of data for the same product, but one of them needs to be removed.
                $products[ $slug ] = clone $products[ $slug ];

                // The reason for having the line above. This also handles a scenario in which the slug is either empty or not empty but incorrect.
                $products[ $slug ]->slug = $slug;
            }

            $this->update_gc_timestamp( $products );

            return $products;
        }

        private function get_filtered_products() {
            $products_to_clean         = array();
            $active_products_by_id_map = array();

            $products = $this->get_products();

            foreach ( $products as $slug => $product_data ) {
                if ( ! is_object( $product_data ) ) {
                    continue;
                }

                if ( $this->is_product_active( $slug ) ) {
                    $active_products_by_id_map[ $product_data->id ] = true;
                    continue;
                }

                $is_addon = ( ! empty( $product_data->parent_plugin_id ) );

                if ( ! $is_addon ) {
                    $products_to_clean[] = $product_data;
                } else {
                    /**
                     * If add-on, add to the beginning of the array so that add-ons are removed before their parent. This is to prevent an unexpected issue when an add-on exists but its parent was already removed.
                     */
                    array_unshift( $products_to_clean, $product_data );
                }
            }

            return array(
                'products_to_clean'         => $products_to_clean,
                'active_products_by_id_map' => $active_products_by_id_map,
            );
        }

        /**
         * @param string $slug
         *
         * @return bool
         */
        private function is_product_active( $slug ) {
            $instances = Freemius::_get_all_instances();

            foreach ( $instances as $instance ) {
                if ( $instance->get_slug() === $slug ) {
                    return true;
                }
            }

            $expiration_time = fs_get_optional_constant( 'WP_FS__GARBAGE_COLLECTOR_EXPIRATION_TIME_SECS', ( WP_FS__TIME_WEEK_IN_SEC * 4 ) );

            if ( $this->get_last_load_timestamp( $slug ) > ( time() - $expiration_time ) ) {
                // Last activation was within the last 4 weeks.
                return true;
            }

            return false;
        }

        private function load_options() {
            $options      = array();
            $option_names = $this->get_all_option_names();

            foreach ( $option_names as $option_name ) {
                $options[ $option_name ] = $this->_accounts->get_option( $option_name, array() );
            }

            return $options;
        }

        /**
         * Updates the garbage collector timestamp, only if it was not already set by the product's primary storage.
         *
         * @param array $products
         *
         * @return void
         */
        private function update_gc_timestamp( $products ) {
            foreach ($products as $slug => $product_data) {
                if ( ! is_object( $product_data ) && ! is_array( $product_data ) ) {
                    continue;
                }


                // If the product is active, we don't need to update the gc_timestamp.
                if ( isset( $this->_storage_data[ $slug ]['last_load_timestamp'] ) ) {
                    continue;
                }

                // First try to check if the product is present in the primary storage. If so update that.
                if ( isset( $this->_storage_data[ $slug ] ) ) {
                    $this->_storage_data[ $slug ]['last_load_timestamp'] = time();
                } else if ( ! isset( $this->_gc_timestamp[ $slug ] ) ) {
                    // If not, fallback to the gc_timestamp, but we don't want to update it more than once.
                    $this->_gc_timestamp[ $slug ] = time();
                }
            }
        }

        private function get_last_load_timestamp( $slug ) {
            if ( isset( $this->_storage_data[ $slug ]['last_load_timestamp'] ) ) {
                return $this->_storage_data[ $slug ]['last_load_timestamp'];
            }

            return isset( $this->_gc_timestamp[ $slug ] ) ?
                $this->_gc_timestamp[ $slug ] :
                // This should never happen, but if it does, let's assume the product is not expired.
                time();
        }
    }

    class FS_User_Garbage_Collector implements FS_I_Garbage_Collector {
        private $_accounts;

        private $_types;

        function __construct( FS_Options $_accounts, array $types ) {
            $this->_accounts = $_accounts;
            $this->_types    = $types;
        }

        function clean() {
            $users = Freemius::get_all_users();

            $user_has_install_map = $this->get_user_has_install_map();

            if ( count( $users ) === count( $user_has_install_map ) ) {
                return false;
            }

            $products_user_id_license_ids_map = $this->_accounts->get_option( 'user_id_license_ids_map', array() );

            $has_updated_option = false;

            foreach ( $users as $user_id => $user ) {
                if ( ! isset( $user_has_install_map[ $user_id ] ) ) {
                    unset( $users[ $user_id ] );

                    foreach( $products_user_id_license_ids_map as $product_id => $user_id_license_ids_map ) {
                        unset( $user_id_license_ids_map[ $user_id ] );

                        if ( empty( $user_id_license_ids_map ) ) {
                            unset( $products_user_id_license_ids_map[ $product_id ] );
                        } else {
                            $products_user_id_license_ids_map[ $product_id ] = $user_id_license_ids_map;
                        }
                    }

                    $this->_accounts->set_option( 'users', $users );
                    $this->_accounts->set_option( 'user_id_license_ids_map', $products_user_id_license_ids_map );

                    $has_updated_option = true;
                }
            }

            return $has_updated_option;
        }

        private function get_user_has_install_map() {
            $user_has_install_map = array();

            foreach ( $this->_types as $product_type ) {
                $option_name = ( WP_FS__MODULE_TYPE_PLUGIN !== $product_type ) ?
                    "{$product_type}_sites" :
                    'sites';

                $installs = $this->_accounts->get_option( $option_name, array() );

                foreach ( $installs as $install ) {
                    $user_has_install_map[ $install->user_id ] = true;
                }
            }

            return $user_has_install_map;
        }
    }

    // Main entry-level class.
    class FS_Garbage_Collector implements FS_I_Garbage_Collector {
        /**
         * @var FS_Garbage_Collector
         * @since 2.6.0
         */
        private static $_instance;

        /**
         * @return FS_Garbage_Collector
         */
        static function instance() {
            if ( ! isset( self::$_instance ) ) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }

        #endregion

        private function __construct() {
        }

        function clean() {
            $_accounts = FS_Options::instance( WP_FS__ACCOUNTS_OPTION_NAME, true );

            $products_cleaners = $this->get_product_cleaners( $_accounts );

            $has_cleaned = false;

            foreach ( $products_cleaners as $products_cleaner ) {
                if ( $products_cleaner->clean() ) {
                    $has_cleaned = true;
                }
            }

            if ( $has_cleaned ) {
                $user_cleaner = new FS_User_Garbage_Collector(
                    $_accounts,
                    array_keys( $products_cleaners )
                );

                $user_cleaner->clean();
            }

            // @todo - We need a garbage collector for `all_plugins` and `active_plugins` (and variants of themes).

            // Always store regardless of whether there were cleaned products or not since during the process, the logic may set the last load timestamp of some products.
            $_accounts->store();
        }

        /**
         * @param FS_Options $_accounts
         *
         * @return FS_I_Garbage_Collector[]
         */
        private function get_product_cleaners( FS_Options $_accounts ) {
            /**
             * @var FS_I_Garbage_Collector[] $products_cleaners
             */
            $products_cleaners = array();

            $products_cleaners[ WP_FS__MODULE_TYPE_PLUGIN ] = new FS_Product_Garbage_Collector(
                $_accounts,
                array(
                    'sites',
                    'plans',
                    'plugins',
                ),
                WP_FS__MODULE_TYPE_PLUGIN
            );

            $products_cleaners[ WP_FS__MODULE_TYPE_THEME ] = new FS_Product_Garbage_Collector(
                $_accounts,
                array(
                    'theme_sites',
                    'theme_plans',
                    'themes',
                ),
                WP_FS__MODULE_TYPE_THEME
            );

            return $products_cleaners;
        }
    }