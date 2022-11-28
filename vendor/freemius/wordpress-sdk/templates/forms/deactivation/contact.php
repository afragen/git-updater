<?php
    /**
     * @package     Freemius
     * @copyright   Copyright (c) 2015, Freemius, Inc.
     * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since       1.2.0
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * @var array $VARS
     */
    $fs   = freemius( $VARS['id'] );
    $slug = $fs->get_slug();

    echo fs_text_inline( 'Sorry for the inconvenience and we are here to help if you give us a chance.', 'contact-support-before-deactivation', $slug )
            . sprintf(" <a href='%s' class='button button-small button-primary'>%s</a>",
                $fs->contact_url( 'technical_support' ),
                fs_text_inline( 'Contact Support', 'contact-support', $slug )
            );
