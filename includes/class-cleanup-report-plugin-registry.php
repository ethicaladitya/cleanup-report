<?php
/**
 * The shared plugin list (Reports › Settings › Plugin list): every plugin and theme seen by "Check plugins & themes"
 * on any report, with whether it's still maintained.
 *
 *  - WordPress.org items: status found automatically (closed, no release for 2 years, "Tested up to" 3+ WordPress
 *    versions behind); refreshed daily by WP-Cron when Settings › Review › "Re-check the plugin list every day" is on.
 *  - Everything else (premium, custom, or a folder name WordPress.org uses for something else): staff mark it
 *    Maintained or Abandoned once; the check then uses that decision on every report.
 *
 * Stored in one non-autoloaded option, keyed "plugin:slug" / "theme:slug" (slug lowercased).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Cleanup_Report_Plugin_Registry {

    const OPTION        = 'crg_plugin_registry';
    const LEGACY_OPTION = 'crg_premium_items'; // the hand-kept premium list (2.1 development); merged in once
    const CRON_HOOK     = 'crg_refresh_plugin_list';
    const REFRESH_DAYS  = 7;   // re-check a WordPress.org item once a week
    const REFRESH_BATCH = 120; // items per daily run
    const MAX_REPORTS   = 25;  // report IDs remembered per item

    public static function init() {
        add_action( self::CRON_HOOK, [ __CLASS__, 'refresh' ] );
        add_action( 'admin_init', [ __CLASS__, 'schedule' ] );
        add_action( 'admin_post_crg_save_plugin_list', [ __CLASS__, 'handle_save' ] );
        add_action( 'admin_post_crg_refresh_plugin_list', [ __CLASS__, 'handle_refresh' ] );
        add_action( 'admin_post_crg_export_plugin_list', [ __CLASS__, 'handle_export' ] );
    }

    /** The daily re-check runs only when an administrator turned it on (Settings › Review › Outside services). */
    public static function schedule() {
        $next = wp_next_scheduled( self::CRON_HOOK );
        if ( get_option( 'crg_plugin_recheck', 0 ) ) {
            if ( ! $next ) {
                wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
            }
        } elseif ( $next ) {
            self::unschedule();
        }
    }

    public static function unschedule() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    public static function key( $type, $slug ) {
        return ( 'theme' === $type ? 'theme' : 'plugin' ) . ':' . strtolower( $slug );
    }

    private static function blank( $type, $slug, $name = '' ) {
        return [
            'type'         => 'theme' === $type ? 'theme' : 'plugin',
            'slug'         => $slug,
            'name'         => $name ?: $slug,
            'source'       => 'unknown', // wporg | unknown
            'status'       => 'unknown', // wporg items: ok | abandoned | closed
            'reason'       => '',
            'latest'       => '',
            'last_release' => '',
            'open_vulns'   => 0,         // vulnerabilities with no fix in any version
            'decision'     => '',        // staff: maintained | abandoned ('' = not decided)
            'vendor'       => '',
            'note'         => '',
            'reports'      => [],
            'last_seen'    => '',
            'checked'      => 0,         // last automatic check (timestamp)
        ];
    }

    /** Common premium products, so they don't all land in "Needs a decision" on day one. */
    private static function seeds() {
        $p = static function ( $slug, $name, $vendor, $type = 'plugin' ) {
            return [ 'slug' => $slug, 'name' => $name, 'type' => $type, 'vendor' => $vendor, 'status' => 'maintained', 'note' => '' ];
        };
        return [
            $p( 'wpmudev-updates', 'WPMU DEV Dashboard', 'https://wpmudev.com/' ),
            $p( 'wp-defender', 'Defender Pro', 'https://wpmudev.com/project/wp-defender/' ),
            $p( 'wp-smush-pro', 'Smush Pro', 'https://wpmudev.com/project/wp-smush-pro/' ),
            $p( 'wp-hummingbird', 'Hummingbird Pro', 'https://wpmudev.com/project/wp-hummingbird/' ),
            $p( 'wpmu-dev-seo', 'SmartCrawl Pro', 'https://wpmudev.com/project/smartcrawl-wordpress-seo/' ),
            $p( 'the-hub-client', 'The Hub Client', 'https://wpmudev.com/' ),
            $p( 'elementor-pro', 'Elementor Pro', 'https://elementor.com/' ),
            $p( 'advanced-custom-fields-pro', 'ACF PRO', 'https://www.advancedcustomfields.com/' ),
            $p( 'gravityforms', 'Gravity Forms', 'https://www.gravityforms.com/' ),
            $p( 'wp-rocket', 'WP Rocket', 'https://wp-rocket.me/' ),
            $p( 'js_composer', 'WPBakery Page Builder', 'https://wpbakery.com/' ),
            $p( 'revslider', 'Slider Revolution', 'https://www.sliderrevolution.com/' ),
            $p( 'LayerSlider', 'LayerSlider', 'https://layerslider.com/' ),
            $p( 'Divi', 'Divi', 'https://www.elegantthemes.com/', 'theme' ),
            $p( 'Avada', 'Avada', 'https://avada.com/', 'theme' ),
            $p( 'betheme', 'Betheme', 'https://muffingroup.com/betheme/', 'theme' ),
            $p( 'bricks', 'Bricks', 'https://bricksbuilder.io/', 'theme' ),
        ];
    }

    /** The whole list, keyed. Created on first use from the seeds (and any old hand-kept premium list). */
    public static function all() {
        $list = get_option( self::OPTION, null );
        if ( is_array( $list ) ) {
            return $list;
        }
        $legacy = get_option( self::LEGACY_OPTION, null );
        $list   = [];
        foreach ( is_array( $legacy ) ? $legacy : self::seeds() as $p ) {
            $entry                = self::blank( $p['type'] ?? 'plugin', $p['slug'], $p['name'] ?? '' );
            $entry['decision']    = 'abandoned' === ( $p['status'] ?? '' ) ? 'abandoned' : 'maintained';
            $entry['vendor']      = $p['vendor'] ?? '';
            $entry['note']        = $p['note'] ?? '';
            $list[ self::key( $entry['type'], $entry['slug'] ) ] = $entry;
        }
        self::save( $list );
        delete_option( self::LEGACY_OPTION );
        return $list;
    }

    private static function save( array $list ) {
        uasort( $list, static function ( $a, $b ) { return strcasecmp( $a['name'], $b['name'] ); } );
        update_option( self::OPTION, $list, false );
    }

    /** Items staff have decided on (the check uses these instead of WordPress.org), keyed. */
    public static function decisions() {
        return array_filter( self::all(), static function ( $e ) { return '' !== $e['decision']; } );
    }

    /** Effective status shown in the list: the staff decision if any, else the automatic one. */
    public static function status( array $e ) {
        if ( '' !== $e['decision'] ) {
            return $e['decision'];
        }
        if ( 'wporg' !== $e['source'] ) {
            return 'undecided';
        }
        return [ 'ok' => 'maintained', 'abandoned' => 'abandoned', 'closed' => 'closed' ][ $e['status'] ] ?? 'undecided';
    }

    /** Add a report's check results to the list (Cleanup_Report_Plugin_Check::check() rows). */
    public static function record( array $rows, $post_id ) {
        $list = self::all();
        foreach ( $rows as $r ) {
            $key = self::key( $r['type'], $r['slug'] );
            $e   = $list[ $key ] ?? self::blank( $r['type'], $r['slug'], $r['name'] );
            if ( '' === $e['decision'] && empty( $r['errors'] ) ) {
                $e['source']       = 'wporg' === $r['source'] ? 'wporg' : 'unknown';
                $e['status']       = 'wporg' === $r['source'] ? ( in_array( $r['status'], [ 'closed', 'abandoned' ], true ) ? $r['status'] : 'ok' ) : 'unknown';
                $e['reason']       = in_array( $r['status'], [ 'closed', 'abandoned' ], true ) ? (string) ( $r['reasons'][0] ?? '' ) : '';
                $e['latest']       = 'wporg' === $r['source'] ? (string) $r['latest'] : $e['latest'];
                $e['last_release'] = (string) ( $r['last_release'] ?? $e['last_release'] );
                $e['checked']      = time();
            }
            if ( $r['name'] && $r['name'] !== $r['slug'] ) {
                $e['name'] = $r['name'];
            }
            $e['open_vulns'] = (int) ( $r['vulns']['open'] ?? $e['open_vulns'] );
            if ( $post_id && ! in_array( $post_id, $e['reports'], true ) ) {
                $e['reports'] = array_slice( array_merge( [ $post_id ], $e['reports'] ), 0, self::MAX_REPORTS );
            }
            $e['last_seen'] = current_time( 'Y-m-d' );
            $list[ $key ]   = $e;
        }
        self::save( $list );
    }

    /**
     * Daily (WP-Cron) or on demand: re-check WordPress.org items not checked for REFRESH_DAYS, and undecided ones
     * (a plugin can be added to WordPress.org later). Returns how many were checked.
     */
    /**
     * @param bool $force   Re-check every undecided item now ("Re-check WordPress.org now").
     * @param bool $by_user Run because someone just did something (not WP-Cron), even when the daily re-check is off.
     */
    public static function refresh( $force = false, $by_user = false ) {
        if ( ! $force && ! $by_user && ! get_option( 'crg_plugin_recheck', 0 ) ) {
            return; // the daily run was turned off since it was scheduled
        }
        $list  = self::all();
        $stale = array_filter( $list, static function ( $e ) use ( $force ) {
            return '' === $e['decision'] && ( $force || $e['checked'] < time() - self::REFRESH_DAYS * DAY_IN_SECONDS );
        } );
        uasort( $stale, static function ( $a, $b ) { return $a['checked'] <=> $b['checked']; } );
        $stale = array_slice( $stale, 0, self::REFRESH_BATCH, true );
        if ( ! $stale ) {
            return 0;
        }
        $urls = [];
        foreach ( $stale as $e ) {
            $urls[] = Cleanup_Report_Plugin_Check::org_url( $e['type'], $e['slug'] );
            $urls[] = Cleanup_Report_Plugin_Check::vuln_url( $e['type'], $e['slug'] );
        }
        $data = Cleanup_Report_Plugin_Check::fetch( $urls );
        $wp   = Cleanup_Report_Plugin_Check::current_wp();
        foreach ( $stale as $key => $e ) {
            $m = Cleanup_Report_Plugin_Check::maintenance( [ 'type' => $e['type'], 'slug' => $e['slug'], 'title' => $e['name'] !== $e['slug'] ? $e['name'] : '' ], $data[ Cleanup_Report_Plugin_Check::org_url( $e['type'], $e['slug'] ) ] ?? null, $wp );
            if ( $m['error'] ) {
                continue; // try again next run
            }
            $e['source']       = $m['source'];
            $e['status']       = $m['status'];
            $e['reason']       = in_array( $m['status'], [ 'closed', 'abandoned' ], true ) ? $m['reason'] : '';
            $e['latest']       = $m['latest'];
            $e['last_release'] = $m['last_release'];
            $vdata             = $data[ Cleanup_Report_Plugin_Check::vuln_url( $e['type'], $e['slug'] ) ] ?? null;
            if ( null !== $vdata ) {
                $e['open_vulns'] = Cleanup_Report_Plugin_Check::open_vulns( $vdata );
            }
            $e['checked'] = time();
            $list[ $key ] = $e;
        }
        self::save( $list );
        return count( $stale );
    }

    /* ------------------------------------------------------------------ admin-post handlers */

    /** Capability check (admins and Buddyleads); each handler verifies its own nonce first (check_admin_referer). */
    private static function guard() {
        if ( ! Cleanup_Report_Access::can_manage_plugin_list() ) {
            wp_die( 'You are not allowed to change these settings.', 'Forbidden', [ 'response' => 403, 'back_link' => true ] );
        }
    }

    private static function back( $args = [] ) {
        $filter = sanitize_key( wp_unslash( $_POST['filter'] ?? $_GET['filter'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- checked by the calling handler
        wp_safe_redirect( add_query_arg( $args + ( $filter ? [ 'filter' => $filter ] : [] ), Cleanup_Report_Settings::url( 'plugins' ) ) );
        exit;
    }

    /** Save staff decisions, notes and vendor links; add an item by hand; delete items. */
    public static function handle_save() {
        check_admin_referer( 'crg_save_plugin_list' );
        self::guard();
        $list   = self::all();
        $posted = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field sanitised below
        foreach ( $posted as $key => $fields ) {
            $key = sanitize_text_field( $key );
            if ( ! isset( $list[ $key ] ) || ! is_array( $fields ) ) {
                continue;
            }
            if ( ! empty( $fields['delete'] ) ) {
                unset( $list[ $key ] );
                continue;
            }
            $decision                   = sanitize_key( $fields['decision'] ?? '' );
            $list[ $key ]['decision']   = in_array( $decision, [ 'maintained', 'abandoned' ], true ) ? $decision : '';
            $list[ $key ]['vendor']     = esc_url_raw( $fields['vendor'] ?? '' );
            $list[ $key ]['note']       = sanitize_text_field( $fields['note'] ?? '' );
            if ( '' === $list[ $key ]['decision'] ) {
                $list[ $key ]['checked'] = 0; // back to automatic: re-check on the next refresh
            }
        }
        $new  = isset( $_POST['new'] ) && is_array( $_POST['new'] ) ? wp_unslash( $_POST['new'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised below
        $slug = preg_replace( '/[^A-Za-z0-9_.\-]/', '', (string) ( $new['slug'] ?? '' ) );
        if ( '' !== $slug ) {
            $type = 'theme' === ( $new['type'] ?? '' ) ? 'theme' : 'plugin';
            $key  = self::key( $type, $slug );
            $e    = $list[ $key ] ?? self::blank( $type, $slug, sanitize_text_field( $new['name'] ?? '' ) );
            $dec  = sanitize_key( $new['decision'] ?? '' );
            $e['decision'] = in_array( $dec, [ 'maintained', 'abandoned' ], true ) ? $dec : $e['decision'];
            $e['vendor']   = esc_url_raw( $new['vendor'] ?? '' ) ?: $e['vendor'];
            $list[ $key ]  = $e;
        }
        self::save( $list );
        if ( '' !== $slug ) {
            self::refresh( false, true ); // a new undecided item gets looked up straight away
        }
        self::back( [ 'saved' => 1 ] );
    }

    public static function handle_refresh() {
        check_admin_referer( 'crg_refresh_plugin_list' );
        self::guard();
        $n = self::refresh( true );
        self::back( [ 'refreshed' => $n ] );
    }

    /** The list as CSV, for sharing or a spreadsheet. */
    public static function handle_export() {
        check_admin_referer( 'crg_export_plugin_list' );
        self::guard();
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=plugin-list-' . gmdate( 'Y-m-d' ) . '.csv' );
        $out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming the download
        // A cell starting with = + - @ (or a tab/CR) runs as a formula in Excel and Sheets; names come from pasted lists.
        $cell = static function ( $v ) { $v = (string) $v; return '' !== $v && false !== strpos( "=+-@\t\r", $v[0] ) ? "'" . $v : $v; };
        fputcsv( $out, [ 'Type', 'Folder', 'Name', 'Status', 'Why', 'Latest version', 'Last release', 'Unfixed vulnerabilities', 'Decided by staff', 'Vendor', 'Note', 'Seen in reports', 'Last seen' ], ',', '"', '\\' );
        foreach ( self::all() as $e ) {
            fputcsv( $out, array_map( $cell, [ $e['type'], $e['slug'], $e['name'], self::status( $e ), $e['reason'], $e['latest'], $e['last_release'], $e['open_vulns'], $e['decision'] ? 'yes' : 'no', $e['vendor'], $e['note'], count( $e['reports'] ), $e['last_seen'] ] ), ',', '"', '\\' );
        }
        fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        exit;
    }
}
