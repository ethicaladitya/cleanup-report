<?php
/**
 * Data retention (Reports › Settings › Review, administrators only): reports nobody has edited for the set number of
 * days are deleted permanently by a daily WP-Cron job, together with their history and their screenshots in the Media
 * Library. A screenshot another report (or the branding) still uses is kept. 0 days keeps reports forever.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Cleanup_Report_Retention {

    const OPTION       = 'crg_retention_days';
    const DEFAULT_DAYS = 30;
    const MAX_DAYS     = 3650;
    const LAST_RUN     = 'crg_retention_last_run'; // [ time, reports, files ] of the latest cleanup; not autoloaded
    const CRON_HOOK    = 'crg_retention_cleanup';
    const BATCH        = 25; // reports per run; a follow-up run is queued while more are due

    public static function init() {
        add_action( self::CRON_HOOK, [ __CLASS__, 'run' ] ); // daily run: no argument; a follow-up run passes true
        add_action( 'admin_init', [ __CLASS__, 'schedule' ] ); // wp-admin only: no cron lookup on front-end pages
    }

    public static function schedule() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
        }
    }

    public static function unschedule() {
        wp_unschedule_hook( self::CRON_HOOK ); // the daily run and any queued follow-up (scheduled with an argument)
    }

    /** Whole days, 0 (keep forever) to MAX_DAYS; the default when unset or not a number. */
    public static function sanitize_days( $value ) {
        return is_numeric( $value ) ? max( 0, min( self::MAX_DAYS, (int) $value ) ) : self::DEFAULT_DAYS;
    }

    public static function days() {
        return self::sanitize_days( get_option( self::OPTION, self::DEFAULT_DAYS ) );
    }

    /** Reports (any status, including the Trash) not edited for days() days, oldest first. */
    private static function query( $limit ) {
        $days = self::days();
        if ( ! $days ) {
            return null;
        }
        return new WP_Query( [
            'post_type'              => 'cleanup_report',
            'post_status'            => array_values( array_diff( get_post_stati(), [ 'auto-draft', 'inherit' ] ) ),
            // post_modified (site time), not post_modified_gmt: WordPress leaves the GMT dates at 0000-00-00 on a draft
            // that was never saved again, which would make every new draft look decades old.
            'date_query'             => [ [ 'column' => 'post_modified', 'before' => wp_date( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ] ],
            'orderby'                => 'modified',
            'order'                  => 'ASC',
            'fields'                 => 'ids',
            'posts_per_page'         => $limit,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ] );
    }

    /** How many reports the next cleanup would delete. */
    public static function due_count() {
        $q = self::query( 1 );
        return $q ? (int) $q->found_posts : 0;
    }

    /** @param bool $follow_up True for the run queued because the previous one had more due: its counts add up. */
    public static function run( $follow_up = false ) {
        if ( get_transient( 'crg_retention_running' ) ) {
            return;
        }
        set_transient( 'crg_retention_running', 1, 10 * MINUTE_IN_SECONDS );
        $q       = self::query( self::BATCH );
        $ids     = $q ? array_map( 'intval', $q->posts ) : [];
        $reports = 0;
        $files   = 0;
        foreach ( $ids as $id ) {
            $shots = self::screenshots( $id );
            if ( ! wp_delete_post( $id, true ) ) {
                continue;
            }
            $reports++;
            foreach ( $shots as $attachment_id => $url ) {
                if ( ! self::in_use( $url ) && wp_delete_attachment( $attachment_id, true ) ) {
                    $files++;
                }
            }
        }
        if ( $q && $q->found_posts > count( $ids ) ) {
            wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, self::CRON_HOOK, [ true ] ); // more are due: carry on shortly
        }
        $last = get_option( self::LAST_RUN );
        if ( $follow_up && is_array( $last ) ) {
            $reports += (int) ( $last['reports'] ?? 0 );
            $files   += (int) ( $last['files'] ?? 0 );
        }
        update_option( self::LAST_RUN, [ 'time' => time(), 'reports' => $reports, 'files' => $files ], false );
        delete_transient( 'crg_retention_running' );
    }

    /** Screenshot lists of every report type (meta keys of their screenshots fields). */
    private static function shot_keys() {
        $keys = [ 'crg_screenshots' ];
        foreach ( array_keys( Cleanup_Report_Types::definitions() ) as $type ) {
            foreach ( Cleanup_Report_Types::get( $type )['sections'] as $section ) {
                foreach ( $section['fields'] as $f ) {
                    if ( 'screenshots' === ( $f['type'] ?? '' ) && ! empty( $f['key'] ) ) {
                        $keys[] = $f['key'];
                    }
                }
            }
        }
        return array_values( array_unique( $keys ) );
    }

    /**
     * The report's own screenshots: Media Library files uploaded to this report (their parent is the report) and used
     * in it, attachment ID => URL. A file picked from the library that belongs to something else is never included,
     * so the cleanup can't delete media it doesn't own. Read before the report is deleted (deleting it detaches them).
     */
    private static function screenshots( $post_id ) {
        $urls = [];
        foreach ( self::shot_keys() as $key ) {
            $shots = get_post_meta( $post_id, $key, true );
            $urls  = array_merge( $urls, is_array( $shots ) ? wp_list_pluck( array_filter( $shots, 'is_array' ), 'url' ) : [] );
        }
        $urls[] = get_post_meta( $post_id, 'crg_ss_before', true ); // reports from 1.x
        $urls[] = get_post_meta( $post_id, 'crg_ss_after', true );
        $out    = [];
        foreach ( array_filter( array_map( 'strval', $urls ) ) as $url ) {
            $id = attachment_url_to_postid( $url );
            if ( $id && (int) wp_get_post_parent_id( $id ) === (int) $post_id ) {
                $out[ $id ] = $url;
            }
        }
        return $out;
    }

    /** Whether another report or the branding still uses this file (checked after the report itself is deleted). */
    private static function in_use( $url ) {
        if ( in_array( $url, [ get_option( 'crg_brand_logo', '' ), get_option( 'crg_brand_logo_light', '' ) ], true ) ) {
            return true;
        }
        $q = new WP_Query( [
            'post_type'      => 'cleanup_report',
            'post_status'    => array_values( array_diff( get_post_stati(), [ 'inherit' ] ) ),
            'fields'         => 'ids',
            'posts_per_page' => 1,
            'no_found_rows'  => true,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- daily cron, once per deleted screenshot
            'meta_query'     => array_merge(
                [ 'relation' => 'OR' ],
                array_map( static function ( $key ) use ( $url ) { return [ 'key' => $key, 'value' => $url, 'compare' => 'LIKE' ]; }, self::shot_keys() ),
                [ [ 'key' => 'crg_ss_before', 'value' => $url ], [ 'key' => 'crg_ss_after', 'value' => $url ] ]
            ),
        ] );
        return (bool) $q->posts;
    }
}
