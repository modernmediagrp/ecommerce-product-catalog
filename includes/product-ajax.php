<?php

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Manages product post type
 *
 * Here all product fields are defined.
 *
 * @version        1.1.1
 * @package        ecommerce-product-catalog/includes
 * @author        Norbert Dreszer
 */
add_action( 'wp_ajax_nopriv_ic_self_submit', 'ic_ajax_self_submit' );
add_action( 'wp_ajax_ic_self_submit', 'ic_ajax_self_submit' );

/**
 * Manages ajax price format
 *
 */
function ic_ajax_self_submit() {
	if ( isset( $_POST[ 'self_submit_data' ] ) ) {
		$params				 = array();
		parse_str( $_POST[ 'self_submit_data' ], $params );
		$_GET				 = $params;
		global $ic_ajax_query_vars;
		$ic_ajax_query_vars	 = json_decode( stripslashes( $_POST[ 'query_vars' ] ), true );
		if ( (isset( $ic_ajax_query_vars[ 'post_type' ] ) && !in_array( $ic_ajax_query_vars[ 'post_type' ], product_post_type_array() ) ) || (isset( $ic_ajax_query_vars[ 'post_status' ] ) && $ic_ajax_query_vars[ 'post_status' ] !== 'publish') ) {
			wp_die();
			return;
		}
		do_action( 'ic_ajax_self_submit', $ic_ajax_query_vars, $params );
		if ( isset( $params[ 's' ] ) ) {
			$ic_ajax_query_vars[ 's' ] = $params[ 's' ];
		}
		if ( isset( $params[ 'page' ] ) ) {
			$ic_ajax_query_vars[ 'paged' ] = $params[ 'page' ];
		}
		if ( $ic_ajax_query_vars[ 'post_type' ] != 'al_product' ) {
			$_GET[ 'post_type' ] = $ic_ajax_query_vars[ 'post_type' ];
		}
		$posts = new WP_Query( $ic_ajax_query_vars );
		if ( !empty( $ic_ajax_query_vars[ 'paged' ] ) && $ic_ajax_query_vars[ 'paged' ] > 1 && empty( $posts->post ) ) {
			unset( $ic_ajax_query_vars[ 'paged' ] );
			unset( $_GET[ 'page' ] );
			$return[ 'remove_pagination' ]	 = 1;
			$posts							 = new WP_Query( $ic_ajax_query_vars );
		}
		if ( !empty( $_POST[ 'shortcode' ] ) ) {
			global $shortcode_query;
			$shortcode_query = $posts;
		}
		$GLOBALS[ 'wp_query' ]			 = $posts;
		$archive_template				 = get_product_listing_template();
		$multiple_settings				 = get_multiple_settings();
		remove_all_actions( 'before_product_list' );
		ob_start();
		ic_product_listing_products( $archive_template, $multiple_settings );
		$return[ 'product-listing' ]	 = ob_get_clean();
		$old_request_url				 = $_SERVER[ 'REQUEST_URI' ];
		$_SERVER[ 'REQUEST_URI' ]		 = $_POST[ 'request_url' ];
		ob_start();
		add_filter( 'get_pagenum_link', 'ic_ajax_pagenum_link' );
		product_archive_pagination();
		remove_filter( 'get_pagenum_link', 'ic_ajax_pagenum_link' );
		$return[ 'product-pagination' ]	 = ob_get_clean();
		if ( !empty( $_POST[ 'ajax_elements' ][ 'product-category-filter-container' ] ) ) {
			ob_start();
			the_widget( 'product_category_filter' );
			$return[ 'product-category-filter-container' ] = ob_get_clean();
		}
		if ( !empty( $_POST[ 'ajax_elements' ][ 'product_price_filter' ] ) ) {
			ob_start();
			the_widget( 'product_price_filter' );
			$return[ 'product_price_filter' ] = ob_get_clean();
		}
		if ( !empty( $_POST[ 'ajax_elements' ][ 'product_order' ] ) ) {
			ob_start();
			the_widget( 'product_sort_filter' );
			$return[ 'product_order' ] = ob_get_clean();
		}
		if ( !empty( $_POST[ 'ajax_elements' ][ 'product-sort-bar' ] ) ) {
			ob_start();
			show_product_sort_bar( $archive_template, $multiple_settings );
			$return[ 'product-sort-bar' ] = ob_get_clean();
		}
		$_SERVER[ 'REQUEST_URI' ]	 = $old_request_url;
		$echo						 = json_encode( $return );
		echo $echo;
	}
	wp_die();
}

