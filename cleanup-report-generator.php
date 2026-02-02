<?php
/**
 * Plugin Name: Cleanup Report Generator (Merged v3 – Review Workflow, Fixed Media)
 * Description: Create branded, color-coded cleanup reports with WPMU DEV styling, multiple screenshots, WYSIWYG editors, and professional PDF export. Includes review workflow and enhanced media management.
 * Version: 2.0.0
 * Author: WPMU DEV
 * License: GPLv2 or later
 * Text Domain: cleanup-report-generator
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Load Composer autoloader if available
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

define( 'CRG_VERSION', '2.0.0' );
define( 'CRG_PATH', plugin_dir_path( __FILE__ ) );
define( 'CRG_URL', plugin_dir_url( __FILE__ ) );

// Include the PDF classes
require_once CRG_PATH . 'includes/class-crg-pdf.php';
// Main plugin class will include the other classes
require_once CRG_PATH . 'includes/class-crg-plugin.php';

// Initialize the plugin
Cleanup_Report_Generator::init();
