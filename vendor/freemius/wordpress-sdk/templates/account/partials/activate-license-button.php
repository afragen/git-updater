<?php
    /**
     * @package     Freemius
     * @copyright   Copyright (c) 2015, Freemius, Inc.
     * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since       2.0.0
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * @var array             $VARS
     * @var Freemius          $fs
     * @var FS_Plugin_License $available_license
     * @var string            $slug
     */
    $fs                = $VARS['freemius'];
    $available_license = $VARS['license'];
    $premium_plan      = $VARS['plan'];
    $slug              = $VARS['slug'];

    $blog_id    = ! empty( $VARS['blog_id'] ) && is_numeric( $VARS['blog_id'] ) ?
        $VARS['blog_id'] :
        '';
    $install_id = ! empty( $VARS['install_id'] ) && FS_Site::is_valid_id( $VARS['install_id'] ) ?
        $VARS['install_id'] :
        '';

    $activate_plan_text = fs_text_inline( 'Activate %s Plan', 'activate-x-plan', $slug );

    $action = 'activate_license';
?>
<form action="<?php echo $fs->_get_admin_page_url( 'account' ) ?>" method="POST">
    <input type="hidden" name="fs_action" value="<?php echo $action ?>">
    <?php wp_nonce_field( trim("{$action}:{$blog_id}:{$install_id}", ':') ) ?>
    <input type="hidden" name="install_id" value="<?php echo $install_id ?>">
    <input type="hidden" name="blog_id" value="<?php echo $blog_id ?>">
    <input type="hidden" name="license_id" value="<?php echo $available_license->id ?>">
    <input type="submit" class="fs-activate-license button<?php echo ! empty( $VARS['class'] ) ? ' ' . $VARS['class'] : '' ?>"
           value="<?php echo esc_attr( sprintf(
               $activate_plan_text . '%s',
               $premium_plan->title,
               ( $VARS['is_localhost'] && $available_license->is_free_localhost ) ?
                   ' [' . fs_text_inline( 'Localhost', 'localhost', $slug ) . ']' :
                   ( $available_license->is_single_site() ?
                       '' :
                       ' [' . ( 1 < $available_license->left() ?
                           sprintf( fs_text_x_inline( '%s left', 'as 5 licenses left', 'x-left', $slug ), $available_license->left() ) :
                           strtolower( fs_text_inline( 'Last license', 'last-license', $slug ) ) ) . ']'
                   )
           ) ) ?> ">
</form>