add_action( 'register_catalog_styles', 'ic_product_ajax_register_styles' );

function ic_product_ajax_register_styles() {
	//wp_register_style( 'ic_variations', plugins_url( '/', __FILE__ ) . '/css/variations-front.css', array( 'al_product_styles' ) );
	wp_register_script( 'ic_product_ajax', AL_PLUGIN_BASE_PATH . 'js/product-ajax.js' );
}

add_action( 'enqueue_catalog_scripts', 'ic_product_ajax_enqueue_styles' );

function ic_product_ajax_enqueue_styles() {
	//wp_enqueue_style( 'ic_variations' );
	wp_enqueue_script( 'ic_product_ajax' );
	global $wp_query;
	$query_vars = $wp_query->query;
	if ( empty( $query_vars ) && is_home_archive() ) {
		$query_vars = array( 'post_type' => 'al_product' );
	}
	wp_localize_script( 'ic_product_ajax', 'ic_ajax', array(
		'query_vars'		 => json_encode( $query_vars ),
		'request_url'		 => remove_query_arg( array( 'page', 'paged' ), get_pagenum_link() ),
		'filters_reset_url'	 => get_filters_bar_reset_url(),
		'is_search'			 => is_search()
	) );
}

add_filter( 'product-list-attr', 'ic_ajax_shortcode_query_data', 10, 2 );

function ic_ajax_shortcode_query_data( $attr, $query ) {
	global $shortcode_query;
	if ( !empty( $shortcode_query->query ) ) {
		$attr .= " data-ic_ajax_query='" . json_encode( $shortcode_query->query ) . "'";
	}
	return $attr;
}

function ic_ajax_pagenum_link( $link ) {
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		global $wp_rewrite;
		parse_str( str_replace( '?', '', strstr( $link, '?' ) ), $params );
		$pagenum = (int) $params[ 'paged' ];

		$request = remove_query_arg( array( 'paged' ) );

		$home_root	 = parse_url( home_url() );
		$home_root	 = ( isset( $home_root[ 'path' ] ) ) ? $home_root[ 'path' ] : '';
		$home_root	 = preg_quote( $home_root, '|' );

		$request = preg_replace( '|^' . $home_root . '|i', '', $request );
		$request = preg_replace( '|^/+|', '', $request );

		if ( !$wp_rewrite->using_permalinks() || (is_admin() && !(defined( 'DOING_AJAX' ) && DOING_AJAX)) ) {
			$base = trailingslashit( get_bloginfo( 'url' ) );

			if ( $pagenum > 1 ) {
				$result = add_query_arg( 'paged', $pagenum, $base . $request );
			} else {
				$result = $base . $request;
			}
		} else {
			$qs_regex = '|\?.*?$|';
			preg_match( $qs_regex, $request, $qs_match );

			if ( !empty( $qs_match[ 0 ] ) ) {
				$query_string	 = $qs_match[ 0 ];
				$request		 = preg_replace( $qs_regex, '', $request );
			} else {
				$query_string = '';
			}

			$request = preg_replace( "|$wp_rewrite->pagination_base/\d+/?$|", '', $request );
			$request = preg_replace( '|^' . preg_quote( $wp_rewrite->index, '|' ) . '|i', '', $request );
			$request = ltrim( $request, '/' );

			$base = trailingslashit( get_bloginfo( 'url' ) );

			if ( $wp_rewrite->using_index_permalinks() && ( $pagenum > 1 || '' != $request ) )
				$base .= $wp_rewrite->index . '/';

			if ( $pagenum > 1 ) {
				$request = ( (!empty( $request ) ) ? trailingslashit( $request ) : $request ) . user_trailingslashit( $wp_rewrite->pagination_base . "/" . $pagenum, 'paged' );
			}

			$result = $base . $request . $query_string;
		}

		return $result;
	}
	return $link;
}