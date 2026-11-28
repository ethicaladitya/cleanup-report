<?php
/**
 * Deleting the plugin (Plugins › Delete). Nothing is removed unless an administrator ticked "Delete all reports and
 * settings when the plugin is deleted" (Settings › Review). Then every report, setting, transient, scheduled event
 * and the plugin's cache folders (uploads/crg-cache, and Dompdf's fonts in the temp folder) go, on each site of a
 * network. Screenshots stay in the Media
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
    // This plugin's options, by name (another plugin may use names starting with crg_ too).
    $options = [
        'crg_db_version', 'crg_defaults', 'crg_brand_name', 'crg_brand_logo', 'crg_brand_logo_light', 'crg_brand_primary',
        'crg_report_theme', 'crg_cover_glow', 'crg_footer_text', 'crg_watermark_text', 'crg_pdf_requires_approval',
        'crg_buddyleads', 'crg_retention_days', 'crg_retention_last_run', 'crg_plugin_recheck', 'crg_delete_data',
        'crg_followup_items', 'crg_defender_items', 'crg_blocklists', 'crg_opt_checks', 'crg_opt_target_mobile',
        'crg_opt_target_desktop', 'crg_plugin_registry', 'crg_premium_items', 'crg_post_status_synced', 'crg_scanner_line_replaced',
    ];
    foreach ( [ 'success', 'warning', 'high', 'danger', 'neutral' ] as $color ) {
        $options[] = 'crg_color_' . $color;
    }
    foreach ( $options as $name ) {
        delete_option( $name );
    }
    delete_transient( 'crg_upgrading' );
    delete_transient( 'crg_retention_running' );
    // Cached lookups: transients named crg_pc_<hash> (plugin check) and crg_logo_<hash> (logo trimming).
    $names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_crg_pc_' ) . '%', $wpdb->esc_like( '_transient_crg_logo_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time cleanup on uninstall; the transients are then deleted through the API
    foreach ( $names as $name ) {
        delete_transient( substr( $name, strlen( '_transient_' ) ) );
    }
    // Trimmed logos and Dompdf's font cache (in uploads, or the temp folder when uploads isn't writable).
    $uploads = wp_get_upload_dir();
    $folders = array_filter( [ ! empty( $uploads['basedir'] ) ? trailingslashit( $uploads['basedir'] ) . 'crg-cache' : '', trailingslashit( get_temp_dir() ) . 'crg-fonts' ] );
    require_once ABSPATH . 'wp-admin/includes/file.php';
    if ( WP_Filesystem() ) {
        global $wp_filesystem;
        foreach ( $folders as $folder ) {
            if ( is_dir( $folder ) ) {
                $wp_filesystem->rmdir( $folder, true );
            }
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
