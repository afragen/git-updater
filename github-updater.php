<?php

/*
Plugin Name: GitHub Updater
Plugin URI: https://github.com/afragen/github-updater
GitHub Plugin URI: https://github.com/afragen/github-updater
Description: Plugin and Theme Updater classes to pull updates of the GitHub based plugins and themes into wordpress. Theme class based upon <a href="https://github.com/WordPress-Phoenix/whitelabel-framework">Whitelabel Framework</a> modifications. Plugin class based upon <a href="https://github.com/codepress/github-plugin-updater">codepress/github-plugin-updater</a>.
Version: 1.4.2
Author: Andy Fragen
License: GNU General Public License v2
License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

//Load base classes and Launch
if( is_admin() ) {
	require_once( 'classes/class-theme-updater.php' );
	require_once( 'classes/class-plugin-updater.php' );
}	


