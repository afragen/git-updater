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
?>
<script type="text/javascript">
    ( function ( $ ) {
        var global = this;

        // Namespace.
        global.FS = global.FS || {};

        //region Switches

        function toggleSwitches( $switches, isOn ) {
            $switches
                .toggleClass( 'fs-on', ( null != isOn ? ( true === isOn ) : isOn ) )
                .toggleClass( 'fs-off', ( null != isOn ? ( false === isOn ) : isOn ) );
        }

        function isSwitch( $switch, isOn ) {
            return $switch.hasClass( isOn ? 'fs-on' : 'fs-off' );
        }

        function getSwitchesStates( $switches, isEnabled ) {
            var switchStates = [];
            for ( var i = 0; i < $switches.length; i++ ) {
                switchStates.push( isSwitch( $( $switches[ i ] ), isEnabled ) );
            }

            return switchStates;
        }

        //endregion

        function toggleGroupOptOut( $button, isEnabled ) {
            setOptInLabel( $button, ! isEnabled );

            $button.data( 'is-enabled', isEnabled );
        }

        /**
         * @param {object} $permissionsSection
         *
         * @returns {string[]}
         */
        function getGroupPermissionIDs( $permissionsSection ) {
            var permissions = [];
            $permissionsSection.find( 'ul li').each( function() {
                permissions.push( $( this ).data( 'permission-id' ) );
            });

            return permissions;
        }

        function getGroupOptOutButton( $section ) {
            return $section.find( '.fs-group-opt-out-button' );
        }

        //region Opt-in/out Labels

        function setUpdatingLabel( $button, isEnabled ) {
            $button.text( isEnabled ?
                '<?php fs_esc_js_echo_inline( 'Opting in', 'opting-in' ) ?>...' :
                '<?php fs_esc_js_echo_inline( 'Opting out', 'opting-out' ) ?>...'
            );
        }

        function getOptInLabel( isEnabled ) {
            return isEnabled ?
                '<?php echo esc_js( fs_text_x_inline( 'Opt In', 'verb', 'opt-in' ) ) ?>' :
                '<?php echo esc_js( fs_text_x_inline( 'Opt Out', 'verb', 'opt-out' ) ) ?>';
        }

        function setOptInLabel( $button, isEnabled ) {
            $button.text( getOptInLabel( isEnabled ) );
        }

        //endregion

        global.FS.Permissions = function () {
            var isUpdating = false;

            function updateStarted() {
                isUpdating = true;
                $body.addClass( 'fs-loading' );
            }

            function updateCompleted() {
                isUpdating = false;
                $body.removeClass( 'fs-loading' );
            }

            return {
                isUpdating: function() {
                    return isUpdating;
                },
                /**
                 * @param {Number} pluginID
                 * @param {Array} permissions
                 * @param {Boolean} isEnabled
                 * @param {Callback} [success]
                 * @param {Callback} [failure]
                 * @param {Callback} [complete]
                 */
                updatePermissions: function(
                    pluginID,
                    permissions,
                    isEnabled,
                    success,
                    failure,
                    complete
                ) {
                    if ( isUpdating ) {
                        return;
                    }

                    updateStarted();

                    var
                        $permissionsContainer = $( '#fs_opt_out_' + pluginID );

                    $.ajax( {
                        url     : <?php echo Freemius::ajax_url() ?>,
                        method  : 'POST',
                        data    : {
                            action          : $permissionsContainer.data( 'action' ),
                            security        : $permissionsContainer.data( 'security' ),
                            module_id       : pluginID,
                            _wp_http_referer: '<?php echo Freemius::current_page_url() ?>',
                            permissions     : permissions.join( ',' ),
                            is_enabled      : isEnabled
                        },
                        success : function ( resultObj ) {
                            if ( resultObj.success ) {
                                if ( success ) {
                                    success( resultObj );
                                }
                            } else {
                                if ( failure ) {
                                    failure( resultObj );
                                }
                            }
                        },
                        error   : failure,
                        complete: function () {
                            if ( complete ) {
                                complete();
                            }

                            updateCompleted();
                        }
                    });
                },
                updateGroupPermissions: function(
                    pluginID,
                    groupID,
                    isEnabled,
                    success,
                    failure,
                    complete
                ) {
                    if ( isUpdating ) {
                        return;
                    }

                    var
                        $modal              = $( '#fs_opt_out_' + pluginID ),
                        $permissionsSection = $modal.find( '.fs-permissions-section.fs-' + groupID + '-permissions' ),
                        $optOutButton       = getGroupOptOutButton( $permissionsSection ),
                        $permissions        = $permissionsSection.find( 'ul li'),
                        permissions         = [];

                    $permissions.each( function() {
                        permissions.push( $( this ).data( 'permission-id' ) );
                    });

                    setUpdatingLabel( $optOutButton, isEnabled );

                    this.updatePermissions(
                        pluginID,
                        permissions,
                        isEnabled,
                        function( resultObj ) {
                            if ( resultObj.success ) {
                                toggleGroupOptOut( $optOutButton, isEnabled );

                                // Update permissions state.
                                $permissions.toggleClass( 'fs-disabled', ! isEnabled );

                                // Update switches state, if there are any.
                                toggleSwitches( $permissions.find( '.fs-switch' ), isEnabled );

                                if ( success ) {
                                    success();
                                }
                            }
                        },
                        function ( resultObj ) {
                            setOptInLabel( $optOutButton, isEnabled );

                            if ( failure ) {
                                failure( resultObj );
                            }
                        },
                        complete
                    );
                }
            };
        }();

        var $body = $( 'body' )

        global.FS.OptOut = function (
            pluginID,
            slug,
            type,
            isRegistered,
            isTrackingAllowed,
            reconnectUrl
        ) {
            var $modal = $( '#fs_opt_out_' + pluginID ),
                actionLinkSelector  = ('theme' === type ? '#fs_theme_opt_in_out' : 'span.opt-in-or-opt-out.' + slug + ' a' );

            //region Error Handling

            function hideError( $optOutErrorMessage ) {
                $optOutErrorMessage = $optOutErrorMessage || $modal.find( '.opt-out-error-message' );
                $optOutErrorMessage.hide();
            }

            function showError( $optOutErrorMessage, msg ) {
                $optOutErrorMessage.find( ' > p' ).html( msg );
                $optOutErrorMessage.show();
            }

            //endregion

            function backToPermissionsList() {
                $modal.find( '.fs-opt-out-disclaimer' )
                      .hide();

                $modal.find( '.fs-opt-out-permissions' )
                      .show();
            }

            function removeFeedbackIndicators() {
                $modal.find( '.fs-switch-feedback' )
                      .remove();
            }

            //region Modal Dialog

            function closeModal() {
                $modal.removeClass( 'active' );
                $body.removeClass( 'has-fs-modal' );
                $modal.hide();
            }

            function resetModal() {
                hideError();
                removeFeedbackIndicators();
                backToPermissionsList();
            }

            function showModal() {
                resetModal();

                // Display the dialog box.
                $modal.show();
                $modal.addClass( 'active' );
                $body.addClass( 'has-fs-modal' );
            }

            //endregion

            function registerActionLinkClick() {
                $body.on( 'click', actionLinkSelector, function( evt ) {
                    evt.preventDefault();

                    showModal();

                    return false;
                });
            }

            function registerEventHandlers() {
                // If the user has clicked outside the window, close the modal.
                $modal.on( 'click', '.fs-close, .button-close', function() {
                    closeModal();
                    return false;
                } );

                $modal.on( 'click', '.fs-permissions .fs-switch', function () {
                    if ( FS.Permissions.isUpdating() ) {
                        return false;
                    }

                    var $switch = $( this ),
                        $permission = $switch.closest( '.fs-permission' );

                    toggleSwitches( $switch );

                    $permission.toggleClass( 'fs-disabled' );

                    var $optOutContainer = $switch.closest( '.fs-modal-opt-out' );

                    if ( 0 === $optOutContainer.length ) {
                        return;
                    }

                    // Remove previously added feedback element.
                    $switch.closest( '.fs-modal-dialog' )
                           .find( '.fs-switch-feedback' )
                           .remove();

                    var $switchFeedback = $( '<span class="fs-switch-feedback"><i class="fs-ajax-spinner"></i></span>' );

                    $switch.after( $switchFeedback )

                    var
                        permissionID = $permission.data( 'permission-id' ),
                        isEnabled = isSwitch( $switch, true );

                    FS.Permissions.updatePermissions(
                        $optOutContainer.data( 'plugin-id' ),
                        [ permissionID ],
                        isEnabled,
                        function () {
                            $switchFeedback.addClass( 'success' );
                            $switchFeedback.html( '<i class="dashicons dashicons-yes"></i> <?php echo esc_js( fs_text_inline( 'Saved', 'saved' ) ) ?>' );

                            var
                                $permissionsGroup = $switch.closest( '.fs-permissions-section' ),
                                $groupPermissions = $permissionsGroup.find( 'ul li' );

                            var allGroupPermissionsUseSameValue = false;

                            if (
                                isEnabled &&
                                0 === $groupPermissions.filter( '.fs-disabled' ).length )
                            {
                                allGroupPermissionsUseSameValue = true;
                            } else if (
                                ! isEnabled &&
                                $groupPermissions.length === $groupPermissions.filter( '.fs-disabled' ).length
                            ) {
                                allGroupPermissionsUseSameValue = true;
                            }

                            if ( allGroupPermissionsUseSameValue ) {
                                toggleGroupOptOut( getGroupOptOutButton( $permissionsGroup ), isEnabled );
                            }
                        },
                        function () {
                            // Revert switch.
                            toggleSwitches( $switch );

                            $switchFeedback.remove();
                        }
                    )
                });

                // Move back to the permissions list if cancelling opt-out.
                $modal.on( 'click', '.fs-opt-out-disclaimer .fs-opt-out-cancel-button', function ( evt ) {
                    backToPermissionsList();
                });

                $modal.on( 'click', '.fs-opt-out-disclaimer .fs-modal-footer .fs-opt-out-button', function ( evt ) {
                    var
                        $optOutButton     = $( this ),
                        $actionLink       = $( actionLinkSelector ),
                        isEnabled         = true,
                        $optOutDisclaimer = $( $optOutButton.closest( '.fs-opt-out-disclaimer' )[ 0 ] ),
                        groupID           = $optOutDisclaimer.data( 'group-id' ),
                        $errorMessage     = $optOutDisclaimer.find( '.opt-out-error-message' );

                    setUpdatingLabel( $optOutButton, ! isEnabled );

                    $optOutDisclaimer.find( '.button-primary' ).prop( 'disabled', true );

                    hideError( $errorMessage );

                    FS.Permissions.updateGroupPermissions(
                        pluginID,
                        groupID,
                        ! isEnabled,
                        function () {
                            if ( 'communication' === groupID ) {
                                window.location.reload();
                            } else {
                                setOptInLabel( $actionLink, ! isEnabled );

                                backToPermissionsList();
                            }
                        },
                        function ( resultObj ) {
                            setOptInLabel( $optOutButton, false );

                            showError( $errorMessage, resultObj.error );
                        },
                        function () {
                            if ( 'communication' !== groupID ) {
                                setOptInLabel( $optOutButton, false );
                            }

                            $optOutDisclaimer.find( '.button-primary' ).prop( 'disabled', false );
                        }
                    );
                } );

                $modal.on( 'click', '.fs-group-opt-out-button', function ( evt ) {
                    evt.preventDefault();

                    if ( FS.Permissions.isUpdating() ) {
                        return;
                    }

                    var
                        $optOutButton     = $( this ),
                        groupID           = $optOutButton.data( 'group-id' ),
                        isEnabled         = $optOutButton.data( 'is-enabled' ),
                        $optOutDisclaimer = $modal.find( '.fs-' + groupID + '-opt-out' ),
                        isConfirmRequired = ( 0 < $optOutDisclaimer.length ),
                        $errorMessage     = $modal.find( '.fs-opt-out-permissions .opt-out-error-message' );

                    $errorMessage.hide();

                    if ( isConfirmRequired ) {
                        if ( isEnabled ) {
                            // Move to disclaimer window.
                            $modal.find( '.fs-opt-out-permissions' )
                                  .hide();

                            $optOutDisclaimer.show();
                        } else {
                            // Opt-in.
                            FS.Permissions.updateGroupPermissions(
                                pluginID,
                                groupID,
                                ! isEnabled,
                                ( 'communication' !== groupID ) ?
                                    null :
                                    function () {
                                        window.location.reload();
                                    },
                                function ( resultObj ) {
                                    showError( $errorMessage, resultObj.error );
                                }
                            );
                        }
                    } else {
                        // Remove previously added feedback element.
                        $modal.find( '.fs-switch-feedback' )
                              .remove();

                        var $switches = $optOutButton.closest( '.fs-permissions-section' )
                                                     .find( '.fs-permission .fs-switch' );

                        var switchStates = getSwitchesStates( $switches, isEnabled );

                        toggleSwitches( $switches, ! isEnabled );

                        $switches.closest( '.fs-permission' )
                                 .toggleClass( 'fs-disabled', isEnabled );

                        var $switchFeedback = $( '<span class="fs-switch-feedback"><i class="fs-ajax-spinner"></i></span>' );

                        $optOutButton.after( $switchFeedback )

                        setUpdatingLabel( $optOutButton, ! isEnabled );

                        FS.Permissions.updatePermissions(
                            pluginID,
                            getGroupPermissionIDs( $modal.find( '.fs-permissions-section.fs-' + groupID + '-permissions' ) ),
                            ! isEnabled,
                            function () {
                                $switchFeedback.addClass( 'success' );
                                $switchFeedback.html( '<i class="dashicons dashicons-yes"></i> <?php echo esc_js( fs_text_inline( 'Saved', 'saved' ) ) ?>' );

                                toggleGroupOptOut( $optOutButton, ! isEnabled );
                            },
                            function () {
                                // Revert switches to their previous state.
                                for ( var i = 0; i < switchStates.length; i++ ) {
                                    if ( switchStates[ i ] ) {
                                        toggleSwitches( $( $switches[ i ] ), isEnabled );
                                        $( $switches[ i ] ).removeClass( 'fs-disabled' );
                                    }
                                }

                                toggleGroupOptOut( $optOutButton, isEnabled );
                            }
                        )
                    }
                });
            }

            if ( 'theme' === type ) {
                /**
                 * Add opt-in/out button to the active theme's buttons collection
                 * in the theme's extended details overlay.
                 *
                 * @author Vova Feldman (@svovaf)
                 * @since 1.2.2.7
                 */
                $( '.theme-overlay' ).contentChange( function () {
                    if ( 0 === $( '.theme-overlay.active' ).length ) {
                        // Add opt-in/out button only to the currently active theme.
                        return;
                    }

                    if ( $( '#fs_theme_opt_in_out' ).length > 0 ) {
                        // Button already there.
                        return;
                    }

                    var label       = getOptInLabel( ! isTrackingAllowed ),
                        href        = ( isTrackingAllowed || isRegistered ) ? '' : reconnectUrl,
                        $actionLink = $( '<a id="fs_theme_opt_in_out" href="' + encodeURI( href ) + '" class="button">' + label + '</a>' );

                    $( '.theme-wrap .theme-actions .active-theme' ).append( $actionLink );

                    if ( isRegistered && '' === href ) {
                        registerActionLinkClick();
                    }
                });
            }

            if ( isRegistered ) {
                if ( 'theme' !== type ) {
                    registerActionLinkClick();
                }

                registerEventHandlers();
            }

        };
    } )( jQuery );
</script>