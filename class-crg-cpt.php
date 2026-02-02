<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CRG_CPT {
    public static function register() {
        register_post_type('cleanup_report', [
            'labels' => [
                'name' => 'Reports',
                'singular_name' => 'Report',
                'add_new_item' => 'Add New Report',
                'edit_item' => 'Edit Report',
                'view_item' => 'View Report',
            ],
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-media-document',
            'supports' => ['title'],
        ]);
    }
}
