<?php
    /**
     * @package     Freemius
     * @copyright   Copyright (c) 2015, Freemius, Inc.
     * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since       1.0.3
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    class FS_Site extends FS_Scope_Entity {
        /**
         * @var number
         */
        public $site_id;
        /**
         * @var int
         */
        public $blog_id;
        /**
         * @var number
         */
        public $plugin_id;
        /**
         * @var number
         */
        public $user_id;
        /**
         * @var string
         */
        public $title;
        /**
         * @var string
         */
        public $url;
        /**
         * @var string
         */
        public $version;
        /**
         * @var string E.g. en-GB
         */
        public $language;
        /**
         * @var string Platform version (e.g WordPress version).
         */
        public $platform_version;
        /**
         * Freemius SDK version
         *
         * @author Leo Fajardo (@leorw)
         * @since  1.2.2
         *
         * @var string SDK version (e.g.: 1.2.2)
         */
        public $sdk_version;
        /**
         * @var string Programming language version (e.g PHP version).
         */
        public $programming_language_version;
        /**
         * @var number|null
         */
        public $plan_id;
        /**
         * @var number|null
         */
        public $license_id;
        /**
         * @var number|null
         */
        public $trial_plan_id;
        /**
         * @var string|null
         */
        public $trial_ends;
        /**
         * @since 1.0.9
         *
         * @var bool
         */
        public $is_premium = false;
        /**
         * @author Leo Fajardo (@leorw)
         *
         * @since  1.2.1.5
         * @deprecated Since 2.5.1
         * @todo Remove after a few releases.
         *
         * @var bool
         */
        public $is_disconnected = false;
        /**
         * @since  2.0.0
         *
         * @var bool
         */
        public $is_active = true;
        /**
         * @since  2.0.0
         *
         * @var bool
         */
        public $is_uninstalled = false;
        /**
         * @author Edgar Melkonyan
         *
         * @since 2.4.2
         *
         * @var bool
         */
        public $is_beta;

        /**
         * @param stdClass|bool $site
         */
        function __construct( $site = false ) {
            parent::__construct( $site );

            if ( is_object( $site ) ) {
                $this->plan_id = $site->plan_id;
            }

            if ( ! is_bool( $this->is_disconnected ) ) {
                $this->is_disconnected = false;
            }
        }

        static function get_type() {
            return 'install';
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param string $url
         *
         * @return bool
         */
        static function is_localhost_by_address( $url ) {
            if ( false !== strpos( $url, '127.0.0.1' ) ||
                 false !== strpos( $url, 'localhost' )
            ) {
                return true;
            }

            if ( ! fs_starts_with( $url, 'http' ) ) {
                $url = 'http://' . $url;
            }

            $url_parts = parse_url( $url );

            $subdomain = $url_parts['host'];

            return (
                // Starts with.
                fs_starts_with( $subdomain, 'local.' ) ||
                fs_starts_with( $subdomain, 'dev.' ) ||
                fs_starts_with( $subdomain, 'test.' ) ||
                fs_starts_with( $subdomain, 'stage.' ) ||
                fs_starts_with( $subdomain, 'staging.' ) ||

                // Ends with.
                fs_ends_with( $subdomain, '.dev' ) ||
                fs_ends_with( $subdomain, '.test' ) ||
                fs_ends_with( $subdomain, '.staging' ) ||
                fs_ends_with( $subdomain, '.local' ) ||
                fs_ends_with( $subdomain, '.example' ) ||
                fs_ends_with( $subdomain, '.invalid' ) ||
                // GoDaddy test/dev.
                fs_ends_with( $subdomain, '.myftpupload.com' ) ||
                // ngrok tunneling.
                fs_ends_with( $subdomain, '.ngrok.io' ) ||
                // wpsandbox.
                fs_ends_with( $subdomain, '.wpsandbox.pro' ) ||
                // SiteGround staging.
                fs_starts_with( $subdomain, 'staging' ) ||
                // WPEngine staging.
                fs_ends_with( $subdomain, '.staging.wpengine.com' ) ||
                fs_ends_with( $subdomain, '.dev.wpengine.com' ) ||
                fs_ends_with( $subdomain, '.wpengine.com' ) ||
                fs_ends_with( $subdomain, '.wpenginepowered.com' ) ||
                // Pantheon
                ( fs_ends_with( $subdomain, 'pantheonsite.io' ) &&
                  ( fs_starts_with( $subdomain, 'test-' ) || fs_starts_with( $subdomain, 'dev-' ) ) ) ||
                // Cloudways
                fs_ends_with( $subdomain, '.cloudwaysapps.com' ) ||
                // Kinsta
                (
                    ( fs_starts_with( $subdomain, 'stg-' ) ||  fs_starts_with( $subdomain, 'staging-' ) || fs_starts_with( $subdomain, 'env-' ) ) &&
                    ( fs_ends_with( $subdomain, '.kinsta.com' ) || fs_ends_with( $subdomain, '.kinsta.cloud' ) )
                ) ||
                // DesktopServer
                fs_ends_with( $subdomain, '.dev.cc' ) ||
                // Pressable
                fs_ends_with( $subdomain, '.mystagingwebsite.com' ) ||
                // WPMU DEV
                ( fs_ends_with( $subdomain, '.tempurl.host' ) || fs_ends_with( $subdomain, '.wpmudev.host' ) ) ||
                // Vendasta
                ( fs_ends_with( $subdomain, '.websitepro-staging.com' ) || fs_ends_with( $subdomain, '.websitepro.hosting' ) ) ||
                // InstaWP
                ( fs_ends_with( $subdomain, '.instawp.co' ) || fs_ends_with( $subdomain, '.instawp.link' ) || fs_ends_with( $subdomain, '.instawp.xyz' ) ) ||
                // 10Web Hosting
                ( fs_ends_with( $subdomain, '-dev.10web.site' ) || fs_ends_with( $subdomain, '-dev.10web.cloud' ) )
            );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.9.1
         *
         * @param string $host
         *
         * @return bool
         */
        static function is_playground_wp_environment_by_host( $host ) {
            // Services aimed at providing a WordPress sandbox environment.
            $sandbox_wp_environment_domains = array(
                // InstaWP
                'instawp.co',
                'instawp.link',
                'instawp.xyz',

                // TasteWP
                'tastewp.com',

                // WordPress Playground
                'playground.wordpress.net',
            );

            foreach ( $sandbox_wp_environment_domains as $domain) {
                if (
                    ( $host === $domain ) ||
                    fs_ends_with( $host, '.' . $domain ) ||
                    fs_ends_with( $host, '-' . $domain )
                ) {
                    return true;
                }
            }

            return false;
        }

        function is_localhost() {
            return ( WP_FS__IS_LOCALHOST_FOR_SERVER || self::is_localhost_by_address( $this->url ) );
        }

        /**
         * Check if site in trial.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @return bool
         */
        function is_trial() {
            return is_numeric( $this->trial_plan_id ) && ( strtotime( $this->trial_ends ) > WP_FS__SCRIPT_START_TIME );
        }

        /**
         * Check if user already utilized the trial with the current install.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @return bool
         */
        function is_trial_utilized() {
            return is_numeric( $this->trial_plan_id );
        }

        /**
         * @author Edgar Melkonyan
         *
         * @return bool
         */
        function is_beta() {
            return ( isset( $this->is_beta ) && true === $this->is_beta );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.1
         *
         * @param string $site_url
         *
         * @return bool
         */
        function is_clone( $site_url ) {
            $clone_install_url = trailingslashit( fs_strip_url_protocol( $this->url ) );

            return ( $clone_install_url !== $site_url );
        }
    }