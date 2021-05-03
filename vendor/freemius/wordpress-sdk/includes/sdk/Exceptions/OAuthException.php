<?php
	if ( ! class_exists( 'Freemius_Exception' ) ) {
		exit;
	}

	if ( ! class_exists( 'Freemius_OAuthException' ) ) {
		class Freemius_OAuthException extends Freemius_Exception {
			public function __construct( $pResult ) {
				parent::__construct( $pResult );
			}
		}
	}