<?php

class WPCOM_Legacy_Redirector_UI {
	/**
	 * Constructor Class.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'validate_redirects_notices' ), 10, 2 );
		add_action( 'manage_vip-legacy-redirect_posts_custom_column' , array( $this, 'custom_vip_legacy_redirect_column' ), 10, 2 );
		add_action( 'load-edit.php', array( $this, 'default_show_excerpt_wp_table' ) );
		add_action( 'after_setup_theme', array( $this, 'validate_vip_legacy_redirect' ), 10, 2 );

		add_filter( 'manage_vip-legacy-redirect_posts_columns', array( $this, 'set_vip_legacy_redirects_columns' ) );
		add_filter( 'post_row_actions', array( $this, 'modify_list_row_actions' ), 10, 2 );
		add_filter( 'removable_query_args', array( $this, 'add_removable_arg' ) );
		add_filter( 'views_edit-vip-legacy-redirect', array( $this, 'vip_redirects_custom_post_status_filters' ) );

	}
	/**
	 * Add Submenu Page.
	 */
	public function admin_menu() {
		add_submenu_page(
			'edit.php?post_type=vip-legacy-redirect',
			__( 'Add Redirect', 'wpcom-legacy-redirector' ),
			__( 'Add Redirect', 'wpcom-legacy-redirector' ),
			'manage_redirects',
			'wpcom-legacy-redirector',
			array( $this, 'generate_page_html' )
		);
	}
	/**
	 * Set the $args that can be removed for validation purposes.
	 *
	 * @param array $args The Args: coming to a theatre near you.
	 */
	public function add_removable_arg( $args ) {
		array_push( $args, 'validate', 'ids' );
		return $args;
	}
	/**
	 * Always show UI with Excerpts.
	 */
	public function default_show_excerpt_wp_table() {
		$post_type = isset( $_GET['post_type'] ) ? $_GET['post_type'] : '';
	}
	/**
	 * Notices for the redirect validation.
	 */
	public function validate_redirects_notices() {
		$redirect_not_valid_text = __( 'Redirect is not valid', 'wpcom-legacy-redirector' );
		if ( isset( $_GET['validate']) ) {
			switch ( $_GET['validate'] ) {
				case 'invalid':
					echo '<div id="message" class="error notice is-dismissible"><p>' . esc_html( $redirect_not_valid_text ) . '<br />' . esc_html__( 'If you are doing an external redirect, make sure you whitelist the domain using the "allowed_redirect_hosts" filter.', 'wpcom-legacy-redirector' ) . '</p></div>';
					break;
				case '404':
					echo '<div id="message" class="error notice is-dismissible"><p>' . esc_html( $redirect_not_valid_text ) . '<br />' . esc_html__( 'Redirect is pointing to a page with the HTTP status of 404.', 'wpcom-legacy-redirector' ) . '</p></div>';
					break;
				case 'valid':
					echo '<div id="message" class="updated notice is-dismissible"><p>' . esc_html__( 'Redirect Valid.', 'wpcom-legacy-redirector' ) . '</p></div>';
					break;
				case 'private':
					echo '<div id="message" class="error notice is-dismissible"><p>' . esc_html( $redirect_not_valid_text ) . '<br />' . esc_html__( 'The redirect is pointing to content that is not publiclly accessible.', 'wpcom-legacy-redirector' ) . '</p></div>';
					break;
				case 'null':
					echo '<div id="message" class="error notice is-dismissible"><p>' . esc_html( $redirect_not_valid_text ) . '<br />' . esc_html__( 'The redirect is pointing to a Post ID that does not exist.', 'wpcom-legacy-redirector' ) . '</p></div>';
			}
		}
	}
	/**
	 * Set Columns for Redirect Table.
	 *
	 * @param array $columns Columns to show for the post type.
	 */
	public function set_vip_legacy_redirects_columns( $columns ) {
		return array(
			'cb' => '<input type="checkbox" />',
			'from' => __( 'Redirect From' ),
			'to' => __( 'Redirect To' ),
			'date' => __( 'Date' ),
		);
	}
	/**
	 * Remove "draft" from the status filters for vip-legacy-redirect post type.
	 */
	public function vip_redirects_custom_post_status_filters( $views ) {
		unset( $views['draft'] );
		return $views;
	}
	/**
	 * Run checks for the Post Parent ID of the redirect.
	 *
	 * @param object $post The Post.
	 */
	public function vip_legacy_redirect_parent_id( $post ) {
		$post = get_post( $post->ID );
		$parent = get_post( $post->post_parent );
		$parent_post_status = get_post_status( $parent );

		if ( is_null( get_post( $post->post_parent ) ) ) {
			return 'null';
		} elseif ( 'publish' !== get_post_status( $parent ) ) {
			return 'private';
		} else {
			$parent_slug = $parent->post_name;
			return $parent_slug;
		}
	}
	/**
	 * Check if $redirect is a public Post.
	 */
	public function vip_legacy_redirect_check_if_public( $excerpt ) {

		$redirect = home_url() . $excerpt;
		$post_types = get_post_types();

		if ( function_exists( 'wpcom_vip_get_page_by_path' ) ) {
			$post_obj = wpcom_vip_get_page_by_path( $excerpt, OBJECT, $post_types );
		} else {
			$post_obj = get_page_by_path( $excerpt, OBJECT, $post_types );
		}
		if ( ! is_null( $post_obj ) ) {
			if ( 'publish' !== get_post_status( $post_obj->ID ) ) {
				return 'private';
			}
		}
	}
	/**
	 * Add the data to the custom columns for the vip-legacy-redirects post type.
	 * Provide warnings for possibly bad redirects.
	 *
	 * @param string $column The Column for the post_type table.
	 * @param int $post_id  The Post ID.
	 */
	public function custom_vip_legacy_redirect_column( $column, $post_id ) {
		switch ( $column ) {
			case 'from':
				echo esc_html( get_the_title( $post_id ) );
				break;
			case 'to':
				$post = get_post( $post_id );
				$post_types = get_post_types();
				$excerpt = get_the_excerpt( $post_id );

				// Check if the Post is Published.
				if ( ! empty( $excerpt ) ) {
					// Check if it's the Home URL
					if ( '/' === $excerpt || home_url() === $excerpt ) {
						echo esc_html( $excerpt );
					} elseif ( 0 === strpos( $excerpt, 'http' ) ) {
						echo esc_url_raw( $excerpt );
					} else {
						if ( 'private' === $this->vip_legacy_redirect_check_if_public( $excerpt ) ) {
							echo esc_html( $excerpt ) . '<br /><em>' . esc_html__( 'Warning: Redirect is not a public URL.', 'wpcom-legacy-redirector' ) . '</em>';
						} else {
							echo esc_html( $excerpt );
						}
					}
				} else {
					if ( 'null' === $this->vip_legacy_redirect_parent_id( $post ) ) {
						echo '<em>' . esc_html__( 'Redirect is pointing to a Post ID that does not exist.', 'wpcom-legacy-redirector' ) . '</em>';
					} elseif ( 'private' === $this->vip_legacy_redirect_parent_id( $post ) ) {
						echo ( esc_html( get_permalink( $parent ) ) . '<br /><em>' . esc_html__( 'Warning: Redirect is not a public URL.', 'wpcom-legacy-redirector' ) . '</em>' );
					} else {
						echo esc_html( '/' . $this->vip_legacy_redirect_parent_id( $post ) );
					}
				}
				break;
		}
	}
	/**
	 * Modify the Row Actions for the vip-legacy-redirect post type.
	 *
	 * @param array $actions Default Actions.
	 * @param object $post the current Post.
	 */
	public function modify_list_row_actions( $actions, $post ) {
		// Check for your post type.
		if ( 'vip-legacy-redirect' === $post->post_type ) {

			$url = admin_url( 'post.php?post=vip-legacy-redirect&post=' . $post->ID );

			if ( isset( $_GET['post_status'] ) && 'trash' === $_GET['post_status'] ) {
				return $actions;
			}
			$trash = $actions['trash'];
			$actions = array();

			if ( current_user_can( 'manage_redirects' ) ) {
				// Add a nonce to Validate Link
				$validate_link = wp_nonce_url( add_query_arg( array(
					'action' => 'validate',
				), $url ), 'validate_vip_legacy_redirect', '_validate_redirect' );

				// Add the Validate Link
				$actions = array_merge( $actions, array(
					'validate' => sprintf( '<a href="%1$s">%2$s</a>',
						esc_url( $validate_link ), 
						'Validate'
					),
				) );
				// Re-insert thrash link preserved from the default $actions.
				$actions['trash'] = $trash;
			}
		}
		return $actions;
	}
	/**
	 * Check if URL is a 404.
	 *
	 * @param string $url The URL.
	 */
	public function check_if_404( $url ) {

		if ( function_exists( 'vip_safe_wp_remote_get' ) ) {
			$response = vip_safe_wp_remote_get( $url );
		} else {
			$response = wp_remote_get( $url );
		}
		$response_code = '';
		if ( is_array( $response ) ) {
			$response_code = wp_remote_retrieve_response_code( $response );
		}
		return $response_code;
	}
	/**
	 * Validate the Redirect To URL.
	 */
	public function validate_vip_legacy_redirect() {

		$sendback = remove_query_arg( array( 'validate', 'ids' ),  wp_get_referer() );
		if ( isset( $_GET['action'] ) && 'validate' === $_GET['action'] ) {
			$nonce = $_REQUEST['_validate_redirect'];
			$post = get_post( $_GET['post'] );
			if ( ! isset( $_REQUEST['_validate_redirect'] ) || ! wp_verify_nonce( $_REQUEST['_validate_redirect'], 'validate_vip_legacy_redirect' ) ) {
				return;
			} else {
				// Check if the Redirect is stored in the Excerpt
				// Check if the Redirect is stored in the Excerpt instead of in the Post Parent as an ID.
				// Excerpts can be both external or internal redirects.
				if ( has_excerpt( $post->ID ) ) {
					$post_types = get_post_types();
					$excerpt = get_the_excerpt( $post->ID );

					// Check if redirect is a full URL or not.
					if ( 0 === strpos( $excerpt, 'http' ) ) {
						$redirect = $excerpt;
					} elseif ( '/' === $excerpt ) {
						$redirect = 'valid';
					} else {
						$redirect = $this->vip_legacy_redirect_check_if_public( $excerpt );
					}
				} else {
					// If it's not stored as an Excerpt, it will be stored as a post_parent ID.
					// Post Parent IDs are always internal redirects.
					$redirect = $this->vip_legacy_redirect_parent_id( $post );
				}
				$status = $this->check_if_404( $redirect );

				// Check if $redirect is invalid.
				if ( ! wp_validate_redirect( $redirect, false ) ) {
					wp_safe_redirect( add_query_arg(
						array(
							'validate'  => 'invalid',
							'ids'       => $post->ID,
						), $sendback
					) );
					exit();
				}
				// Check if $redirect is a 404.
				if ( 404 === $status ) {
					wp_safe_redirect( add_query_arg(
						array(
							'validate'  => '404',
							'ids'       => $post->ID,
						), $sendback
					) );
					exit();
				}
				// Check if $redirect is not publicly visible.
				if ( 'private' === $redirect ) {
					wp_safe_redirect( add_query_arg(
						array(
							'validate'  => 'private',
							'ids'       => $post->ID,
						), $sendback
					) );
					exit();
				}
				// Check if $redirect is pointing to a null Post ID.
				if ( 'null' === $redirect ) {
					wp_safe_redirect( add_query_arg(
						array(
							'validate'  => 'null',
							'ids'       => $post->ID,
						), $sendback
					) );
					exit();
				}
				// Check if $redirect is valid.
				if ( wp_validate_redirect( $redirect, false ) && 404 !== $status || 'valid' === $redirect ) {
					wp_safe_redirect( add_query_arg(
						array(
							'validate'  => 'valid',
							'ids'       => $post->ID,
						), $sendback
					) );
					exit();
				}
			}
		}
	}
	public function generate_page_html() {
		if ( ! current_user_can( 'manage_redirects' ) ) {
			return;
		}

		$errors   = array();
		$messages = array();
		if ( class_exists( 'WPCOM_Legacy_Redirector' ) ) {
			if ( isset( $_POST['from_url'] ) && isset( $_POST['redirect_to'] ) ) {
				if (
					! isset( $_POST['redirect_nonce_field'] )
					|| ! wp_verify_nonce( $_POST['redirect_nonce_field'] )
				) {
					$errors[] = array(
						'label'   => __( 'Error', 'wpcom-legacy-redirector' ),
						'message' => __( 'Sorry, your nonce did not verify.', 'wpcom-legacy-redirector' ),
					);
				} else {
					$from_url	= sanitize_text_field( $_POST['from_url'] );
					$redirect_to = sanitize_text_field( $_POST['redirect_to'] );

					// Check if $from_url is not a 404.  Only 404 links are redirected in this plugin.
					$status = $this->check_if_404( home_url() . $from_url );
					if ( 404 !== $status ) {
						$from_url = '';
					}

					if ( WPCOM_Legacy_Redirector::validate( $from_url, $redirect_to ) ) {
						$output = WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $redirect_to, false );
						if ( true === $output ) {
							$link	   = '<a href="' . esc_url( $from_url ) . '" target="_blank">' . esc_url( $from_url ) . '</a>';
							$messages[] = __( 'The redirect was added successfully. Check Redirect: ', 'wpcom-legacy-redirector' ) . $link;
						} else {
							if ( false === $output ) {
								$errors[] = array(
									'label'   => __( 'Error', 'wpcom-legacy-redirector' ),
									'message' => __( 'Redirect could not be saved. Contact administrator and check permissions.', 'wpcom-legacy-redirector' ),
								);
							} elseif ( is_wp_error( $output ) ) {
								foreach ( $output->get_error_messages() as $error ) {
									$errors[] = array(
										'label'   => __( 'Error', 'wpcom-legacy-redirector' ),
										'message' => $error,
									);
								}
							}
						}
					} else {
						if ( $from_url === '' ) {
							$errors[] = array(
								'label'   => __( 'Error', 'wpcom-legacy-redirector' ),
								'message' => __( 'Redirects need to be from URLs that have a 404 status.', 'wpcom-legacy-redirector' ),
							);
							$output = false;
						} else {
							$errors[] = array(
								'label'   => __( 'Error', 'wpcom-legacy-redirector' ),
								'message' => __( 'Check the values you are using to save the redirect. All fields are required. "Redirect From" and "Redirect To" should not match.', 'wpcom-legacy-redirector' ),
							);
						}
					}
				}
			}
		} else {
			$errors[] = array(
				'label'   => __( 'Error', 'wpcom-legacy-redirector' ),
				'message' => __( 'WPCOM Legacy Redirector plugin is required to add redirects.', 'wpcom-legacy-redirector' ),
			);
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Add Redirect', 'wpcom-legacy-redirector' ); ?></h1>
			<?php if ( count( $messages ) ) : ?>
				<div class="notice notice-success">
					<?php foreach ( $messages as $message ) : ?>
						<p><?php echo wp_kses_post( $message ); ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( count( $errors ) ) : ?>
				<div class="notice notice-error">
					<?php foreach ( $errors as $error ) : ?>
						<p><strong><?php echo esc_html( $error['label'] ); ?></strong>: <?php echo esc_html( $error['message'] ); ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

            <form method="post">
                <?php wp_nonce_field( - 1, 'redirect_nonce_field' ); ?>

                <table class="form-table">
                    <tbody>
                    <tr>
                        <th>
                            <label for="from_url"><?php esc_html_e( 'Redirect From', 'wpcom-legacy-redirector' ); ?></label>
                        </th>
                        <td>
                            <input name="from_url" type="text" id="from_url" value="" class="regular-text">
                            <p class="description"><?php esc_html_e( 'This path should be relative to the root, e.g. "/hello".', 'wpcom-legacy-redirector' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="redirect_to"><?php esc_html_e( 'Redirect To', 'wpcom-legacy-redirector' ); ?></label>
                        </th>
                        <td>
                            <input name="redirect_to" type="text" id="redirect_to" value="" class="regular-text">
                            <p class="description"><?php esc_html_e( 'To redirect to a post you can use the post_id, e.g. "100".', '' ); ?></p>
                        </td>
                    </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary"
                           value="<?php esc_attr_e( 'Add Redirect', 'wpcom-legacy-redirector' ); ?>">
                </p>
            </form>

        </div>
        <?php
    }
}
