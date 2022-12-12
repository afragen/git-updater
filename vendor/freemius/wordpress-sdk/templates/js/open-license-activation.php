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

    $license_id = $VARS['license_id'];
?>
<script type="text/javascript">
    (function ($) {
        var prepareLicenseActivationDialog = function () {
            var $dialog = $('.fs-modal-license-activation');

            // Trigger the license activation dialog box.
            $($('.activate-license-trigger')[0]).click();

//            setTimeout(function(){
                $dialog.find('select.fs-licenses option[data-id=<?php echo $license_id ?>]')
                    .prop('selected', true)
                    .change();
//            }, 100);

        };
        if ($('.fs-modal-license-activation').length > 0) {
            prepareLicenseActivationDialog();
        } else {
            $('body').on('licenseActivationLoaded', function () {
                prepareLicenseActivationDialog();
            });
        }
    })(jQuery);
</script>