<?php
	if ( ! class_exists( 'Freemius_InvalidArgumentException' ) ) {
		exit;
	}

	if ( ! class_exists( 'Freemius_EmptyArgumentException' ) ) {
		class Freemius_EmptyArgumentException extends Freemius_InvalidArgumentException {
		}
	}