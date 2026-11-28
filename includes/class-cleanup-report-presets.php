<?php
/**
 * Defaults preset (Settings › Branding › Defaults): what the plugin starts with when nothing is saved yet.
 *
 *  - Generic (every new install): neutral defaults for any agency. The site's name as the brand, no product names,
 *    the Defender and automatic-update items off (they can be switched on), no Hub check.
 *  - Defender & managed hosting (optional): for teams whose clients' sites run Defender, the Hub, Automate and
 *    Snapshot on their own hosting. The Defender checklist and the Automate follow-up on, the Hub team member check,
 *    the Hub in backup notes, those products in the standard texts. Its stored key is 'wpmudev' (kept from 2.1).
 *
 * The brand name and footer come from Settings › Branding, never from a preset. Installs that had the plugin before
 * 2.2.0 got this preset and their brand saved by the upgrade (Cleanup_Report_Admin::upgrade_site()), so nothing they
 * see changes. Saved settings always win over the preset: it only fills in what was never set.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Cleanup_Report_Presets {

    const OPTION = 'crg_defaults';

    /** key => label (Settings › Branding). */
    public static function choices() {
        return [
            'generic' => __( 'Generic', 'cleanup-report-generator' ),
            'wpmudev' => __( 'Defender & managed hosting', 'cleanup-report-generator' ),
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
     * Upgrade step: an install that already had the plugin (a saved version, brand or report) keeps the defaults it
     * has always had (this preset); a new one starts Generic. Runs once (the option is then set).
     */
    public static function choose_on_upgrade( $had_plugin ) {
        if ( false === get_option( self::OPTION ) ) {
            add_option( self::OPTION, $had_plugin ? 'wpmudev' : 'generic', '', true ); // autoloaded: every admin page reads it
        }
    }

    /**
     * Upgrade step: before 2.2.0 an install with no brand saved showed the support team's brand. It's now saved as
     * its setting, so those reports keep it while the presets no longer carry a brand. Only for installs on the
     * Defender & managed hosting preset (installs from before 2.2.0); never on a new install.
     */
    public static function keep_old_brand() {
        if ( ! self::is( 'wpmudev' ) ) {
            return;
        }
        $brand = 'WPMU DEV Services'; // what 2.1 showed by default; kept only for those installs
        if ( '' === (string) get_option( 'crg_brand_name', '' ) ) {
            update_option( 'crg_brand_name', $brand, false );
        }
        if ( false === get_option( 'crg_footer_text' ) ) {
            add_option( 'crg_footer_text', '© {year} ' . $brand, '', false );
        }
    }

    /** One default for the current preset. */
    public static function get( $key ) {
        $wpmudev = self::is( 'wpmudev' );
        switch ( $key ) {
            case 'brand_name': // the same in both presets: this site's name
                return wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) ?: __( 'Our team', 'cleanup-report-generator' );
            case 'footer':
                return '© {year} ' . self::get( 'brand_name' );
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
