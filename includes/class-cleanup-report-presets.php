<?php
/**
 * Defaults preset (Settings › Branding › Defaults): what the plugin starts with when nothing is saved yet.
 *
 *  - WPMU DEV: the support team's defaults. Brand "WPMU DEV Services", the Defender checklist and the Automate
 *    follow-up on, the Hub team member check, the Hub in backup notes, WPMU DEV products in the standard texts.
 *  - Generic: neutral defaults for any agency. The site's name as the brand, no product names, Defender and Automate
 *    items off (they can be switched on), no Hub check.
 *
 * Existing installs are set to WPMU DEV by the upgrade, so nothing they see changes; new installs start Generic.
 * Saved settings always win over the preset: it only fills in what was never set.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Cleanup_Report_Presets {

    const OPTION = 'crg_defaults';

    /** key => label (Settings › Branding). */
    public static function choices() {
        return [
            'generic' => __( 'Generic', 'cleanup-report-generator' ),
            'wpmudev' => __( 'WPMU DEV', 'cleanup-report-generator' ),
        ];
    }

    public static function current() {
        $preset = get_option( self::OPTION, 'generic' );
        return isset( self::choices()[ $preset ] ) ? $preset : 'generic';
    }

    public static function is( $preset ) {
        return self::current() === $preset;
    }

    public static function sanitize( $value ) {
        return isset( self::choices()[ $value ] ) ? $value : 'generic';
    }

    /**
     * Upgrade step: an install that already had the plugin (a saved version, brand or report) keeps the WPMU DEV
     * defaults it has always had; a new one starts Generic. Runs once (the option is then set).
     */
    public static function choose_on_upgrade( $had_plugin ) {
        if ( false === get_option( self::OPTION ) ) {
            add_option( self::OPTION, $had_plugin ? 'wpmudev' : 'generic', '', false );
        }
    }

    /** One default for the current preset. */
    public static function get( $key ) {
        $wpmudev = self::is( 'wpmudev' );
        switch ( $key ) {
            case 'brand_name':
                return $wpmudev ? 'WPMU DEV Services' : ( wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) ?: __( 'Our team', 'cleanup-report-generator' ) );
            case 'footer':
                return '© {year} ' . ( $wpmudev ? 'WPMU DEV Services' : ( wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) ?: __( 'Our team', 'cleanup-report-generator' ) ) );
            case 'request_placeholder':
                return $wpmudev ? 'https://wpmudev.com/…' : 'https://…';
            case 'hosting_answers':
                // One stored value for "our own hosting" in both presets ('wpmudev', as 2.1 saved it), so switching the
                // preset only changes the label and never loses an answer.
                return [
                    'wpmudev'     => $wpmudev ? __( 'WPMU DEV hosting', 'cleanup-report-generator' ) : __( 'Our hosting', 'cleanup-report-generator' ),
                    'third_party' => __( 'Third-party hosting', 'cleanup-report-generator' ),
                ];
            case 'product_items': // the Defender checklist and the Automate follow-up start switched on
                return $wpmudev;
        }
        return null;
    }
}
