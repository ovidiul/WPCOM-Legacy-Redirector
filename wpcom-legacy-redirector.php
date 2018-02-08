<?php
/**
 * Plugin Name: WPCOM Legacy Redirector
 * Plugin URI: https://vip.wordpress.com/plugins/wpcom-legacy-redirector/
 * Description: Simple plugin for handling legacy redirects in a scalable manner.
 * Version: 1.2.0
 * Requires PHP: 5.6
 * Author: Automattic / WordPress.com VIP
 * Author URI: https://vip.wordpress.com
 *
 * This is a no-frills plugin (no UI, for example). Data entry needs to be bulk-loaded via the wp-cli commands provided or custom scripts.
 *
 * Redirects are stored as a custom post type and use the following fields:
 *
 * - post_name for the md5 hash of the "from" path or URL.
 *  - we use this column, since it's indexed and queries are super fast.
 *  - we also use an md5 just to simplify the storage.
 * - post_title to store the non-md5 version of the "from" path.
 * - one of either:
 *  - post_parent if we're redirect to a post; or
 *  - post_excerpt if we're redirecting to an alternate URL.
 *
 * Please contact us before using this plugin.
 */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require( __DIR__ . '/includes/wp-cli.php' );
}

class WPCOM_Legacy_Redirector {
	const POST_TYPE = 'vip-legacy-redirect';
	const CACHE_GROUP = 'vip-legacy-redirect-2';

	static function start() {
		add_action( 'init', array( __CLASS__, 'init' ) );
		add_filter( 'template_redirect', array( __CLASS__, 'maybe_do_redirect' ), 0 ); // hook in early, before the canonical redirect
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_post' ) );
		add_filter( 'post_updated_messages', array( __CLASS__, 'updated_messages' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'custom_columns' ), 10, 1 );
	}
	
