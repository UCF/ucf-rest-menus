<?php defined( 'ABSPATH' ) or die( 'No script kiddies please!' ); ?>
<?php
/*
Plugin Name: UCF Rest Menus
Plugin URI: http://github.com/UCF/ucf-rest-menus/
Description: Provides rest api for accessing WordPress menus.
Version: 1.0.2
GitHub Plugin URI: UCF/ucf-rest-menus
Author: UCF Web Communications
Author URI: http://github.com/UCF/
License: MIT
Note: This plugin is heavily based on the wp-api-menus plugin which can
be found here: https://wordpress.org/plugins/wp-api-menus/. In the initial
commit of the plugin, the code is almost exactly the same as the v2 code
of the original. Because functionality within UCF themes will be dependent
upon this plugin, the code was slightly modified and put within its own
repository to prevent incompatibilties from arising as the  original plugin
author makes changes.
*/
?>
<?php

if ( ! class_exists( 'UCF_REST_Menus' ) ) {
	/**
	 * UCF Rest Menus class
	 * WP API Menus support for WP API v2
	 * @author Jim Barnes
	 * @since 1.0.0
	 **/
	class UCF_REST_Menus {

		/*
		 * Get WP API namespace
		 * @since 1.0.0
		 * @return string
		 */
		public static function get_api_namespace() {
			return 'wp/v2';
		}

		/*
		 * Get plugin namespace.
		 * @author Jim Barnes
		 * @since 1.0.0
		 * @return string
		 */
		public static function get_plugin_namespace() {
			return 'ucf-rest-menus/v1';
		}

		/*
		 * Register API routes
		 * @author Jim Barnes
		 * @since 1.0.0
		 * @return array
		 */
		public function register_routes() {

			register_rest_route( self::get_plugin_namespace(), '/menus', array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'get_menus' )
				)
			) );

			register_rest_route( self::get_plugin_namespace(), '/menus/(?P<id>\d+)', array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'get_menu' ),
					'args'     => array(
						'context' => array(
							'default' => 'view'
						)
					)
				)
			) );

			register_rest_route( self::get_plugin_namespace(), '/menu-locations', array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'get_menu_locations' )
				)
			) );

			register_rest_route( self::get_plugin_namespace(), '/menu-locations/(?P<location>[a-zA-Z0-9_-]+)', array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'get_menu_location' )
				)
			) );

		}

		/*
		 * Get Menus
		 * @author Jim Barnes
		 * @since 1.0.0
		 * @returns array
		 */
		public static function get_menus() {

			$rest_url = trailingslashit( get_rest_url() . self::get_plugin_namespace() . '/menus/' );

			$wp_menus = wp_get_nav_menus();

			$retval = array();

			foreach( $wp_menus as $i=>$wp_menu ) {
				$menu = (array) $wp_menu;

				$retval[$i]                = array();
				$retval[$i]['ID']          = $menu['term_id'];
				$retval[$i]['name']        = $menu['name'];
				$retval[$i]['slug']        = $menu['slug'];
				$retval[$i]['description'] = $menu['description'];
				$retval[$i]['count']       = $menu['count'];

				$retval[$i]['meta']['links']['collection'] = $rest_url;
				$retval[$i]['meta']['links']['self']       = $rest_url . $menu['term_id'];
			}

			return apply_filters( 'rest_menus_format_menus', $retval );
		}

		/*
		* Get Menu Details
		* @author Jim Barnes
		* @since 1.0.0
		* @return array
		*/
		public static function get_menu( $request ) {
			$id             = (int) $request['id'];
			$rest_url       = get_rest_url() . self::get_plugin_namespace() . '/menus/';
			$wp_menu_object = isset( $id ) ? wp_get_nav_menu_object( $id ) : null;
			$wp_menu_items  = isset( $id ) ? wp_get_nav_menu_items( $id ) : array();

			$retval = array();

			if ( $wp_menu_object ) {
				$menu = (array) $wp_menu_object;

				$retval['ID']          = abs( $menu['term_id'] );
				$retval['name']        = $menu['name'];
				$retval['slug']        = $menu['slug'];
				$retval['description'] = $menu['description'];
				$retval['count']       = $menu['count'];

				$menu_items = array();
				foreach( $wp_menu_items as $item ) {
					$menu_items[] = $this->format_menu_item( $item );
				}

				$menu_items = $this->nested_menu_items( $menu_items, 0 );

				$retval['items'] = $menu_items;
				$retval['meta']['links']['collection'] = $rest_url;
				$retval['meta']['links']['self']       = $rest_url . $id;
			}

			return apply_filters( 'rest_menus_format_menu', $retval );
		}

		/*
		 * Get Menu Locations
		 * @since 1.0.0
		 * @author Jim Barnes
		 * @return array
		 */
	   public static function get_menu_locations( $request ) {
		   $rest_url = get_rest_url() . self::get_plugin_namespace() . '/menu-locations/';

		   $locations = get_nav_menu_locations();
		   $registered_menus = get_registered_nav_menus();

		   $retval = array();
		   if ( $locations && $registered_menus ) {
			   foreach( $registered_menus as $slug=>$label ) {
				   $retval['slug']['ID']                  = $locations[$slug];
				   $retval['slug']['label']               = $label;
				   $retval['meta']['links']['collection'] = $rest_url;
				   $retval['meta']['links']['self']       = $rest_url . $slug;
			   }
		   }

		   return $retval;
	   }

	   /*
		* Get menu for location
		* @since 1.0.0
		* @author Jim Barnes
		* return array
		*/
		public function get_menu_location( $request ) {
			$params     = $request->get_params();
			$location   = $params['location'];
			$locations  = get_nav_menu_locations();

			if ( ! isset( $locations[$location] ) ) {
				return array();
			}

			$wp_menu_object = wp_get_nav_menu_object( $locations[$location] );
			$wp_menu_items  = wp_get_nav_menu_items( $wp_menu_object->term_id );

			if ( $wp_menu_object ) {
				$menu = (array) $wp_menu_object;

				$retval['ID']          = abs( $menu['term_id'] );
				$retval['name']        = $menu['name'];
				$retval['slug']        = $menu['slug'];
				$retval['description'] = $menu['description'];
				$retval['count']       = $menu['count'];

				$menu_items = array();
				foreach( $wp_menu_items as $item ) {
					$menu_items[] = $this->format_menu_item( $item );
				}

				$menu_items = $this->nested_menu_items( $menu_items, 0 );

				$retval['items'] = $menu_items;
				$retval['meta']['links']['collection'] = $rest_url;
				$retval['meta']['links']['self']       = $rest_url . $id;
			}

			return apply_filters( 'rest_menus_format_menu', $retval );
		}

		/*
		* Handle nested menu items.
		* @since 1.0.0
		* @author Jim Barnes
		* @return string
		*/
		private function nested_menu_items( &$menu_items, $parent=null ) {
			$parents = array();
			$children = array();

			array_map( function( $i ) use ( $parent, &$children, &$parents ) {
				if ( $i['id'] !== $parent && $i['parent'] == $parent ) {
					$parents[] = $i;
				} else {
					$children[] = $i;
				}
			}, $menu_items );

			foreach( $parents as &$parent ) {
				if ( $this->has_children( $children, $parent['id'] ) ) {
					$parent['children'] = $this->nested_menu_items( $children, $parent['id'] );
				}
			}

			return $parents;
		}

		/*
		* Check if a collection of menu items contains an item that is the parent if of id.
		* @since 1.0.0
		* @author Jim Barnes
		* @return array
		*/
		private function has_children( $items, $id ) {
			return array_filter( $items, function( $i ) use ( $id ) {
				return $i['parent'] == $id;
			} );
		}

		/*
		 * Format a menu item for REST API
		 * @since 1.0.0
		 * @author Jim Barnes
		 * @return array
		 */
		public function format_menu_item( $menu_item, $children=false, $menu=array() ) {
			$item = (array) $menu_item;

			$retval = array(
				'id'          => abs( $item['ID'] ),
				'order'       => (int) $item['menu_order'],
				'parent'      => abs( $item['menu_item_parent'] ),
				'title'       => $item['title'],
				'url'         => $item['url'],
				'attr'        => $item['attr_title'],
				'target'      => $item['target'],
				'classes'     => $item['classes'],
				'xfn'         => $item['xfn'],
				'description' => $item['description'],
				'object_id'   => abs( $item['object_id'] ),
				'object'      => $item['object'],
				'type'        => $item['type'],
				'type_label'  => $item['type_label']
			);

			if ( $children === true && ! empty( $menu ) ) {
				$retval['children'] = $this->get_nav_menu_item_children( $item['ID'], $menu );
			}

			return apply_filters( 'rest_menus_format_menu_item', $retval );
		}

		/*
		 * Returns all child nav_menu_items under a specific parent.
		 * @since 1.0.0
		 * @author Jim Barnes
		 * @return array
		 */
		private function get_nav_menu_item_children( $parent_id, $nav_menu_items, $depth=true ) {
			$retval = array();

			foreach( (array) $nav_menu_items as $nav_menu_item ) {
				if ( $nav_menu_item->menu_item_parent == $parent_id ) {
					$retval[] = $this->format_menu_item( $nav_menu_item, true, $nav_menu_items );

					if ( $depth ) {
						if ( $children = $this->get_nav_menu_item_children( $nav_menu_item->ID, $nav_menu_items ) ) {
							$retval = array_merge( $retval, $children );
						}
					}
				}
			}

			return $retval;
		}
	}
}

if ( ! function_exists( 'ucf_rest_menus_init' ) ) {

	function ucf_rest_menus_init() {
		$class = new UCF_REST_Menus();
		add_filter( 'rest_api_init', array( $class, 'register_routes' ) );
	}

	add_action( 'init', 'ucf_rest_menus_init' );

}

?>