<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Cleanup_Report_CPT {
    public static function register() {
        register_post_type( 'cleanup_report', [
            'labels'    => [
                'name'               => 'Reports',
                'singular_name'      => 'Report',
                'menu_name'          => 'Reports',
                'all_items'          => 'All reports',
                'add_new'            => 'Add report',
                'add_new_item'       => 'Add report',
                'edit_item'          => 'Edit report',
                'new_item'           => 'New report',
                'view_item'          => 'View report',
                'search_items'       => 'Search reports',
                'not_found'          => 'No reports yet. Click "Add report" to write the first one.',
                'not_found_in_trash' => 'No reports in the bin.',
            ],
            'public'    => false,
            'show_ui'   => true,
            'menu_icon' => 'dashicons-shield',
            'supports'  => [ 'title' ],
        ] );
    }
}
