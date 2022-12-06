<?php
    /**
     * @package     Freemius
     * @copyright   Copyright (c) 2015, Freemius, Inc.
     * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since       2.5.1
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    if ( ! function_exists( 'fs_migrate_251' ) ) {
        function fs_migrate_251( Freemius $fs, $install_by_blog_id ) {
            $permission_manager = FS_Permission_Manager::instance( $fs );

            /**
             * @var FS_Site $install
             */
            foreach ( $install_by_blog_id as $blog_id => $install ) {
                if ( true === $install->is_disconnected ) {
                    $permission_manager->update_site_tracking(
                        false,
                        ( 0 == $blog_id ) ? null : $blog_id,
                        // Update only if permissions are not yet set.
                        true
                    );
                }
            }
        }
    }