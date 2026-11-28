<?php
/**
 * Deleting the plugin (Plugins › Delete). Nothing is removed unless an administrator ticked "Delete all reports and
 * settings when the plugin is deleted" (Settings › Review). Then every report, setting, transient, scheduled event
 * and the plugin's cache folder (uploads/crg-cache) go, on each site of a network. Screenshots stay in the Media
 * Library: they're ordinary media and may be used elsewhere.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/** Removes this site's data if its administrators asked for it. */
function cleanup_report_uninstall_site() {
    if ( ! get_option( 'crg_delete_data' ) ) {
        return;
    }
    global $wpdb;
    $ids = get_posts( [ 'post_type' => 'cleanup_report', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true ] );
    $ids = array_merge( $ids, get_posts( [ 'post_type' => 'cleanup_report', 'post_status' => 'trash', 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true ] ) );
    foreach ( array_unique( $ids ) as $id ) {
        wp_delete_post( $id, true ); // with its meta (report fields, history) and revisions
    }
    foreach ( [ 'crg_refresh_plugin_list', 'crg_retention_cleanup' ] as $hook ) {
        wp_unschedule_hook( $hook );
    }
    // Every option and transient this plugin stores starts with crg_.
    $like  = $wpdb->esc_like( 'crg_' ) . '%';
    $names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s", $like, $wpdb->esc_like( '_transient_crg_' ) . '%', $wpdb->esc_like( '_transient_timeout_crg_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time cleanup on uninstall; options are listed, then deleted through the API
    foreach ( $names as $name ) {
        if ( 0 === strpos( $name, '_transient_timeout_' ) ) {
            continue;
        }
        if ( 0 === strpos( $name, '_transient_' ) ) {
            delete_transient( substr( $name, strlen( '_transient_' ) ) );
        } else {
            delete_option( $name );
        }
    }
    // Trimmed logos and Dompdf's font cache.
    $uploads = wp_get_upload_dir();
    $cache   = trailingslashit( $uploads['basedir'] ?? '' ) . 'crg-cache';
    if ( ! empty( $uploads['basedir'] ) && is_dir( $cache ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        if ( WP_Filesystem() ) {
            global $wp_filesystem;
            $wp_filesystem->rmdir( $cache, true );
        }
    }
}

if ( is_multisite() ) {
    foreach ( get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) as $cleanup_report_site ) {
        switch_to_blog( $cleanup_report_site );
        cleanup_report_uninstall_site();
        restore_current_blog();
    }
} else {
    cleanup_report_uninstall_site();
}
