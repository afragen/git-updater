<?php
    /**
     * @package     Freemius
     * @copyright   Copyright (c) 2015, Freemius, Inc.
     * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since       1.0.4
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    if ( ! defined( 'WP_FS__SLUG' ) ) {
        define( 'WP_FS__SLUG', 'freemius' );
    }
    if ( ! defined( 'WP_FS__DEV_MODE' ) ) {
        define( 'WP_FS__DEV_MODE', false );
    }

    #--------------------------------------------------------------------------------
    #region API Connectivity Issues Simulation
    #--------------------------------------------------------------------------------

    if ( ! defined( 'WP_FS__SIMULATE_NO_API_CONNECTIVITY' ) ) {
        define( 'WP_FS__SIMULATE_NO_API_CONNECTIVITY', false );
    }
    if ( ! defined( 'WP_FS__SIMULATE_NO_CURL' ) ) {
        define( 'WP_FS__SIMULATE_NO_CURL', false );
    }
    if ( ! defined( 'WP_FS__SIMULATE_NO_API_CONNECTIVITY_CLOUDFLARE' ) ) {
        define( 'WP_FS__SIMULATE_NO_API_CONNECTIVITY_CLOUDFLARE', false );
    }
    if ( ! defined( 'WP_FS__SIMULATE_NO_API_CONNECTIVITY_SQUID_ACL' ) ) {
        define( 'WP_FS__SIMULATE_NO_API_CONNECTIVITY_SQUID_ACL', false );
    }
    if ( WP_FS__SIMULATE_NO_CURL ) {
        define( 'FS_SDK__SIMULATE_NO_CURL', true );
    }
    if ( WP_FS__SIMULATE_NO_API_CONNECTIVITY_CLOUDFLARE ) {
        define( 'FS_SDK__SIMULATE_NO_API_CONNECTIVITY_CLOUDFLARE', true );
    }
    if ( WP_FS__SIMULATE_NO_API_CONNECTIVITY_SQUID_ACL ) {
        define( 'FS_SDK__SIMULATE_NO_API_CONNECTIVITY_SQUID_ACL', true );
    }

    #endregion

    if ( ! defined( 'WP_FS__SIMULATE_FREEMIUS_OFF' ) ) {
        define( 'WP_FS__SIMULATE_FREEMIUS_OFF', false );
    }

    if ( ! defined( 'WP_FS__PING_API_ON_IP_OR_HOST_CHANGES' ) ) {
        /**
         * @since  1.1.7.3
         * @author Vova Feldman (@svovaf)
         *
         * I'm not sure if shared servers periodically change IP, or the subdomain of the
         * admin dashboard. Also, I've seen sites that have strange loop of switching
         * between domains on a daily basis. Therefore, to eliminate the risk of
         * multiple unwanted connectivity test pings, temporary ignore domain or
         * server IP changes.
         */
        define( 'WP_FS__PING_API_ON_IP_OR_HOST_CHANGES', false );
    }

    /**
     * If your dev environment supports custom public network IP setup
     * like VVV, please update WP_FS__LOCALHOST_IP with your public IP
     * and uncomment it during dev.
     */
    if ( ! defined( 'WP_FS__LOCALHOST_IP' ) ) {
        // VVV default public network IP.
        define( 'WP_FS__VVV_DEFAULT_PUBLIC_IP', '192.168.50.4' );

//		define( 'WP_FS__LOCALHOST_IP', WP_FS__VVV_DEFAULT_PUBLIC_IP );
    }

    /**
     * If true and running with secret key, the opt-in process
     * will skip the email activation process which is invoked
     * when the email of the context user already exist in Freemius
     * database (as a security precaution, to prevent sharing user
     * secret with unauthorized entity).
     *
     * IMPORTANT:
     *      AS A SECURITY PRECAUTION, WE VALIDATE THE TIMESTAMP OF THE OPT-IN REQUEST.
     *      THEREFORE, MAKE SURE THAT WHEN USING THIS PARAMETER,YOUR TESTING ENVIRONMENT'S
     *      CLOCK IS SYNCED.
     */
    if ( ! defined( 'WP_FS__SKIP_EMAIL_ACTIVATION' ) ) {
        define( 'WP_FS__SKIP_EMAIL_ACTIVATION', false );
    }


    #--------------------------------------------------------------------------------
    #region Directories
    #--------------------------------------------------------------------------------

    if ( ! defined( 'WP_FS__DIR' ) ) {
        define( 'WP_FS__DIR', dirname( __FILE__ ) );
    }
    if ( ! defined( 'WP_FS__DIR_INCLUDES' ) ) {
        define( 'WP_FS__DIR_INCLUDES', WP_FS__DIR . '/includes' );
    }
    if ( ! defined( 'WP_FS__DIR_TEMPLATES' ) ) {
        define( 'WP_FS__DIR_TEMPLATES', WP_FS__DIR . '/templates' );
    }
    if ( ! defined( 'WP_FS__DIR_ASSETS' ) ) {
        define( 'WP_FS__DIR_ASSETS', WP_FS__DIR . '/assets' );
    }
    if ( ! defined( 'WP_FS__DIR_CSS' ) ) {
        define( 'WP_FS__DIR_CSS', WP_FS__DIR_ASSETS . '/css' );
    }
    if ( ! defined( 'WP_FS__DIR_JS' ) ) {
        define( 'WP_FS__DIR_JS', WP_FS__DIR_ASSETS . '/js' );
    }
    if ( ! defined( 'WP_FS__DIR_IMG' ) ) {
        define( 'WP_FS__DIR_IMG', WP_FS__DIR_ASSETS . '/img' );
    }
    if ( ! defined( 'WP_FS__DIR_SDK' ) ) {
        define( 'WP_FS__DIR_SDK', WP_FS__DIR_INCLUDES . '/sdk' );
    }

    #endregion

    /**
     * Domain / URL / Address
     */
    define( 'WP_FS__ROOT_DOMAIN_PRODUCTION', 'freemius.com' );
    define( 'WP_FS__DOMAIN_PRODUCTION', 'wp.freemius.com' );
    define( 'WP_FS__ADDRESS_PRODUCTION', 'https://' . WP_FS__DOMAIN_PRODUCTION );

    if ( ! defined( 'WP_FS__DOMAIN_LOCALHOST' ) ) {
        define( 'WP_FS__DOMAIN_LOCALHOST', 'wp.freemius' );
    }
    if ( ! defined( 'WP_FS__ADDRESS_LOCALHOST' ) ) {
        define( 'WP_FS__ADDRESS_LOCALHOST', 'http://' . WP_FS__DOMAIN_LOCALHOST . ':8080' );
    }

    if ( ! defined( 'WP_FS__TESTING_DOMAIN' ) ) {
        define( 'WP_FS__TESTING_DOMAIN', 'fswp' );
    }

    #--------------------------------------------------------------------------------
    #region HTTP
    #--------------------------------------------------------------------------------

    if ( ! defined( 'WP_FS__IS_HTTP_REQUEST' ) ) {
        define( 'WP_FS__IS_HTTP_REQUEST', isset( $_SERVER['HTTP_HOST'] ) );
    }

    if ( ! defined( 'WP_FS__IS_HTTPS' ) ) {
        define( 'WP_FS__IS_HTTPS', ( WP_FS__IS_HTTP_REQUEST &&
                                     // Checks if CloudFlare's HTTPS (Flexible SSL support).
                                     isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) &&
                                     'https' === strtolower( $_SERVER['HTTP_X_FORWARDED_PROTO'] )
                                   ) ||
                                   // Check if HTTPS request.
                                   ( isset( $_SERVER['HTTPS'] ) && 'on' == $_SERVER['HTTPS'] ) ||
                                   ( isset( $_SERVER['SERVER_PORT'] ) && 443 == $_SERVER['SERVER_PORT'] )
        );
    }

    if ( ! defined( 'WP_FS__IS_POST_REQUEST' ) ) {
        define( 'WP_FS__IS_POST_REQUEST', ( WP_FS__IS_HTTP_REQUEST &&
                                            strtoupper( $_SERVER['REQUEST_METHOD'] ) == 'POST' ) );
    }

    if ( ! defined( 'WP_FS__REMOTE_ADDR' ) ) {
        define( 'WP_FS__REMOTE_ADDR', fs_get_ip() );
    }

    if ( ! defined( 'WP_FS__IS_LOCALHOST' ) ) {
        if ( defined( 'WP_FS__LOCALHOST_IP' ) ) {
            define( 'WP_FS__IS_LOCALHOST', ( WP_FS__LOCALHOST_IP === WP_FS__REMOTE_ADDR ) );
        } else {
            define( 'WP_FS__IS_LOCALHOST', WP_FS__IS_HTTP_REQUEST &&
                                           is_string( WP_FS__REMOTE_ADDR ) &&
                                           ( substr( WP_FS__REMOTE_ADDR, 0, 4 ) === '127.' ||
                                             WP_FS__REMOTE_ADDR === '::1' )
            );
        }
    }

    if ( ! defined( 'WP_FS__IS_LOCALHOST_FOR_SERVER' ) ) {
        define( 'WP_FS__IS_LOCALHOST_FOR_SERVER', ( ! WP_FS__IS_HTTP_REQUEST ||
                                                    false !== strpos( $_SERVER['HTTP_HOST'], 'localhost' ) ) );
    }

    #endregion

    if ( ! defined( 'WP_FS__IS_PRODUCTION_MODE' ) ) {
        // By default, run with Freemius production servers.
        define( 'WP_FS__IS_PRODUCTION_MODE', true );
    }

    if ( ! defined( 'WP_FS__ADDRESS' ) ) {
        define( 'WP_FS__ADDRESS', ( WP_FS__IS_PRODUCTION_MODE ? WP_FS__ADDRESS_PRODUCTION : WP_FS__ADDRESS_LOCALHOST ) );
    }


    #--------------------------------------------------------------------------------
    #region API
    #--------------------------------------------------------------------------------

    if ( ! defined( 'WP_FS__API_ADDRESS_LOCALHOST' ) ) {
        define( 'WP_FS__API_ADDRESS_LOCALHOST', 'http://api.freemius-local.com:8080' );
    }
    if ( ! defined( 'WP_FS__API_SANDBOX_ADDRESS_LOCALHOST' ) ) {
        define( 'WP_FS__API_SANDBOX_ADDRESS_LOCALHOST', 'http://sandbox-api.freemius:8080' );
    }

    // Set API address for local testing.
    if ( ! WP_FS__IS_PRODUCTION_MODE ) {
        if ( ! defined( 'FS_API__ADDRESS' ) ) {
            define( 'FS_API__ADDRESS', WP_FS__API_ADDRESS_LOCALHOST );
        }
        if ( ! defined( 'FS_API__SANDBOX_ADDRESS' ) ) {
            define( 'FS_API__SANDBOX_ADDRESS', WP_FS__API_SANDBOX_ADDRESS_LOCALHOST );
        }
    }

    #endregion

    #--------------------------------------------------------------------------------
    #region Checkout
    #--------------------------------------------------------------------------------

    if ( ! defined( 'FS_CHECKOUT__ADDRESS_PRODUCTION' ) ) {
        define( 'FS_CHECKOUT__ADDRESS_PRODUCTION', 'https://checkout.freemius.com' );
    }

    if ( ! defined( 'FS_CHECKOUT__ADDRESS_LOCALHOST' ) ) {
        define( 'FS_CHECKOUT__ADDRESS_LOCALHOST', 'http://checkout.freemius-local.com:8080' );
    }

    if ( ! defined( 'FS_CHECKOUT__ADDRESS' ) ) {
        define( 'FS_CHECKOUT__ADDRESS', ( WP_FS__IS_PRODUCTION_MODE ? FS_CHECKOUT__ADDRESS_PRODUCTION : FS_CHECKOUT__ADDRESS_LOCALHOST ) );
    }

    #endregion

    define( 'WP_FS___OPTION_PREFIX', 'fs' . ( WP_FS__IS_PRODUCTION_MODE ? '' : '_dbg' ) . '_' );

    if ( ! defined( 'WP_FS__ACCOUNTS_OPTION_NAME' ) ) {
        define( 'WP_FS__ACCOUNTS_OPTION_NAME', WP_FS___OPTION_PREFIX . 'accounts' );
    }
    if ( ! defined( 'WP_FS__API_CACHE_OPTION_NAME' ) ) {
        define( 'WP_FS__API_CACHE_OPTION_NAME', WP_FS___OPTION_PREFIX . 'api_cache' );
    }
    if ( ! defined( 'WP_FS__GDPR_OPTION_NAME' ) ) {
        define( 'WP_FS__GDPR_OPTION_NAME', WP_FS___OPTION_PREFIX . 'gdpr' );
    }
    define( 'WP_FS__OPTIONS_OPTION_NAME', WP_FS___OPTION_PREFIX . 'options' );

    /**
     * Module types
     *
     * @since 1.2.2
     */
    define( 'WP_FS__MODULE_TYPE_PLUGIN', 'plugin' );
    define( 'WP_FS__MODULE_TYPE_THEME', 'theme' );

    /**
     * Billing Frequencies
     */
    define( 'WP_FS__PERIOD_ANNUALLY', 'annual' );
    define( 'WP_FS__PERIOD_MONTHLY', 'monthly' );
    define( 'WP_FS__PERIOD_LIFETIME', 'lifetime' );

    /**
     * Plans
     */
    define( 'WP_FS__PLAN_DEFAULT_PAID', false );
    define( 'WP_FS__PLAN_FREE', 'free' );
    define( 'WP_FS__PLAN_TRIAL', 'trial' );

    /**
     * Times in seconds
     */
    if ( ! defined( 'WP_FS__TIME_5_MIN_IN_SEC' ) ) {
        define( 'WP_FS__TIME_5_MIN_IN_SEC', 300 );
    }
    if ( ! defined( 'WP_FS__TIME_10_MIN_IN_SEC' ) ) {
        define( 'WP_FS__TIME_10_MIN_IN_SEC', 600 );
    }
