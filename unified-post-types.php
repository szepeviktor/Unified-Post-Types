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

	private $global_post_type_needs_reset;

	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new Unified_Post_Types;
			self::$instance->setup_actions();
			self::$instance->setup_filters();
		}
		return self::$instance;
	}

	/**
	 * Set up plugin actions
	 */
	private function setup_actions() {
		add_action( 'pre_get_posts', array( $this, 'action_pre_get_posts' ) );
		add_action( 'wp', array( $this, 'action_wp_reset_primary_post_type' ) );
		add_action( 'admin_menu', array( $this, 'action_admin_menu_late' ), 100 );
	}

	/**
	 * Set up plugin filters
	 */
	private function setup_filters() {
		add_filter( 'the_title', array( $this, 'filter_the_title' ), 11, 2 ); // after esc_html()
		add_filter( 'parent_file', array( $this, 'filter_parent_file' ) );
	}

	/**
	 * Get the primary post type under which all post types will be consolidated
	 *
	 * @return string
	 */
	public function get_primary_post_type() {
		return apply_filters( 'primary_unified_post_type', 'post' );
	}

	/**
	 * Get all of the secondary post types which should appear with the primary post type
	 *
	 * @return array
	 */
	public function get_unified_post_types() {
		$post_types = apply_filters( 'unified_post_types', array() );
		if ( ! in_array( $this->get_primary_post_type(), $post_types ) ) {
			$post_types[] = $this->get_primary_post_type();
		}
		return $post_types;
	}

	/**
	 * Whether or not we should be hacking the view
	 *
	 * @return bool
	 */
	public function is_unified_post_type_screen() {
		if ( ! is_admin() ) {
			return false;
		}
		$screen = get_current_screen();
		if ( $screen && 'edit' === $screen->base && ! empty( $screen->post_type ) && $this->get_primary_post_type() === $screen->post_type ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Include all post types in our unified post types view
	 */
	public function action_pre_get_posts( $query ) {

		if ( ! $query->is_main_query() || ! $this->is_unified_post_type_screen() ) {
			return;
		}

		$query->set( 'post_type', $this->get_unified_post_types() );
		$this->global_post_type_needs_reset = true;

	}

	/**
	 * Resets the primary post type after wp_edit_posts_query() runs, which clobbers existing $post_type global
	 */
	public function action_wp_reset_primary_post_type( $wp ) {
		global $post_type;

		if ( ! empty( $this->global_post_type_needs_reset ) && $this->is_unified_post_type_screen() ) {
			$this->global_post_type_needs_reset = false;
			$post_type = $this->get_primary_post_type();
		}

	}

	/**
	 * Change the label for the primary post type; remove links to others
	 */
	public function action_admin_menu_late() {
		global $menu, $pagenow, $submenu;

		$unified_post_types = $this->get_unified_post_types();
		$primary_post_type = $this->get_primary_post_type();
		$primary_menu_key = 'post' === $primary_post_type ? 'edit.php' : 'edit.php?post_type=' . $primary_post_type;
		$screen = get_current_screen();
		foreach( $menu as $key => $menu_item ) {
			foreach( $unified_post_types as $post_type ) {
				// Remove links to unified posts that aren't the primary
				if ( ! empty( $menu_item[2] ) && 'edit.php?post_type=' . $post_type === $menu_item[2] && $post_type !== $primary_post_type ) {
					unset( $menu[ $key ] );
					continue;
				}
			}

			if ( ! empty( $menu_item[2] ) && $primary_menu_key === $menu_item[2] ) {
				$menu[ $key ][0] = esc_html__( 'Content', 'unified-post-types' );
				foreach( $submenu[ $primary_menu_key ] as $key => $submenu_item ) {
					if ( $submenu_item[2] === $primary_menu_key ) {
						$submenu[ $primary_menu_key ][ $key ][0] = esc_html__( 'All Content', 'unified-post-types' );
					}
				}
			}
		}

	}

	/**
	 * Prepend the post type icon to the title for visual distinction
	 *
	 * @param string $title
	 * @param int $id
	 */
	public function filter_the_title( $title, $id = null ) {
		if ( ! $this->is_unified_post_type_screen() ) {
			return $title;
		}

		$post = get_post( $id );
		if ( ! $post || ! in_array( $post->post_type, $this->get_unified_post_types() ) ) {
			return $title;
		}

		$post_type_obj = get_post_type_object( $post->post_type );
		if ( is_string( $post_type_obj->menu_icon ) ) {
			// Special handling for dashicons.
			if ( 0 === strpos( $post_type_obj->menu_icon, 'dashicons-' ) ) {
				$menu_icon = '<i class="' . esc_attr( 'dashicons ' . $post_type_obj->menu_icon ) . '"></i>';
			} else {
				$menu_icon = '<img src="' . esc_url( $post_type_obj->menu_icon ) . '" />';
			}
		} else {
			$menu_icon = '<i class="dashicons dashicons-admin-post"></i>';
		}

		return $menu_icon . ' ' . $title;
	}

	/**
	 * Reset "Parent File" to the primary post type for all post types
	 * Core has a filter for parent_file, but we need to stomp on the $submenu_file global
	 *
	 * @param string $parent_file
	 * @return string
	 */
	public function filter_parent_file( $parent_file ) {
		global $submenu_file, $pagenow;

		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base || ! in_array( $screen->post_type, $this->get_unified_post_types() ) ) {
			return $parent_file;
		}

		$primary_post_type = $this->get_primary_post_type();
		if ( 'post' === $primary_post_type ) {
			$parent_file = 'edit.php';
		} else {
			$parent_file = 'edit.php?post_type=' . $primary_post_type;
		}

		if ( 'add' !== $screen->action ) {
			$submenu_file = $parent_file;
		}
		return $parent_file;
	}

}

function Unified_Post_Types() {
	return Unified_Post_Types::get_instance();
}
add_action( 'after_setup_theme', 'Unified_Post_Types' );
