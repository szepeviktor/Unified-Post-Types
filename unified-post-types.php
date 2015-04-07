<?php
/*
Plugin Name: Unified Post Types
Version: 0.1-alpha
Description: Consolidate your custom post types into one "Manage Posts" view.
Author: Daniel Bachhuber, Fusion
Author URI: http://fusion.net/
Plugin URI: http://wordpress.org/plugins/unified-post-types/
Text Domain: unified-post-types
Domain Path: /languages
*/

class Unified_Post_Types {

	private static $instance;

	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new Unified_Post_Types;
		}
		return self::$instance;
	}

}

function Unified_Post_Types() {
	return Unified_Post_Types::get_instance();
}
add_action( 'after_setup_theme', 'Unified_Post_Types' );