//	define( 'WP_FS__TIME_15_MIN_IN_SEC', 900 );
    if ( ! defined( 'WP_FS__TIME_12_HOURS_IN_SEC' ) ) {
        define( 'WP_FS__TIME_12_HOURS_IN_SEC', 43200 );
    }
    if ( ! defined( 'WP_FS__TIME_24_HOURS_IN_SEC' ) ) {
        define( 'WP_FS__TIME_24_HOURS_IN_SEC', WP_FS__TIME_12_HOURS_IN_SEC * 2 );
    }
    if ( ! defined( 'WP_FS__TIME_WEEK_IN_SEC' ) ) {
        define( 'WP_FS__TIME_WEEK_IN_SEC', 7 * WP_FS__TIME_24_HOURS_IN_SEC );
    }

    #--------------------------------------------------------------------------------
    #region Debugging
    #--------------------------------------------------------------------------------

    if ( ! defined( 'WP_FS__DEBUG_SDK' ) ) {
        $debug_mode = get_option( 'fs_debug_mode', null );

        if ( $debug_mode === null ) {
            $debug_mode = false;
            add_option( 'fs_debug_mode', $debug_mode );
        }

        define( 'WP_FS__DEBUG_SDK', is_numeric( $debug_mode ) ? ( 0 < $debug_mode ) : WP_FS__DEV_MODE );
    }

    if ( ! defined( 'WP_FS__ECHO_DEBUG_SDK' ) ) {
        define( 'WP_FS__ECHO_DEBUG_SDK', WP_FS__DEV_MODE && ! empty( $_GET['fs_dbg_echo'] ) );
    }
    if ( ! defined( 'WP_FS__LOG_DATETIME_FORMAT' ) ) {
        define( 'WP_FS__LOG_DATETIME_FORMAT', 'Y-m-d H:i:s' );
    }
    if ( ! defined( 'FS_API__LOGGER_ON' ) ) {
        define( 'FS_API__LOGGER_ON', WP_FS__DEBUG_SDK );
    }

    if ( WP_FS__ECHO_DEBUG_SDK ) {
        error_reporting( E_ALL );
    }

    #endregion

    if ( ! defined( 'WP_FS__SCRIPT_START_TIME' ) ) {
        define( 'WP_FS__SCRIPT_START_TIME', time() );
    }
    if ( ! defined( 'WP_FS__DEFAULT_PRIORITY' ) ) {
        define( 'WP_FS__DEFAULT_PRIORITY', 10 );
    }
    if ( ! defined( 'WP_FS__LOWEST_PRIORITY' ) ) {
        define( 'WP_FS__LOWEST_PRIORITY', 999999999 );
    }

    #--------------------------------------------------------------------------------
    #region Multisite Network
    #--------------------------------------------------------------------------------

    /**
     * Do not use this define directly, it will have the wrong value
     * during plugin uninstall/deletion when the inclusion of the plugin
     * is triggered due to registration with register_uninstall_hook().
     *
     * Instead, use fs_is_network_admin().
     *
     * @author Vova Feldman (@svovaf)
     */
    if ( ! defined( 'WP_FS__IS_NETWORK_ADMIN' ) ) {
        define( 'WP_FS__IS_NETWORK_ADMIN',
            is_multisite() &&
            ( is_network_admin() ||
              ( ( defined( 'DOING_AJAX' ) && DOING_AJAX &&
                  ( isset( $_REQUEST['_fs_network_admin'] ) /*||
                    ( ! empty( $_REQUEST['action'] ) && 'delete-plugin' === $_REQUEST['action'] )*/ )
                ) ||
                // Plugin uninstall.
                defined( 'WP_UNINSTALL_PLUGIN' ) )
            )
        );
    }

    /**
     * Do not use this define directly, it will have the wrong value
     * during plugin uninstall/deletion when the inclusion of the plugin
     * is triggered due to registration with register_uninstall_hook().
     *
     * Instead, use fs_is_blog_admin().
     *
     * @author Vova Feldman (@svovaf)
     */
    if ( ! defined( 'WP_FS__IS_BLOG_ADMIN' ) ) {
        define( 'WP_FS__IS_BLOG_ADMIN', is_blog_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_REQUEST['_fs_blog_admin'] ) ) );
    }

    if ( ! defined( 'WP_FS__SHOW_NETWORK_EVEN_WHEN_DELEGATED' ) ) {
        // Set to true to show network level settings even if delegated to site admins.
        define( 'WP_FS__SHOW_NETWORK_EVEN_WHEN_DELEGATED', false );
    }

    #endregion

    if ( ! defined( 'WP_FS__DEMO_MODE' ) ) {
        define( 'WP_FS__DEMO_MODE', false );
    }
    if ( ! defined( 'FS_SDK__SSLVERIFY' ) ) {
        define( 'FS_SDK__SSLVERIFY', false );
    }