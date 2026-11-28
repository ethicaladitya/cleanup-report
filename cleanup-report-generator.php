<?php
/**
 * Plugin Name: Handover – Service Reports for Agencies
 * Description: Branded service reports for your clients: malware cleanup and website optimization reports, written in wp-admin, approved through a review workflow and exported as PDF.
 * Version: 2.2.0
 * Author: WPMU DEV
 * License: GPLv2 or later
 * Text Domain: cleanup-report-generator
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.2
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Dompdf and its libraries, copied under this plugin's own namespace (CleanupReportGenerator\Vendor\) by
// `composer install` (Strauss), so another plugin bundling a different Dompdf version can't clash with ours.
if ( file_exists( __DIR__ . '/vendor-prefixed/autoload.php' ) ) {
    require_once __DIR__ . '/vendor-prefixed/autoload.php';
}

define( 'CLEANUP_REPORT_VERSION', '2.2.0' );
define( 'CLEANUP_REPORT_PATH', plugin_dir_path( __FILE__ ) );
define( 'CLEANUP_REPORT_URL', plugin_dir_url( __FILE__ ) );

// The main class; it registers an autoloader for the others.
require_once CLEANUP_REPORT_PATH . 'includes/class-cleanup-report-plugin.php';

// Initialize the plugin
Cleanup_Report_Generator::init();

// Activation: the plugin list's daily re-check (when it's turned on) is scheduled again.
register_activation_hook( __FILE__, [ 'Cleanup_Report_Plugin_Registry', 'schedule' ] );
// The plugin list's daily refresh (WP-Cron) stops when the plugin is deactivated.
register_deactivation_hook( __FILE__, [ 'Cleanup_Report_Plugin_Registry', 'unschedule' ] );
// So does the daily retention cleanup (Settings › Review › Data retention).
register_deactivation_hook( __FILE__, [ 'Cleanup_Report_Retention', 'unschedule' ] );
