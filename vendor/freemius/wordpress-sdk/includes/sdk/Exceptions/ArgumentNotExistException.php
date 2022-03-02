<?php
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

	if ( ! class_exists( 'Freemius_InvalidArgumentException' ) ) {
		exit;
	}

	if ( ! class_exists( 'Freemius_ArgumentNotExistException' ) ) {
		class Freemius_ArgumentNotExistException extends Freemius_InvalidArgumentException {
		}
	}
