<?php

// Do not allow inserts to be enabled on the frontend on wpcom
add_filter( 'wpcom_legacy_redirector_allow_insert', '__return_false', 9999 );

// Gives the ability to short circuit legacy redirects for debugging.
function vip_debug_legacy_redirects( $status, $url ) {
	if ( is_automattician() && isset( $_COOKIE['wpcom-legacy-redirector'] ) && 'brisket' === $_COOKIE['wpcom-legacy-redirector'] ) {
		$post_id = WPCOM_Legacy_Redirector::get_redirect_post_id( $url );
		$edit_url = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
		$edit_link = sprintf( '<a href="%s">Edit %s</a>', esc_url( $edit_url ), esc_html( $post_id ) );
		$request_path = apply_filters( 'wpcom_legacy_redirector_request_path', $url );

		wp_die( sprintf( 'WPCOM Legacy Redirector Debugging:<br/><br/><code>%s -> %s, %d</code><br/><br/>%s',
			esc_html( $request_path ),
			esc_html( WPCOM_Legacy_Redirector::get_redirect_uri( $request_path ) ),
			(int) $status,
			wp_kses_post( $edit_link )
		) );
	}
	return $status;
}
add_filter( 'wpcom_legacy_redirector_redirect_status', 'vip_debug_legacy_redirects', 10, 2 );