	static function add_meta_boxes() {
		add_meta_box(
			'redirect-details',
			esc_html__( 'Redirect Details', 'vip-legacy-redirect' ),
			array( __CLASS__, 'add_meta_box_callback' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	static function add_meta_box_callback( $post ) {
		wp_nonce_field( 'redirect_details_data', 'redirect_details_nonce' );

		echo '<p><label class="screen-reader-text" for="post-parent-id">Redirect Post ID</label>';
		echo '<input class="post-parent-id" size="4" name="post-parent-id" id="post-parent-id" type="number" value="' . absint( $post->post_parent ) . '" /></p>';
		echo '<p class="howto" id="post-parent-id-desc">If a non-zero Post ID is entered, it will redirect to that post.</p>';
		echo '<p><label class="screen-reader-text" for="redirect-url">Redirect URL</label>';
		echo '<input class="regular-text" name="redirect-url" id="redirect-url" type="text" value="' . esc_url( $post->post_excerpt ) . '" /></p>';
		echo '<p class="howto" id="redirect-url-desc">If no Post ID is entered, it will redirect to this URL.</p>';
		echo '<input class="regular-text" disabled=disabled type="text" id="redirect-hash" value="' . esc_attr( $post->post_name ) . '" /></p>';
		echo '<p class="howto" id="redirect-hash">Redirect Hash (Read Only)</p>';

	}

	static function save_post( $post_id ) {
		remove_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_post' ) );
		if ( ! isset( $_POST['redirect_details_nonce'] ) ) {
			return false;
		}

		if ( ! wp_verify_nonce( $_POST['redirect_details_nonce'], 'redirect_details_data' ) ) {
			return false;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}
		$from_url = sanitize_text_field( $_POST['post_title'] );
		$redirect_to = (bool) $_POST['post-parent-id'] ? (int) $_POST['post-parent-id'] : esc_url_raw( $_POST['redirect-url'] );
		self::insert_legacy_redirect( $from_url, $redirect_to, $post_id );
	}

	static function init() {
		$args = array(
			'public' => false,
		);

		if ( current_user_can( apply_filters( 'wpcom_legeacy_redirector_show_ui_capability', 'manage_options' ) ) ) {
			$labels = array(
				'name' => esc_html_x( 'Legacy Redirects', 'Legacy Redirects General Name', 'wpcom-legacy-redirector' ),
				'singular_name' => esc_html_x( 'Legacy Redirects', 'Legacy Redirects Singular Name', 'wpcom-legacy-redirector' ),
				'menu_name' => esc_html__( 'Legacy Redirects', 'wpcom-legacy-redirector' ),
				'name_admin_bar' => esc_html__( 'Legacy Redirects', 'wpcom-legacy-redirector' ),
				'archives' => esc_html__( 'Redirect Archives', 'wpcom-legacy-redirector' ),
				'attributes' => esc_html__( 'Redirect Attributes', 'wpcom-legacy-redirector' ),
				'parent_item_colon' => esc_html__( 'Parent Redirect:', 'wpcom-legacy-redirector' ),
				'all_items' => esc_html__( 'All Redirects', 'wpcom-legacy-redirector' ),
				'add_new_item' => esc_html__( 'Add New Redirect', 'wpcom-legacy-redirector' ),
				'add_new' => esc_html__( 'Add New', 'wpcom-legacy-redirector' ),
				'new_item' => esc_html__( 'New Redirect', 'wpcom-legacy-redirector' ),
				'edit_item' => esc_html__( 'Edit Redirect', 'wpcom-legacy-redirector' ),
				'update_item' => esc_html__( 'Update Redirect', 'wpcom-legacy-redirector' ),
				'view_item' => esc_html__( 'View Redirect', 'wpcom-legacy-redirector' ),
				'view_items' => esc_html__( 'View Redirects', 'wpcom-legacy-redirector' ),
				'search_items' => esc_html__( 'Search Redirect', 'wpcom-legacy-redirector' ),
				'not_found' => esc_html__( 'Not found', 'wpcom-legacy-redirector' ),
				'not_found_in_trash' => esc_html__( 'Not found in Trash', 'wpcom-legacy-redirector' ),
				'featured_image' => esc_html__( 'Featured Image', 'wpcom-legacy-redirector' ),
				'set_featured_image' => esc_html__( 'Set featured image', 'wpcom-legacy-redirector' ),
				'remove_featured_image' => esc_html__( 'Remove featured image', 'wpcom-legacy-redirector' ),
				'use_featured_image' => esc_html__( 'Use as featured image', 'wpcom-legacy-redirector' ),
				'insert_into_item' => esc_html__( 'Insert into redirect', 'wpcom-legacy-redirector' ),
				'uploaded_to_this_item' => esc_html__( 'Uploaded to this redirect', 'wpcom-legacy-redirector' ),
				'items_list' => esc_html__( 'Redirects list', 'wpcom-legacy-redirector' ),
				'items_list_navigation' => esc_html__( 'Redirects list navigation', 'wpcom-legacy-redirector' ),
				'filter_items_list' => esc_html__( 'Filter redirect list', 'wpcom-legacy-redirector' ),
			);
			$args = array_merge( $args, array(
				'show_ui' => true,
				'show_in_admin_bar' => false,
				'menu_icon' => 'dashicons-randomize',
				'capability_type' => array( 'legacy_redirect', 'legacy_redirects' ),
				'label' => esc_html__( 'Legacy Redirects', 'wpcom-legacy-redirector' ),
				'labels' => $labels,
				'supports' => array(
					'title',
				),
				'rewrite' => false,
				'query_var' => false,
				'delete_with_user' => false,
				'show_in_rest' => false,
			) );
		}
		register_post_type( self::POST_TYPE, $args );
	}

	static function custom_columns( $columns ) {
		// Rename the title.
		if ( 'Title' === $columns['title'] ) {
			$columns['title'] = 'Legacy Path';
		}

		// We don't need to show Likes.
		if ( isset( $columns['likes'] ) ) {
			unset( $columns['likes'] );
		}

		return $columns;
	}

	static function maybe_do_redirect() {
		// Avoid the overhead of running this on every single pageload.
		// We move the overhead to the 404 page but the trade-off for site performance is worth it.
		if ( ! is_404() ) {
			return;
		}

		$url = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

		if ( ! empty( $_SERVER['QUERY_STRING'] ) )
			$url .= '?' . $_SERVER['QUERY_STRING'];

		$request_path = apply_filters( 'wpcom_legacy_redirector_request_path', $url );

		if ( $request_path ) {
			$redirect_uri = self::get_redirect_uri( $request_path );
			if ( $redirect_uri ) {
				header( 'X-legacy-redirect: HIT' );
				$redirect_status = apply_filters( 'wpcom_legacy_redirector_redirect_status', 301, $url );
				wp_safe_redirect( $redirect_uri, $redirect_status );
				exit;
			}
		}
	}

	/**
 	 *
 	 * @param string $from_url URL or path that should be redirected; should have leading slash if path.
 	 * @param int|string $redirect_to The post ID or URL to redirect to.
 	 * @return bool|WP_Error Error if invalid redirect URL specified or if the URI already has a rule; false if not is_admin, true otherwise.
 	 */
	static function insert_legacy_redirect( $from_url, $redirect_to, $post_id = 0 ) {

		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! is_admin() && ! apply_filters( 'wpcom_legacy_redirector_allow_insert', false ) ) {
			// never run on the front end
			return false;
		}

		$from_url = self::normalise_url( $from_url );
		if ( is_wp_error( $from_url ) ) {
			return $from_url;
		}

		$from_url_hash = self::get_url_hash( $from_url );

		if ( false !== self::get_redirect_uri( $from_url ) ) {
			return new WP_Error( 'duplicate-redirect-uri', 'A redirect for this URI already exists' );
		}

		$args = array(
			'ID' => $post_id,
			'post_name' => $from_url_hash,
			'post_status' => 'draft',
			'post_title' => $from_url,
			'post_type' => self::POST_TYPE,
		);

		if ( is_numeric( $redirect_to ) ) {
			$args['post_parent'] = $redirect_to;
		} elseif ( false !== parse_url( $redirect_to ) ) {
			$args['post_excerpt'] = esc_url_raw( $redirect_to );
		} else {
			return new WP_Error( 'invalid-redirect-url', 'Invalid redirect_to param; should be a post_id or a URL' );
		}

		wp_insert_post( $args );

		wp_cache_delete( $from_url_hash, self::CACHE_GROUP );

		return true;
	}

	static function get_redirect_uri( $url ) {

		$url = self::normalise_url( $url );
		if ( is_wp_error( $url ) ) {
			return false;
		}

		// White list of Params that should be pass through as is.
		$protected_params = apply_filters( 'wpcom_legacy_redirector_preserve_query_params', array(), $url );
		$protected_param_values = array();
		$param_values = array();

		// Parse URL to get Query Params.
		$query_params = wp_parse_url( $url, PHP_URL_QUERY );
		if ( ! empty( $query_params ) ) { // Verify Query Params exist.

			// Parse Query String to Associated Array.
			parse_str( $query_params, $param_values );
			// For every white listed param save value and strip from url
			foreach ( $protected_params as $protected_param ) {
				if ( ! empty( $param_values[ $protected_param ] ) ) {
					$protected_param_values[ $protected_param ] = $param_values[ $protected_param ];
					$url = remove_query_arg( $protected_param, $url );
				}
			}
		}

		$url_hash = self::get_url_hash( $url );

		$redirect_post_id = wp_cache_get( $url_hash, self::CACHE_GROUP );

		if ( false === $redirect_post_id ) {
			$redirect_post_id = self::get_redirect_post_id( $url );
			wp_cache_add( $url_hash, $redirect_post_id, self::CACHE_GROUP );
		}

		if ( $redirect_post_id ) {
			$redirect_post = get_post( $redirect_post_id );
			if ( ! $redirect_post instanceof WP_Post ) {
				// If redirect post object doesn't exist, reset cache
				wp_cache_set( $url_hash, 0, self::CACHE_GROUP );

				return false;
			} elseif ( 0 !== $redirect_post->post_parent ) {
				return add_query_arg( $protected_param_values, get_permalink( $redirect_post->post_parent ) ); // Add Whitelisted Params to the Redirect URL.
			} elseif ( ! empty( $redirect_post->post_excerpt ) ) {
				return add_query_arg( $protected_param_values, esc_url_raw( $redirect_post->post_excerpt ) ); // Add Whitelisted Params to the Redirect URL
			}
		}

		return false;
	}

	static function get_redirect_post_id( $url ) {
		global $wpdb;

		$url_hash = self::get_url_hash( $url );

		$redirect_post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_name = %s LIMIT 1", self::POST_TYPE, $url_hash ) );

		if ( ! $redirect_post_id ) {
			$redirect_post_id = 0;
		}

		return $redirect_post_id;
	}

	private static function get_url_hash( $url ) {
		return md5( $url );
	}

	/**
	 * Takes a request URL and "normalises" it, stripping common elements
	 *
	 * Removes scheme and host from the URL, as redirects should be independent of these.
	 *
	 * @param string $url URL to transform
	 *
	 * @return string $url Transformed URL
	 */
	private static function normalise_url( $url ) {

		// Sanitise the URL first rather than trying to normalise a non-URL
		$url = esc_url_raw( $url );
		if ( empty( $url ) ) {
			return new WP_Error( 'invalid-redirect-url', 'The URL does not validate' );
		}

		// Break up the URL into it's constituent parts
		$components = wp_parse_url( $url );

		// Avoid playing with unexpected data
		if ( ! is_array( $components ) ) {
			return new WP_Error( 'url-parse-failed', 'The URL could not be parsed' );
		}

		// We should have at least a path or query
		if ( ! isset( $components['path'] ) && ! isset( $components['query'] ) ) {
			return new WP_Error( 'url-parse-failed', 'The URL contains neither a path nor query string' );
		}

		// Make sure $components['query'] is set, to avoid errors
		$components['query'] = ( isset( $components['query'] ) ) ? $components['query'] : '';

		// All we want is path and query strings
		// Note this strips hashes (#) too
		// @todo should we destory the query strings and rebuild with `add_query_arg()`?
		$normalised_url = $components['path'];

		// Only append '?' and the query if there is one
		if ( ! empty( $components['query'] ) ) {
			$normalised_url = $components['path'] . '?' . $components['query'];
		}

		return $normalised_url;

	}

	static function updated_messages( $messages ) {
		$post = get_post();

		$messages[ self::POST_TYPE ] = array(
			1  => 'Legacy Redirect updated.',
			4  => 'Legacy Redirect updated.',
			5  => isset( $_GET['revision'] ) ? sprintf( 'Legacy Redirect restored to revision from %s',wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6  => 'Legacy Redirect published.',
			7  => 'Legacy Redirect saved.',
			8  => 'Legacy Redirect submitted.',
			9  => sprintf(
				'Legacy Redirect scheduled for: <strong>%1$s</strong>.',
				date_i18n( 'M j, Y @ G:i', strtotime( $post->post_date ) )
			),
			10 => 'Legacy Redirect draft updated.'
		);

		return $messages;
	}
}

WPCOM_Legacy_Redirector::start();
