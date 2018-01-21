<?php
/**
 * GitHub Updater
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

namespace Fragen\GitHub_Updater;


/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class API_PseudoTrait
 *
 * Used to access methods in class API for PHP < 5.4 instead of using class API as a Trait.
 *
 * @package Fragen\GitHub_Updater
 */
class API_PseudoTrait extends API {
}
