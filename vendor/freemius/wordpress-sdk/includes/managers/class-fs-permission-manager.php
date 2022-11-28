<?php
    /**
     * @package     Freemius
     * @copyright   Copyright (c) 2022, Freemius, Inc.
     * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since       2.5.1
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * This class is responsible for managing the user permissions.
     *
     * @author Vova Feldman (@svovaf)
     * @since 2.5.1
     */
    class FS_Permission_Manager {
        /**
         * @var Freemius
         */
        private $_fs;
        /**
         * @var FS_Storage
         */
        private $_storage;

        /**
         * @var array<number,self>
         */
        private static $_instances = array();

        const PERMISSION_USER       = 'user';
        const PERMISSION_SITE       = 'site';
        const PERMISSION_EVENTS     = 'events';
        const PERMISSION_ESSENTIALS = 'essentials';
        const PERMISSION_DIAGNOSTIC = 'diagnostic';
        const PERMISSION_EXTENSIONS = 'extensions';
        const PERMISSION_NEWSLETTER = 'newsletter';

        /**
         * @param Freemius $fs
         *
         * @return self
         */
        static function instance( Freemius $fs ) {
            $id = $fs->get_id();

            if ( ! isset( self::$_instances[ $id ] ) ) {
                self::$_instances[ $id ] = new self( $fs );
            }

            return self::$_instances[ $id ];
        }

        /**
         * @param Freemius $fs
         */
        protected function __construct( Freemius $fs ) {
            $this->_fs      = $fs;
            $this->_storage = FS_Storage::instance( $fs->get_module_type(), $fs->get_slug() );
        }

        /**
         * @return string[]
         */
        static function get_all_permission_ids() {
            return array(
                self::PERMISSION_USER,
                self::PERMISSION_SITE,
                self::PERMISSION_EVENTS,
                self::PERMISSION_ESSENTIALS,
                self::PERMISSION_DIAGNOSTIC,
                self::PERMISSION_EXTENSIONS,
                self::PERMISSION_NEWSLETTER,
            );
        }

        /**
         * @return string[]
         */
        static function get_api_managed_permission_ids() {
            return array(
                self::PERMISSION_USER,
                self::PERMISSION_SITE,
                self::PERMISSION_EXTENSIONS,
            );
        }

        /**
         * @param string $permission
         *
         * @return bool
         */
        static function is_supported_permission( $permission ) {
            return in_array( $permission, self::get_all_permission_ids() );
        }

        /**
         * @param bool    $is_license_activation
         * @param array[] $extra_permissions
         *
         * @return array[]
         */
        function get_permissions( $is_license_activation, array $extra_permissions = array() ) {
            return $is_license_activation ?
                $this->get_license_activation_permissions( $extra_permissions ) :
                $this->get_opt_in_permissions( $extra_permissions );
        }

        #--------------------------------------------------------------------------------
        #region Opt-In Permissions
        #--------------------------------------------------------------------------------

        /**
         * @param array[] $extra_permissions
         *
         * @return array[]
         */
        function get_opt_in_permissions(
            array $extra_permissions = array(),
            $load_default_from_storage = false,
            $is_optional = false
        ) {
            $permissions = array_merge(
                $this->get_opt_in_required_permissions( $load_default_from_storage ),
                $this->get_opt_in_optional_permissions( $load_default_from_storage, $is_optional ),
                $extra_permissions
            );

            return $this->get_sorted_permissions_by_priority( $permissions );
        }

        /**
         * @param bool $load_default_from_storage
         *
         * @return array[]
         */
        function get_opt_in_required_permissions( $load_default_from_storage = false ) {
            return array( $this->get_user_permission( $load_default_from_storage ) );
        }

        /**
         * @param bool $load_default_from_storage
         * @param bool $is_optional
         *
         * @return array[]
         */
        function get_opt_in_optional_permissions(
            $load_default_from_storage = false,
            $is_optional = false
        ) {
            return array_merge(
                $this->get_opt_in_diagnostic_permissions( $load_default_from_storage, $is_optional ),
                array( $this->get_extensions_permission(
                    false,
                    false,
                    $load_default_from_storage
                ) )
            );
        }

        /**
         * @param bool $load_default_from_storage
         * @param bool $is_optional
         *
         * @return array[]
         */
        function get_opt_in_diagnostic_permissions(
            $load_default_from_storage = false,
            $is_optional = false
        ) {
            // Alias.
            $fs = $this->_fs;

            $permissions = array();

            $permissions[] = $this->get_permission(
                self::PERMISSION_SITE,
                'admin-links',
                $fs->get_text_inline( 'View Basic Website Info', 'permissions-site' ),
                $fs->get_text_inline( 'Homepage URL & title, WP & PHP versions, and site language', 'permissions-site_desc' ),
                sprintf(
                /* translators: %s: 'Plugin' or 'Theme' */
                    $fs->get_text_inline( 'To provide additional functionality that\'s relevant to your website, avoid WordPress or PHP version incompatibilities that can break your website, and recognize which languages & regions the %s should be translated and tailored to.', 'permissions-site_tooltip' ),
                    $fs->get_module_label( true )
                ),
                10,
                $is_optional,
                true,
                $load_default_from_storage
            );

            $permissions[] = $this->get_permission(
                self::PERMISSION_EVENTS,
                'admin-' . ( $fs->is_plugin() ? 'plugins' : 'appearance' ),
                sprintf( $fs->get_text_inline( 'View Basic %s Info', 'permissions-events' ), $fs->get_module_label() ),
                sprintf(
                /* translators: %s: 'Plugin' or 'Theme' */
                    $fs->get_text_inline( 'Current %s & SDK versions, and if active or uninstalled', 'permissions-events_desc' ),
                    $fs->get_module_label( true )
                ),
                '',
                20,
                $is_optional,
                true,
                $load_default_from_storage
            );

            return $permissions;
        }

        #endregion

        #--------------------------------------------------------------------------------
        #region License Activation Permissions
        #--------------------------------------------------------------------------------

        /**
         * @param array[] $extra_permissions
         *
         * @return array[]
         */
        function get_license_activation_permissions(
            array $extra_permissions = array(),
            $include_optional_label = true
        ) {
            $permissions = array_merge(
                $this->get_license_required_permissions(),
                $this->get_license_optional_permissions( $include_optional_label ),
                $extra_permissions
            );

            return $this->get_sorted_permissions_by_priority( $permissions );
        }

        /**
         * @param bool $load_default_from_storage
         *
         * @return array[]
         */
        function get_license_required_permissions( $load_default_from_storage = false ) {
            // Alias.
            $fs = $this->_fs;

            $permissions = array();

            $permissions[] = $this->get_permission(
                self::PERMISSION_ESSENTIALS,
                'admin-links',
                $fs->get_text_inline( 'View License Essentials', 'permissions-essentials' ),
                $fs->get_text_inline(
                    sprintf(
                    /* translators: %s: 'Plugin' or 'Theme' */
                        'Homepage URL, %s version, SDK version',
                        $fs->get_module_label()
                    ),
                    'permissions-essentials_desc'
                ),
                sprintf(
                /* translators: %s: 'Plugin' or 'Theme' */
                    $fs->get_text_inline( 'To let you manage & control where the license is activated and ensure %s security & feature updates are only delivered to websites you authorize.', 'permissions-essentials_tooltip' ),
                    $fs->get_module_label( true )
                ),
                10,
                false,
                true,
                $load_default_from_storage
            );

            $permissions[] = $this->get_permission(
                self::PERMISSION_EVENTS,
                'admin-' . ( $fs->is_plugin() ? 'plugins' : 'appearance' ),
                sprintf( $fs->get_text_inline( 'View %s State', 'permissions-events' ), $fs->get_module_label() ),
                sprintf(
                /* translators: %s: 'Plugin' or 'Theme' */
                    $fs->get_text_inline( 'Is active, deactivated, or uninstalled', 'permissions-events_desc-paid' ),
                    $fs->get_module_label( true )
                ),
                sprintf( $fs->get_text_inline( 'So you can reuse the license when the %s is no longer active.', 'permissions-events_tooltip' ), $fs->get_module_label( true ) ),
                20,
                false,
                true,
                $load_default_from_storage
            );

            return $permissions;
        }

        /**
         * @return array[]
         */
        function get_license_optional_permissions(
            $include_optional_label = false,
            $load_default_from_storage = false
        ) {
            return array(
                $this->get_diagnostic_permission( $include_optional_label, $load_default_from_storage ),
                $this->get_extensions_permission( true, $include_optional_label, $load_default_from_storage ),
            );
        }

        /**
         * @param bool $include_optional_label
         * @param bool $load_default_from_storage
         *
         * @return array
         */
        function get_diagnostic_permission(
            $include_optional_label = false,
            $load_default_from_storage = false
        ) {
            return $this->get_permission(
                self::PERMISSION_DIAGNOSTIC,
                'wordpress-alt',
                $this->_fs->get_text_inline( 'View Diagnostic Info', 'permissions-diagnostic' ) . ( $include_optional_label ? ' (' . $this->_fs->get_text_inline( 'optional' ) . ')' : '' ),
                $this->_fs->get_text_inline( 'WordPress & PHP versions, site language & title', 'permissions-diagnostic_desc' ),
                sprintf(
                /* translators: %s: 'Plugin' or 'Theme' */
                    $this->_fs->get_text_inline( 'To avoid breaking your website due to WordPress or PHP version incompatibilities, and recognize which languages & regions the %s should be translated and tailored to.', 'permissions-diagnostic_tooltip' ),
                    $this->_fs->get_module_label( true )
                ),
                25,
                true,
                true,
                $load_default_from_storage
            );
        }

        #endregion

        #--------------------------------------------------------------------------------
        #region Common Permissions
        #--------------------------------------------------------------------------------

        /**
         * @param bool $is_license_activation
         * @param bool $include_optional_label
         * @param bool $load_default_from_storage
         *
         * @return array
         */
        function get_extensions_permission(
            $is_license_activation,
            $include_optional_label = false,
            $load_default_from_storage = false
        ) {
            $is_on_by_default = ! $is_license_activation;

            return $this->get_permission(
                self::PERMISSION_EXTENSIONS,
                'block-default',
                $this->_fs->get_text_inline( 'View Plugins & Themes List', 'permissions-extensions' ) . ( $is_license_activation ? ( $include_optional_label ? ' (' . $this->_fs->get_text_inline( 'optional' ) . ')' : '' ) : '' ),
                $this->_fs->get_text_inline( 'Names, slugs, versions, and if active or not', 'permissions-extensions_desc' ),
                $this->_fs->get_text_inline( 'To ensure compatibility and avoid conflicts with your installed plugins and themes.', 'permissions-events_tooltip' ),
                25,
                true,
                $is_on_by_default,
                $load_default_from_storage
            );
        }

        /**
         * @param bool $load_default_from_storage
         *
         * @return array
         */
        function get_user_permission( $load_default_from_storage = false ) {
            return $this->get_permission(
                self::PERMISSION_USER,
                'admin-users',
                $this->_fs->get_text_inline( 'View Basic Profile Info', 'permissions-profile' ),
                $this->_fs->get_text_inline( 'Your WordPress user\'s: first & last name, and email address', 'permissions-profile_desc' ),
                $this->_fs->get_text_inline( 'Never miss important updates, get security warnings before they become public knowledge, and receive notifications about special offers and awesome new features.', 'permissions-profile_tooltip' ),
                5,
                false,
                true,
                $load_default_from_storage
            );
        }

        #endregion

        #--------------------------------------------------------------------------------
        #region Optional Permissions
        #--------------------------------------------------------------------------------

        /**
         * @return array[]
         */
        function get_newsletter_permission() {
            return $this->get_permission(
                self::PERMISSION_NEWSLETTER,
                'email-alt',
                $this->_fs->get_text_inline( 'Newsletter', 'permissions-newsletter' ),
                $this->_fs->get_text_inline( 'Updates, announcements, marketing, no spam', 'permissions-newsletter_desc' ),
                '',
                15
            );
        }

        #endregion

        #--------------------------------------------------------------------------------
        #region Permissions Storage
        #--------------------------------------------------------------------------------

        /**
         * @param int|null $blog_id
         *
         * @return bool
         */
        function is_extensions_tracking_allowed( $blog_id = null ) {
            return $this->is_permission_allowed( self::PERMISSION_EXTENSIONS, ! $this->_fs->is_premium(), $blog_id );
        }

        /**
         * @param int|null $blog_id
         *
         * @return bool
         */
        function is_essentials_tracking_allowed( $blog_id = null ) {
            return $this->is_permission_allowed( self::PERMISSION_ESSENTIALS, true, $blog_id );
        }

        /**
         * @param bool $default
         *
         * @return bool
         */
        function is_diagnostic_tracking_allowed( $default = true ) {
            return $this->_fs->is_premium() ?
                $this->is_permission_allowed( self::PERMISSION_DIAGNOSTIC, $default ) :
                $this->is_permission_allowed( self::PERMISSION_SITE, $default );
        }

        /**
         * @param int|null $blog_id
         *
         * @return bool
         */
        function is_homepage_url_tracking_allowed( $blog_id = null ) {
            return $this->is_permission_allowed( $this->get_site_permission_name(), true, $blog_id );
        }

        /**
         * @param int|null $blog_id
         *
         * @return bool
         */
        function update_site_tracking( $is_enabled, $blog_id = null, $only_if_not_set = false ) {
            $permissions = $this->get_site_tracking_permission_names();

            $result = true;
            foreach ( $permissions as $permission ) {
                if ( ! $only_if_not_set || ! $this->is_permission_set( $permission, $blog_id ) ) {
                    $result = ( $result && $this->update_permission_tracking_flag( $permission, $is_enabled, $blog_id ) );
                }
            }

            return $result;
        }

        /**
         * @param string   $permission
         * @param bool     $default
         * @param int|null $blog_id
         *
         * @return bool
         */
        function is_permission_allowed( $permission, $default = false, $blog_id = null ) {
            if ( ! self::is_supported_permission( $permission ) ) {
                return $default;
            }

            return $this->is_permission( $permission, true, $blog_id );
        }

        /**
         * @param string   $permission
         * @param bool     $is_allowed
         * @param int|null $blog_id
         *
         * @return bool
         */
        function is_permission( $permission, $is_allowed, $blog_id = null ) {
            if ( ! self::is_supported_permission( $permission ) ) {
                return false;
            }

            $tag = "is_{$permission}_tracking_allowed";

            return ( $is_allowed === $this->_fs->apply_filters(
                    $tag,
                    $this->_storage->get(
                        $tag,
                        $this->get_permission_default( $permission ),
                        $blog_id,
                        FS_Storage::OPTION_LEVEL_NETWORK_ACTIVATED_NOT_DELEGATED
                    )
                ) );
        }

        /**
         * @param string   $permission
         * @param int|null $blog_id
         *
         * @return bool
         */
        function is_permission_set( $permission, $blog_id = null ) {
            $tag = "is_{$permission}_tracking_allowed";

            $permission = $this->_storage->get(
                $tag,
                null,
                $blog_id,
                FS_Storage::OPTION_LEVEL_NETWORK_ACTIVATED_NOT_DELEGATED
            );

            return is_bool( $permission );
        }

        /**
         * @param string[] $permissions
         * @param bool     $is_allowed
         *
         * @return bool `true` if all given permissions are in sync with `$is_allowed`.
         */
        function are_permissions( $permissions, $is_allowed, $blog_id = null ) {
            foreach ( $permissions as $permission ) {
                if ( ! $this->is_permission( $permission, $is_allowed, $blog_id ) ) {
                    return false;
                }
            }

            return true;
        }

        /**
         * @param string   $permission
         * @param bool     $is_enabled
         * @param int|null $blog_id
         *
         * @return bool `false` if permission not supported or `$is_enabled` is not a boolean.
         */
        function update_permission_tracking_flag( $permission, $is_enabled, $blog_id = null ) {
            if ( is_bool( $is_enabled ) && self::is_supported_permission( $permission ) ) {
                $this->_storage->store(
                    "is_{$permission}_tracking_allowed",
                    $is_enabled,
                    $blog_id,
                    FS_Storage::OPTION_LEVEL_NETWORK_ACTIVATED_NOT_DELEGATED
                );

                return true;
            }

            return false;
        }

        /**
         * @param array<string,bool> $permissions
         */
        function update_permissions_tracking_flag( $permissions ) {
            foreach ( $permissions as $permission => $is_enabled ) {
                $this->update_permission_tracking_flag( $permission, $is_enabled );
            }
        }

        #endregion


        /**
         * @param string $permission
         *
         * @return bool
         */
        function get_permission_default( $permission ) {
            if (
                $this->_fs->is_premium() &&
                self::PERMISSION_EXTENSIONS === $permission
            ) {
                return false;
            }

            // All permissions except for the extensions in paid version are on by default when the user opts in to usage tracking.
            return true;
        }

        /**
         * @return string
         */
        function get_site_permission_name() {
            return $this->_fs->is_premium() ?
                self::PERMISSION_ESSENTIALS :
                self::PERMISSION_SITE;
        }

        /**
         * @return string[]
         */
        function get_site_tracking_permission_names() {
            return $this->_fs->is_premium() ?
                array(
                    FS_Permission_Manager::PERMISSION_ESSENTIALS,
                    FS_Permission_Manager::PERMISSION_EVENTS,
                ) :
                array( FS_Permission_Manager::PERMISSION_SITE );
        }

        #--------------------------------------------------------------------------------
        #region Rendering
        #--------------------------------------------------------------------------------

        /**
         * @param array $permission
         */
        function render_permission( array $permission ) {
            fs_require_template( 'connect/permission.php', $permission );
        }

        /**
         * @param array $permissions_group
         */
        function render_permissions_group( array $permissions_group ) {
            $permissions_group[ 'fs' ] = $this->_fs;

            fs_require_template( 'connect/permissions-group.php', $permissions_group );
        }

        function require_permissions_js() {
            fs_require_once_template( 'js/permissions.php', $params );
        }

        #endregion

        #--------------------------------------------------------------------------------
        #region Helper Methods
        #--------------------------------------------------------------------------------

        /**
         * @param string $id
         * @param string $dashicon
         * @param string $label
         * @param string $desc
         * @param string $tooltip
         * @param int    $priority
         * @param bool   $is_optional
         * @param bool   $is_on_by_default
         * @param bool   $load_from_storage
         *
         * @return array
         */
        private function get_permission(
            $id,
            $dashicon,
            $label,
            $desc,
            $tooltip = '',
            $priority = 10,
            $is_optional = false,
            $is_on_by_default = true,
            $load_from_storage = false
        ) {
            $is_on = $load_from_storage ?
                $this->is_permission_allowed( $id, $is_on_by_default ) :
                $is_on_by_default;

            return array(
                'id'         => $id,
                'icon-class' => $this->_fs->apply_filters( "permission_{$id}_icon", "dashicons dashicons-{$dashicon}" ),
                'label'      => $this->_fs->apply_filters( "permission_{$id}_label", $label ),
                'tooltip'    => $this->_fs->apply_filters( "permission_{$id}_tooltip", $tooltip ),
                'desc'       => $this->_fs->apply_filters( "permission_{$id}_desc", $desc ),
                'priority'   => $this->_fs->apply_filters( "permission_{$id}_priority", $priority ),
                'optional'   => $is_optional,
                'default'    => $this->_fs->apply_filters( "permission_{$id}_default", $is_on ),
            );
        }

        /**
         * @param array $permissions
         *
         * @return array[]
         */
        private function get_sorted_permissions_by_priority( array $permissions ) {
            // Allow filtering of the permissions list.
            $permissions = $this->_fs->apply_filters( 'permission_list', $permissions );

            // Sort by priority.
            uasort( $permissions, 'fs_sort_by_priority' );

            return $permissions;
        }

        #endregion
    }