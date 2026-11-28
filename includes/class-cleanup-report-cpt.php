<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Cleanup_Report_CPT {
    public static function register() {
        register_post_type( 'cleanup_report', [
            'labels'    => [
                'name'               => __( 'Reports', 'cleanup-report-generator' ),
                'singular_name'      => __( 'Report', 'cleanup-report-generator' ),
                'menu_name'          => __( 'Reports', 'cleanup-report-generator' ),
                'all_items'          => __( 'All reports', 'cleanup-report-generator' ),
                'add_new'            => __( 'Add report', 'cleanup-report-generator' ),
                'add_new_item'       => __( 'Add report', 'cleanup-report-generator' ),
                'edit_item'          => __( 'Edit report', 'cleanup-report-generator' ),
                'new_item'           => __( 'New report', 'cleanup-report-generator' ),
                'view_item'          => __( 'View report', 'cleanup-report-generator' ),
                'search_items'       => __( 'Search reports', 'cleanup-report-generator' ),
                'not_found'          => __( 'No reports yet. Click "Add report" to write the first one.', 'cleanup-report-generator' ),
                'not_found_in_trash' => __( 'No reports in the bin.', 'cleanup-report-generator' ),
            ],
            'public'    => false,
            'show_ui'   => true,
            'menu_icon' => 'dashicons-shield',
            'supports'  => [ 'title' ],
        ] );
    }
}
