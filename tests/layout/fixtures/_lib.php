<?php
/**
 * Shared helpers for the layout fixtures. Loaded by each fixture under `wp eval-file`.
 */
defined( 'WP_CLI' ) || exit;

/**
 * Find the report with this title (wiping its meta so the fixture starts clean), or create it.
 */
function crg_fixture_report( $title ) {
	$found = get_posts( [ 'post_type' => 'cleanup_report', 'title' => $title, 'post_status' => 'any', 'numberposts' => 1 ] );
	if ( $found ) {
		foreach ( array_keys( get_post_meta( $found[0]->ID ) ) as $key ) {
			delete_post_meta( $found[0]->ID, $key );
		}
		return $found[0]->ID;
	}
	return wp_insert_post( [ 'post_type' => 'cleanup_report', 'post_status' => 'publish', 'post_title' => $title ] );
}

/**
 * URL of the media item named $name, uploading $dir/$name.png into the media library first if it isn't there.
 */
function crg_fixture_image( $name, $dir ) {
	$found = get_posts( [ 'post_type' => 'attachment', 'title' => $name, 'numberposts' => 1 ] );
	if ( $found ) {
		return wp_get_attachment_url( $found[0]->ID );
	}
	$src = rtrim( $dir, '/' ) . '/' . $name . '.png';
	if ( ! is_readable( $src ) ) {
		WP_CLI::error( "Fixture image missing: $src" );
	}
	$upload = wp_upload_bits( $name . '.png', null, file_get_contents( $src ) );
	if ( ! empty( $upload['error'] ) ) {
		WP_CLI::error( $upload['error'] );
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$id = wp_insert_attachment( [ 'post_title' => $name, 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ], $upload['file'] );
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
	return wp_get_attachment_url( $id );
}

/**
 * Save the fixture's meta and print the report ID (read by run.sh).
 */
function crg_fixture_save( $id, array $meta ) {
	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}
	wp_update_post( [ 'ID' => $id ] ); // WordPress's status follows the review status set above
	echo $id;
}
