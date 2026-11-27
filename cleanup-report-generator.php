<?php
/**
 * Plugin Name: Cleanup Report Generator
 * Description: Create branded, color-coded cleanup reports with WPMU DEV styling, multiple screenshots, WYSIWYG editors, and professional PDF export. Includes review workflow and enhanced media management.
 * Version: 2.1.0
 * Author: WPMU DEV
 * License: GPLv2 or later
 * Text Domain: cleanup-report-generator
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Dompdf and its libraries, copied under this plugin's own namespace (CleanupReportGenerator\Vendor\) by
// `composer install` (Strauss), so another plugin bundling a different Dompdf version can't clash with ours.
if ( file_exists( __DIR__ . '/vendor-prefixed/autoload.php' ) ) {
    require_once __DIR__ . '/vendor-prefixed/autoload.php';
}

define( 'CLEANUP_REPORT_VERSION', '2.1.0' );
define( 'CLEANUP_REPORT_PATH', plugin_dir_path( __FILE__ ) );
define( 'CLEANUP_REPORT_URL', plugin_dir_url( __FILE__ ) );

// Include the PDF classes
require_once CLEANUP_REPORT_PATH . 'includes/class-cleanup-report-pdf.php';
// Main plugin class will include the other classes
require_once CLEANUP_REPORT_PATH . 'includes/class-cleanup-report-plugin.php';

// Initialize the plugin
Cleanup_Report_Generator::init();

// The plugin list's daily refresh (WP-Cron) stops when the plugin is deactivated.
register_deactivation_hook( __FILE__, [ 'Cleanup_Report_Plugin_Registry', 'unschedule' ] );
// So does the daily retention cleanup (Settings › Review › Data retention).
register_deactivation_hook( __FILE__, [ 'Cleanup_Report_Retention', 'unschedule' ] );
