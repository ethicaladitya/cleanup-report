<?php
/**
 * The report edit screen (meta boxes, in the same order as the report), saving, and the Reports list.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Cleanup_Report_Admin {

    /**
     * Review workflow. Anyone who can edit a report can draft it and submit it for review; only Buddyleads
     * (Reports › Settings › Review) can approve it or send it back; once approved, anyone can mark it completed.
     * The labels are shown translated through statuses().
     */
    const STATUSES = [
        'draft'          => 'Draft',
        'submitted'      => 'Submitted for review',
        'needs_revision' => 'Needs revision',
        'approved'       => 'Approved',
        'complete'       => 'Completed', // sent to the member; the task is done
    ];
    const BUDDYLEADS_OPTION = 'crg_buddyleads';

    /**
     * WordPress's own post status, kept in step with the review status (the Publish box is replaced by the Review
     * box): Drafts = with the writer, Pending = waiting for a Buddylead, Published = approved or completed.
     */
    const POST_STATUS = [
        'draft'          => 'draft',
        'submitted'      => 'pending',
        'needs_revision' => 'draft',
        'approved'       => 'publish',
        'complete'       => 'publish',
    ];

    /** "Report approved." etc. after a save that changed the review status (post_updated_messages indexes; shown translated through status_messages()). */
    const STATUS_MESSAGES = [
        'draft'          => [ 51, 'Report moved back to draft.' ],
        'submitted'      => [ 52, 'Report submitted for review.' ],
        'needs_revision' => [ 53, 'Report sent back for revision. The author will see your notes.' ],
        'approved'       => [ 54, 'Report approved. It can now be sent to the member.' ],
        'complete'       => [ 55, 'Report marked completed.' ],
    ];

    /** Bump to run upgrade() once more on every site (stored in the autoloaded option crg_db_version). */
    const DB_VERSION = 6;

    /** The review status this request changed the report to, for the "saved" message. */
    private static $changed_to = '';


    /** What each review step is called in the history (stored in English; translated on display by history_label()). */
    const HISTORY_STEPS = [
        'draft'          => 'Moved back to draft',
        'submitted'      => 'Submitted for review',
        'needs_revision' => 'Sent back for revision',
        'approved'       => 'Approved',
        'complete'       => 'Marked completed',
    ];

    /** STATUSES, translated: review status => label. */
    public static function statuses() {
        return [
            'draft'          => __( 'Draft', 'cleanup-report-generator' ),
            'submitted'      => __( 'Submitted for review', 'cleanup-report-generator' ),
            'needs_revision' => __( 'Needs revision', 'cleanup-report-generator' ),
            'approved'       => __( 'Approved', 'cleanup-report-generator' ),
            'complete'       => __( 'Completed', 'cleanup-report-generator' ),
        ];
    }

    /** STATUS_MESSAGES, translated: review status => [ post_updated_messages index, message ]. */
    public static function status_messages() {
        return [
            'draft'          => [ self::STATUS_MESSAGES['draft'][0], __( 'Report moved back to draft.', 'cleanup-report-generator' ) ],
            'submitted'      => [ self::STATUS_MESSAGES['submitted'][0], __( 'Report submitted for review.', 'cleanup-report-generator' ) ],
            'needs_revision' => [ self::STATUS_MESSAGES['needs_revision'][0], __( 'Report sent back for revision. The author will see your notes.', 'cleanup-report-generator' ) ],
            'approved'       => [ self::STATUS_MESSAGES['approved'][0], __( 'Report approved. It can now be sent to the member.', 'cleanup-report-generator' ) ],
            'complete'       => [ self::STATUS_MESSAGES['complete'][0], __( 'Report marked completed.', 'cleanup-report-generator' ) ],
        ];
    }

    /**
     * A history entry's action or section as stored (in English, e.g. "Submitted for review", "Title"), translated for
     * display. Section titles of the report types are shown as they were stored.
     */
    public static function history_label( $text ) {
        $labels = [
            'Created the report'     => __( 'Created the report', 'cleanup-report-generator' ),
            'Edited'                 => __( 'Edited', 'cleanup-report-generator' ),
            'Moved to Trash'         => __( 'Moved to Trash', 'cleanup-report-generator' ),
            'Restored from Trash'    => __( 'Restored from Trash', 'cleanup-report-generator' ),
            'Downloaded the PDF'     => __( 'Downloaded the PDF', 'cleanup-report-generator' ),
            'Moved back to draft'    => __( 'Moved back to draft', 'cleanup-report-generator' ),
            'Submitted for review'   => __( 'Submitted for review', 'cleanup-report-generator' ),
            'Resubmitted for review' => __( 'Resubmitted for review', 'cleanup-report-generator' ),
            'Sent back for revision' => __( 'Sent back for revision', 'cleanup-report-generator' ),
            'Approved'               => __( 'Approved', 'cleanup-report-generator' ),
            'Marked completed'       => __( 'Marked completed', 'cleanup-report-generator' ),
            'Title'                  => __( 'Title', 'cleanup-report-generator' ),
            'Notes'                  => __( 'Notes', 'cleanup-report-generator' ),
        ];
        return $labels[ (string) $text ] ?? (string) $text;
    }

    /** Set when this request renamed the report (post_updated runs before save_post). */
    private static $title_changed = false;

    /** Set when this request created the report: its first save fills in defaults, which aren't edits. */
    private static $created = false;

    /** The report type's edit-screen sections: box ID (crg_box_<section>) => title. */
    public static function sections( $post_id ) {
        $out = [];
        foreach ( Cleanup_Report_Types::get( Cleanup_Report_Types::of( $post_id ) )['sections'] as $key => $section ) {
            $out[ 'crg_box_' . $key ] = $section['title'];
        }
        return $out;
    }

    /** History groups of the report's type: section title => the meta its fields write (and the review notes). */
    private static function history_fields( $post_id ) {
        $out = [];
        foreach ( Cleanup_Report_Types::get( Cleanup_Report_Types::of( $post_id ) )['sections'] as $section ) {
            $keys = [];
            foreach ( $section['fields'] as $f ) {
                $keys = array_merge( $keys, Cleanup_Report_Fields::meta_keys( $f ) );
            }
            $out[ $section['title'] ] = $keys;
        }
        $out['Notes'] = [ 'crg_author_notes', 'crg_review_notes' ]; // stored in English in the history; translated on display
        return $out;
    }

    public static function init() {
        add_action( 'add_meta_boxes_cleanup_report', [ __CLASS__, 'register_meta_boxes' ] );
        add_action( 'save_post_cleanup_report', [ __CLASS__, 'save_meta' ] );
        add_action( 'edit_form_after_title', [ __CLASS__, 'section_nav' ] );
        add_filter( 'enter_title_here', [ __CLASS__, 'title_placeholder' ], 10, 2 );
        add_filter( 'manage_cleanup_report_posts_columns', [ __CLASS__, 'columns' ] );
        add_action( 'manage_cleanup_report_posts_custom_column', [ __CLASS__, 'column' ], 10, 2 );
        add_filter( 'manage_edit-cleanup_report_sortable_columns', [ __CLASS__, 'sortable_columns' ] );
        add_action( 'pre_get_posts', [ __CLASS__, 'sort_by_report_date' ] );
        add_filter( 'wp_insert_post_data', [ __CLASS__, 'sync_post_status' ], 10, 2 );
        add_filter( 'redirect_post_location', [ __CLASS__, 'saved_message' ], 10, 2 );
        add_filter( 'display_post_states', [ __CLASS__, 'post_states' ], 10, 2 );
        if ( (int) get_option( 'crg_db_version', 0 ) < self::DB_VERSION ) { // autoloaded: no query once up to date
            add_action( 'admin_init', [ __CLASS__, 'upgrade' ] );
            add_action( self::UPGRADE_HOOK, [ __CLASS__, 'upgrade_batch' ] ); // a large site continues in the background
        }
        add_action( 'added_option', [ __CLASS__, 'checklists_not_autoloaded' ] );
        add_action( 'current_screen', [ __CLASS__, 'prime_options' ] );
        // History.
        add_action( 'transition_post_status', [ __CLASS__, 'history_created' ], 10, 3 );
        add_action( 'post_updated', [ __CLASS__, 'history_title' ], 10, 3 );
        add_action( 'trashed_post', [ __CLASS__, 'history_trash' ] );
        add_action( 'untrashed_post', [ __CLASS__, 'history_trash' ] );
    }

    /**
     * One-time fixes for reports saved before 2.1.0, run on the first admin page an editor opens after updating:
     *  - WordPress's post status brought in line with the review status (POST_STATUS), without touching the
     *    modified date; later saves keep them in step through sync_post_status().
     *  - The old default Verification bullet that named a third-party scanner brand gets the current wording.
     *  - The two checklist settings stop loading on every front-end page (see checklists_not_autoloaded()).
     *  - A saved footer starting "© 2026" gets "© {year}", so it stays current (DB_VERSION 2).
     *  - Checklist items still named as before 2.1.0 take the report template's names, in the saved lists and in
     *    reports not yet approved (DB_VERSION 3; see Cleanup_Report_Checklists::rename_old_labels()).
     *  - Every report without a type is marked a Malware cleanup report, which it already reads as (DB_VERSION 4), so
     *    the Reports list's type filter is a plain meta query.
     *  - An install that already had the plugin keeps the WPMU DEV defaults and the daily plugin-list re-check it has
     *    always had; a new install starts with the Generic defaults, the re-check off and no automatic deletion
     *    (retention 0) (DB_VERSION 4). Settings only wp-admin needs stop being autoloaded (DB_VERSION 5).
     *  - The upgrade runs in batches of reports, and the type backfill is one query (DB_VERSION 6).
     * Every step is safe to repeat, so a version bump simply runs them all again once.
     */
    /** Reports handled per upgrade step; the rest follow on the next admin page or WP-Cron run. */
    const UPGRADE_BATCH  = 100;
    const UPGRADE_CURSOR = 'crg_upgrade_cursor'; // last report ID done while an upgrade is under way
    const UPGRADE_HOOK   = 'crg_upgrade_continue';

    /** Runs (or continues) the upgrade on an admin page of someone who can write reports. */
    public static function upgrade() {
        if ( current_user_can( 'edit_posts' ) ) {
            self::upgrade_batch();
        }
    }

    /**
     * One step of the upgrade: the site-wide fixes the first time, then the next UPGRADE_BATCH reports by ID. When a
     * step ends with more to do, the cursor is saved and a WP-Cron event carries on; crg_db_version is set only when
     * every report is done, so an interrupted upgrade simply continues.
     */
    public static function upgrade_batch() {
        if ( (int) get_option( 'crg_db_version', 0 ) >= self::DB_VERSION || get_transient( 'crg_upgrading' ) ) {
            return;
        }
        set_transient( 'crg_upgrading', 1, 5 * MINUTE_IN_SECONDS ); // one request at a time
        global $wpdb;
        $cursor = get_option( self::UPGRADE_CURSOR, false );
        if ( false === $cursor ) {
            self::upgrade_site();
            $cursor = 0;
        }
        $ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'cleanup_report' AND post_status NOT IN ( 'auto-draft', 'inherit' ) AND ID > %d ORDER BY ID ASC LIMIT %d", (int) $cursor, self::UPGRADE_BATCH ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- upgrade cursor over report IDs
        if ( $ids ) {
            _prime_post_caches( $ids, false, true ); // posts and their meta in two queries, not one per report
            foreach ( $ids as $id ) {
                self::upgrade_report( get_post( $id ) );
            }
            $cursor = end( $ids );
        }
        if ( count( $ids ) < self::UPGRADE_BATCH ) {
            update_option( 'crg_db_version', self::DB_VERSION, true );
            delete_option( self::UPGRADE_CURSOR );
            wp_clear_scheduled_hook( self::UPGRADE_HOOK );
        } else {
            update_option( self::UPGRADE_CURSOR, (int) $cursor, false );
            if ( ! wp_next_scheduled( self::UPGRADE_HOOK ) ) {
                wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::UPGRADE_HOOK );
            }
        }
        delete_transient( 'crg_upgrading' );
    }

    /** The upgrade's site-wide steps (each safe to repeat). */
    private static function upgrade_site() {
        global $wpdb;
        $had_plugin = (int) get_option( 'crg_db_version', 0 ) > 0 || false !== get_option( 'crg_brand_name' )
            || (bool) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'cleanup_report' AND post_status <> 'auto-draft' LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time check
        Cleanup_Report_Presets::choose_on_upgrade( $had_plugin );
        if ( false === get_option( 'crg_plugin_recheck' ) ) {
            add_option( 'crg_plugin_recheck', $had_plugin ? 1 : 0, '', false );
        }
        if ( ! $had_plugin && false === get_option( Cleanup_Report_Retention::OPTION ) ) {
            add_option( Cleanup_Report_Retention::OPTION, 0, '', false ); // a new install keeps reports until someone sets a retention period
        }
        // Every report without a type is a Malware cleanup report: one query instead of one write per report.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time backfill; the meta caches of the reports are cleared below
        $untyped = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type = 'cleanup_report' AND p.post_status <> 'auto-draft' AND m.meta_id IS NULL", Cleanup_Report_Types::META ) );
        if ( $untyped ) {
            $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->postmeta} ( post_id, meta_key, meta_value ) SELECT p.ID, %s, %s FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type = 'cleanup_report' AND p.post_status <> 'auto-draft' AND m.meta_id IS NULL", Cleanup_Report_Types::META, Cleanup_Report_Types::DEFAULT_TYPE, Cleanup_Report_Types::META ) );
            foreach ( $untyped as $id ) {
                wp_cache_delete( (int) $id, 'post_meta' );
            }
        }
        // phpcs:enable
        Cleanup_Report_Checklists::rename_saved_labels();
        if ( function_exists( 'wp_set_options_autoload' ) ) {
            wp_set_options_autoload( self::ADMIN_OPTIONS, false );
            wp_set_option_autoload( Cleanup_Report_Presets::OPTION, true ); // read on every admin page (the menu labels)
        }
        $footer = get_option( 'crg_footer_text' ); // a saved "© 2026 …" froze the year: make it follow the date
        if ( is_string( $footer ) ) {
            $live = preg_replace( '/^(©|&copy;|\(c\))\s*(?:19|20)\d{2}(?!\d)/iu', '$1 {year}', $footer, 1 );
            if ( is_string( $live ) && $live !== $footer ) {
                update_option( 'crg_footer_text', $live );
            }
        }
        delete_option( 'crg_post_status_synced' ); // flags from earlier 2.1.0 builds
        delete_option( 'crg_scanner_line_replaced' );
    }

    /** The upgrade's steps for one report (each safe to repeat). */
    private static function upgrade_report( $post ) {
        global $wpdb;
        if ( ! $post ) {
            return;
        }
        $want = self::POST_STATUS[ self::status( $post->ID ) ];
        if ( 'trash' !== $post->post_status && $post->post_status !== $want ) {
            $wpdb->update( $wpdb->posts, [ 'post_status' => $want ], [ 'ID' => $post->ID ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time fix; wp_update_post() would change the modified date; the cache is cleared next
            clean_post_cache( $post->ID );
        }
        if ( ! self::is_final( $post->ID ) ) {
            Cleanup_Report_Checklists::rename_report_labels( $post->ID );
        }
        $text = (string) get_post_meta( $post->ID, 'crg_verification_summary', true );
        $new  = preg_replace( '#Front-end scan with \S+ SiteCheck and VirusTotal#i', 'Front-end scan with external malware scanners such as VirusTotal', $text );
        if ( $new !== $text ) {
            update_post_meta( $post->ID, 'crg_verification_summary', wp_slash( $new ) );
        }
    }

    /**
     * Settings only wp-admin, WP-Cron and the PDF need: kept out of the autoload that every front-end page reads.
     * (crg_db_version stays autoloaded: every admin page checks it.)
     */
    const ADMIN_OPTIONS = [
        Cleanup_Report_Checklists::FOLLOWUP_OPTION, Cleanup_Report_Checklists::DEFENDER_OPTION, Cleanup_Report_Checklists::BLOCKLIST_OPTION, Cleanup_Report_Optimization::CHECKS_OPTION,
        'crg_brand_name', 'crg_brand_logo', 'crg_brand_logo_light', 'crg_brand_primary', 'crg_report_theme', 'crg_cover_glow', 'crg_footer_text',
        'crg_color_success', 'crg_color_warning', 'crg_color_high', 'crg_color_danger', 'crg_color_neutral', 'crg_watermark_text', 'crg_pdf_requires_approval',
        'crg_buddyleads', 'crg_retention_days', 'crg_plugin_recheck', 'crg_delete_data', 'crg_opt_target_mobile', 'crg_opt_target_desktop',
        'crg_custom_types',
    ];

    /** On the plugin's own screens, load its (non-autoloaded) settings in one query instead of one each. */
    public static function prime_options( $screen ) {
        if ( $screen && 'cleanup_report' === $screen->post_type ) {
            self::prime_option_cache();
        }
    }

    public static function prime_option_cache() {
        if ( function_exists( 'wp_prime_option_caches' ) ) {
            wp_prime_option_caches( self::ADMIN_OPTIONS );
        }
    }

    /** When one of ADMIN_OPTIONS is first saved, keep it out of every page's autoload. */
    public static function checklists_not_autoloaded( $option ) {
        if ( in_array( $option, self::ADMIN_OPTIONS, true ) && function_exists( 'wp_set_option_autoload' ) ) {
            wp_set_option_autoload( $option, false );
        }
    }

    /* ================================================================== history */

    /**
     * Notes who did what to a report, and when (shown in its History box). Repeats of the same thing by the same
     * person within 15 minutes are merged into one line, so a run of saves reads "Edited Findings, Defender" once.
     */
    public static function log( $post_id, $action, $note = '', array $sections = [] ) {
        $history = get_post_meta( $post_id, 'crg_history', true );
        $history = is_array( $history ) ? $history : [];
        $user    = get_current_user_id();
        $now     = time();
        $i       = array_key_last( $history );
        $last    = null === $i ? null : $history[ $i ];
        if ( $last && (int) $last['u'] === $user && $last['a'] === $action && $now - (int) $last['t'] < 15 * MINUTE_IN_SECONDS && '' === $note && empty( $last['n'] ) ) {
            $history[ $i ]['t'] = $now;
            $history[ $i ]['s'] = array_values( array_unique( array_merge( (array) ( $last['s'] ?? [] ), $sections ) ) );
        } else {
            $history[] = [ 't' => $now, 'u' => $user, 'a' => $action, 'n' => $note, 's' => $sections ];
        }
        update_post_meta( $post_id, 'crg_history', wp_slash( array_slice( $history, -200 ) ) );
    }

    /** Current values of every field, by section, to see what a save changed. */
    private static function history_snapshot( $post_id ) {
        $snap = [];
        foreach ( self::history_fields( $post_id ) as $section => $keys ) {
            foreach ( $keys as $key ) {
                $snap[ $section ][ $key ] = get_post_meta( $post_id, $key, true );
            }
            $snap[ $section ] = wp_json_encode( $snap[ $section ] );
        }
        return $snap;
    }

    public static function history_created( $new, $old, $post ) {
        if ( 'cleanup_report' === $post->post_type && 'auto-draft' === $old && ! in_array( $new, [ 'auto-draft', 'inherit', 'trash' ], true ) ) {
            self::$created = true;
            self::log( $post->ID, 'Created the report' );
        }
    }

    public static function history_title( $post_id, $after, $before ) {
        if ( 'cleanup_report' === $after->post_type && 'auto-draft' !== $before->post_status && $after->post_title !== $before->post_title ) {
            self::$title_changed = true;
        }
    }

    public static function history_trash( $post_id ) {
        if ( 'cleanup_report' === get_post_type( $post_id ) ) {
            self::log( $post_id, 'trashed_post' === current_action() ? 'Moved to Trash' : 'Restored from Trash' );
        }
    }

    /** Side box: the report's history, newest first. */
    public static function box_history( $post ) {
        $history = get_post_meta( $post->ID, 'crg_history', true );
        $history = is_array( $history ) ? array_reverse( $history ) : [];
        if ( ! $history ) {
            echo '<p class="crg-muted">' . esc_html__( 'Edits, review steps and PDF downloads will be noted here.', 'cleanup-report-generator' ) . '</p>';
            return;
        }
        $row = static function ( $e ) {
            $user = get_userdata( (int) $e['u'] );
            $what = self::history_label( $e['a'] ) . ( ! empty( $e['s'] ) ? ' ' . implode( ', ', array_map( [ __CLASS__, 'history_label' ], (array) $e['s'] ) ) : '' );
            echo '<li><strong>' . esc_html( $what ) . '</strong><span class="crg-muted">' . esc_html( ( $user ? $user->display_name : 'WordPress' ) . ' · ' . wp_date( 'j M Y, H:i', (int) $e['t'] ) ) . '</span>';
            if ( '' !== (string) $e['n'] ) {
                echo '<span class="crg-history-note">' . nl2br( esc_html( $e['n'] ) ) . '</span>';
            }
            echo '</li>';
        };
        echo '<ul class="crg-history">';
        array_map( $row, array_slice( $history, 0, 8 ) );
        echo '</ul>';
        if ( count( $history ) > 8 ) {
            $more = count( $history ) - 8;
            /* translators: %d: number of older history entries */
            echo '<details class="crg-history-more"><summary>' . esc_html( sprintf( _n( 'Show %d earlier', 'Show %d earlier', $more, 'cleanup-report-generator' ), $more ) ) . '</summary><ul class="crg-history">';
            array_map( $row, array_slice( $history, 8 ) );
            echo '</ul></details>';
        }
    }

    /** Reports list: the Review column shows where a report is, so drop WordPress's "— Draft" / "— Pending" labels. */
    public static function post_states( $states, $post ) {
        if ( 'cleanup_report' === $post->post_type ) {
            unset( $states['draft'], $states['pending'], $states['private'], $states['scheduled'] );
        }
        return $states;
    }

    /* ================================================================== edit screen */

    public static function register_meta_boxes( $post ) {
        foreach ( Cleanup_Report_Types::get( Cleanup_Report_Types::of( $post->ID ) )['sections'] as $key => $section ) {
            add_meta_box( 'crg_box_' . $key, $section['title'], [ __CLASS__, 'box_section' ], 'cleanup_report', 'normal', 'high', [ 'section' => $key ] );
        }
        // The Review box saves the report, so WordPress's Publish box (its own status, visibility and publish date,
        // which mean nothing for reports) is removed. The Review box comes first, where Publish used to be.
        remove_meta_box( 'submitdiv', 'cleanup_report', 'side' );
        remove_meta_box( 'pageparentdiv', 'cleanup_report', 'side' ); // a theme's page templates mean nothing for reports
        add_meta_box( 'crg_review', __( 'Save & review', 'cleanup-report-generator' ), [ __CLASS__, 'box_review' ], 'cleanup_report', 'side', 'high' );
        // 'core', not 'high': a saved box order moves the Review box to WordPress's 'sorted' slot, which comes after 'high'.
        add_meta_box( 'crg_box_export', __( 'Report', 'cleanup-report-generator' ), [ __CLASS__, 'box_export' ], 'cleanup_report', 'side', 'core' );
        add_meta_box( 'crg_history', __( 'History', 'cleanup-report-generator' ), [ __CLASS__, 'box_history' ], 'cleanup_report', 'side', 'low' );
    }

    /**
     * One section of the report type as an edit-screen box. The first box also carries the form's nonce and the
     * report's type.
     */
    public static function box_section( $post, $box ) {
        $type     = Cleanup_Report_Types::of( $post->ID );
        $sections = Cleanup_Report_Types::get( $type )['sections'];
        if ( array_key_first( $sections ) === $box['args']['section'] ) {
            wp_nonce_field( 'crg_save_' . $post->ID, 'crg_nonce' );
            echo '<input type="hidden" name="crg_report_type" value="' . esc_attr( $type ) . '">';
        }
        $section = $sections[ $box['args']['section'] ] ?? null;
        if ( $section ) {
            Cleanup_Report_Fields::render_section( $post, $section );
        }
    }

    public static function title_placeholder( $text, $post ) {
        if ( 'cleanup_report' !== $post->post_type ) {
            return $text;
        }
        /* translators: %s: the report type's title suffix, e.g. Malware cleanup report */
        return sprintf( __( 'Report title, e.g. Acme Bakery – %s', 'cleanup-report-generator' ), Cleanup_Report_Types::get( Cleanup_Report_Types::of( $post->ID ) )['suffix'] );
    }

    /** "Jump to" links under the title, one per section. */
    public static function section_nav( $post ) {
        if ( 'cleanup_report' !== $post->post_type ) {
            return;
        }
        $type = Cleanup_Report_Types::of( $post->ID );
        echo '<nav class="crg-nav" aria-label="' . esc_attr__( 'Report sections', 'cleanup-report-generator' ) . '" data-title-suffix="' . esc_attr( Cleanup_Report_Types::get( $type )['suffix'] ) . '">';
        Cleanup_Report_Types::badge( $type );
        $n = 0;
        foreach ( self::sections( $post->ID ) as $id => $title ) {
            printf( '<a href="#%s"><span>%d</span>%s</a>', esc_attr( $id ), (int) ++$n, esc_html( $title ) );
        }
        echo '</nav>';
    }

    /* ---------- side: preview / PDF */

    public static function box_export( $post ) {
        if ( 'auto-draft' === $post->post_status ) {
            echo '<p class="crg-muted">' . esc_html__( 'Save the report to preview it or download the PDF.', 'cleanup-report-generator' ) . '</p>';
            return;
        }
        $view = wp_nonce_url( admin_url( 'admin-post.php?action=crg_view_report&post=' . $post->ID ), 'crg_view_' . $post->ID );
        $pdf  = wp_nonce_url( admin_url( 'admin-post.php?action=crg_export_pdf&post=' . $post->ID ), 'crg_pdf_' . $post->ID );
        ?>
        <div class="crg-export">
            <?php if ( self::can_download( $post->ID ) ) : ?>
                <a class="button button-primary crg-export-pdf" href="<?php echo esc_url( $pdf ); ?>"><span class="dashicons dashicons-pdf"></span> <?php esc_html_e( 'Download PDF', 'cleanup-report-generator' ); ?></a>
            <?php else : ?>
                <span class="button button-primary crg-export-pdf disabled" aria-disabled="true"><span class="dashicons dashicons-pdf"></span> <?php esc_html_e( 'Download PDF', 'cleanup-report-generator' ); ?></span>
            <?php endif; ?>
            <a class="button" href="<?php echo esc_url( $view ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview in browser', 'cleanup-report-generator' ); ?></a>
        </div>
        <?php if ( ! self::can_download( $post->ID ) ) : ?>
            <p class="crg-muted crg-export-locked">
                <?php
                echo wp_kses(
                    sprintf(
                        /* translators: %s: the mark on reports that aren't approved yet, e.g. NOT FINAL */
                        __( 'The PDF can be downloaded once the report is approved. Until then the preview is marked <strong>%s</strong>.', 'cleanup-report-generator' ),
                        esc_html( self::watermark() )
                    ),
                    [ 'strong' => [] ]
                );
                ?>
            </p>
        <?php endif; ?>
        <p class="crg-muted crg-export-note"><?php esc_html_e( 'Uses the last saved version.', 'cleanup-report-generator' ); ?></p>
        <p class="crg-dirty" hidden><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'You have unsaved changes. Save to include them.', 'cleanup-report-generator' ); ?></p>
        <?php
    }

    /* ---------- side: review */

    private static function status_badge( $status ) {
        printf( '<span class="crg-status-badge crg-status-%s">%s</span>', esc_attr( $status ), esc_html( self::statuses()[ $status ] ?? $status ) );
    }

    private static function review_dates( $post ) {
        $labels = [
            'submitted' => [
                /* translators: %s: date and time */
                __( 'Submitted %s', 'cleanup-report-generator' ),
                /* translators: 1: a person's name, 2: date and time */
                __( 'Submitted by %1$s, %2$s', 'cleanup-report-generator' ),
            ],
            'reviewed'  => [
                /* translators: %s: date and time */
                __( 'Approved %s', 'cleanup-report-generator' ),
                /* translators: 1: a person's name, 2: date and time */
                __( 'Approved by %1$s, %2$s', 'cleanup-report-generator' ),
            ],
            'completed' => [
                /* translators: %s: date and time */
                __( 'Completed %s', 'cleanup-report-generator' ),
                /* translators: 1: a person's name, 2: date and time */
                __( 'Completed by %1$s, %2$s', 'cleanup-report-generator' ),
            ],
        ];
        foreach ( $labels as $key => $label ) {
            $date = get_post_meta( $post->ID, 'crg_' . $key . '_date', true );
            $by   = get_userdata( (int) get_post_meta( $post->ID, 'crg_' . $key . '_by', true ) );
            if ( $date && strtotime( $date ) ) {
                $when = mysql2date( 'j M Y, H:i', $date );
                printf( '<p class="crg-muted">%s</p>', esc_html( $by ? sprintf( $label[1], $by->display_name, $when ) : sprintf( $label[0], $when ) ) );
            }
        }
    }

    /** The report's review status ("reviewed" from earlier versions reads as submitted). */
    public static function status( $post_id ) {
        $status = get_post_meta( $post_id, 'crg_review_status', true ) ?: 'draft';
        return 'reviewed' === $status ? 'submitted' : ( isset( self::STATUSES[ $status ] ) ? $status : 'draft' );
    }

    /** Approved or completed: the report is final (no "Not final" watermark; the PDF can be downloaded). */
    public static function is_final( $post_id ) {
        return in_array( self::status( $post_id ), [ 'approved', 'complete' ], true );
    }

    /** Can the PDF be downloaded? Only once approved, unless an administrator turned that off (Settings › Review). */
    public static function can_download( $post_id ) {
        return ! get_option( 'crg_pdf_requires_approval', 1 ) || self::is_final( $post_id );
    }

    /** The mark on reports that aren't approved yet (Settings › Review; administrators). */
    public static function watermark() {
        return trim( (string) get_option( 'crg_watermark_text', __( 'NOT FINAL', 'cleanup-report-generator' ) ) ) ?: __( 'NOT FINAL', 'cleanup-report-generator' );
    }

    /** User IDs of the Buddyleads: the only people who can approve reports or send them back. */
    public static function buddyleads() {
        return array_map( 'intval', (array) get_option( self::BUDDYLEADS_OPTION, [] ) );
    }

    /** Can this user approve? The site's administrators always can (full access); so can the Buddyleads. */
    public static function is_buddylead( $user_id = 0 ) {
        $user_id = $user_id ?: get_current_user_id();
        // A Buddylead below Editor couldn't save anyone else's report, so the role is required as well as the tick.
        return user_can( $user_id, 'manage_options' ) || ( in_array( (int) $user_id, self::buddyleads(), true ) && user_can( $user_id, 'edit_others_posts' ) );
    }

    /** Statuses this user can move a report to from $current (always including $current itself). */
    public static function allowed_statuses( $current, $lead ) {
        $allowed = [ $current ];
        if ( in_array( $current, [ 'draft', 'submitted', 'needs_revision' ], true ) || $lead ) {
            $allowed = array_merge( $allowed, [ 'draft', 'submitted' ] );
        }
        if ( $lead ) {
            $allowed = array_merge( $allowed, [ 'needs_revision', 'approved' ] );
        }
        if ( in_array( $current, [ 'approved', 'complete' ], true ) ) {
            $allowed[] = 'complete';
        }
        return array_values( array_intersect( array_keys( self::STATUSES ), $allowed ) ); // in workflow order
    }

    /** The review status a save moves the report to: the one picked, if this user may, else it stays. */
    public static function next_status( $post_id, $picked ) {
        $current = self::status( $post_id );
        $picked  = sanitize_key( (string) $picked );
        return $picked && in_array( $picked, self::allowed_statuses( $current, self::is_buddylead() ), true ) ? $picked : $current;
    }

    /** The Save button's label for moving from $current to $target. */
    public static function save_label( $target, $current ) {
        if ( $target === $current ) {
            return 'draft' === $current ? __( 'Save draft', 'cleanup-report-generator' ) : __( 'Save', 'cleanup-report-generator' );
        }
        $labels = [
            'draft'          => __( 'Move back to draft', 'cleanup-report-generator' ),
            'submitted'      => 'needs_revision' === $current ? __( 'Resubmit for review', 'cleanup-report-generator' ) : __( 'Submit for review', 'cleanup-report-generator' ),
            'needs_revision' => __( 'Send back for revision', 'cleanup-report-generator' ),
            'approved'       => __( 'Approve', 'cleanup-report-generator' ),
            'complete'       => __( 'Mark completed', 'cleanup-report-generator' ),
        ];
        return $labels[ $target ] ?? __( 'Save', 'cleanup-report-generator' );
    }

    /** Keeps WordPress's post status in step with the review status on every save (see POST_STATUS). */
    public static function sync_post_status( $data, $postarr ) {
        if ( 'cleanup_report' !== $data['post_type'] || in_array( $data['post_status'], [ 'trash', 'auto-draft', 'inherit' ], true ) ) {
            return $data;
        }
        $id     = (int) ( $postarr['ID'] ?? 0 );
        $review = $id ? self::next_status( $id, $postarr['crg_review_status'] ?? '' ) : 'draft';
        $data['post_status'] = self::POST_STATUS[ $review ];
        return $data;
    }

    /** After a save that changed the review status, say what happened ("Report approved.") instead of "updated". */
    public static function saved_message( $location, $post_id ) {
        if ( self::$changed_to && 'cleanup_report' === get_post_type( $post_id ) ) {
            $location = add_query_arg( 'message', self::STATUS_MESSAGES[ self::$changed_to ][0], $location );
        }
        return $location;
    }

    public static function box_review( $post ) {
        $status       = self::status( $post->ID );
        $lead         = self::is_buddylead();
        $allowed      = self::allowed_statuses( $status, $lead );
        $author_notes = get_post_meta( $post->ID, 'crg_author_notes', true );
        $review_notes = get_post_meta( $post->ID, 'crg_review_notes', true );
        $names        = array_filter( array_map( static function ( $id ) { $u = get_userdata( $id ); return $u ? $u->display_name : ''; }, self::buddyleads() ) );
        ?>
        <div class="crg-review">
            <p><?php self::status_badge( $status ); ?></p>
            <?php self::review_dates( $post ); ?>
            <?php if ( $review_notes ) : ?>
                <div class="crg-note-box is-review"><strong><?php esc_html_e( 'Buddylead\'s notes', 'cleanup-report-generator' ); ?></strong><br><?php echo nl2br( esc_html( $review_notes ) ); ?></div>
            <?php endif; ?>
            <?php if ( $author_notes && $lead ) : ?>
                <div class="crg-note-box"><strong><?php esc_html_e( 'Notes from the author', 'cleanup-report-generator' ); ?></strong><br><?php echo nl2br( esc_html( $author_notes ) ); ?></div>
            <?php endif; ?>
            <p>
                <label for="crg_review_status"><strong><?php esc_html_e( 'Status', 'cleanup-report-generator' ); ?></strong></label>
                <select id="crg_review_status" name="crg_review_status" class="widefat" data-current="<?php echo esc_attr( $status ); ?>">
                    <?php foreach ( $allowed as $value ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" data-label="<?php echo esc_attr( self::save_label( $value, $status ) ); ?>" data-post-status="<?php echo esc_attr( self::POST_STATUS[ $value ] ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( self::statuses()[ $value ] ); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p class="crg-muted">
                <?php if ( 'approved' === $status ) : ?>
                    <?php esc_html_e( 'Approved. Once the report is sent to the member, mark it Completed.', 'cleanup-report-generator' ); ?>
                <?php elseif ( $lead && current_user_can( 'manage_options' ) ) : ?>
                    <?php esc_html_e( 'As an administrator, you can approve this report or send it back for changes.', 'cleanup-report-generator' ); ?>
                <?php elseif ( $lead ) : ?>
                    <?php esc_html_e( 'As a Buddylead, you can approve this report or send it back for changes.', 'cleanup-report-generator' ); ?>
                <?php elseif ( $names ) : ?>
                    <?php
                    /* translators: %s: names of the Buddyleads, e.g. "Ana, Ben" */
                    echo esc_html( sprintf( __( 'Buddyleads (%s) and administrators approve reports. Submit it for review when it\'s ready.', 'cleanup-report-generator' ), implode( ', ', $names ) ) );
                    ?>
                <?php else : ?>
                    <?php esc_html_e( 'Buddyleads and administrators approve reports. Submit it for review when it\'s ready.', 'cleanup-report-generator' ); ?>
                <?php endif; ?>
            </p>
            <?php if ( $lead ) : ?>
                <p>
                    <label for="crg_review_notes"><strong><?php esc_html_e( 'Notes for the author', 'cleanup-report-generator' ); ?></strong></label>
                    <textarea id="crg_review_notes" name="crg_review_notes" rows="3" class="widefat" placeholder="<?php esc_attr_e( 'What needs changing, or why it\'s approved', 'cleanup-report-generator' ); ?>"><?php echo esc_textarea( $review_notes ); ?></textarea>
                </p>
            <?php endif; ?>
            <?php if ( in_array( $status, [ 'draft', 'needs_revision' ], true ) ) : ?>
                <p>
                    <label for="crg_author_notes"><strong><?php esc_html_e( 'Notes for the Buddylead', 'cleanup-report-generator' ); ?></strong></label>
                    <textarea id="crg_author_notes" name="crg_author_notes" rows="2" class="widefat" placeholder="<?php esc_attr_e( 'Anything they should check', 'cleanup-report-generator' ); ?>"><?php echo esc_textarea( $author_notes ); ?></textarea>
                </p>
            <?php endif; ?>
            <div class="crg-save">
                <?php // Replaces WordPress's Publish box: the post status follows the review status (sync_post_status()). ?>
                <input type="hidden" name="post_status" id="crg_post_status" value="<?php echo esc_attr( self::POST_STATUS[ $status ] ); ?>">
                <input type="submit" name="save" id="crg-save" class="button button-primary button-large" value="<?php echo esc_attr( self::save_label( $status, $status ) ); ?>">
                <?php if ( in_array( $status, [ 'draft', 'needs_revision' ], true ) && in_array( 'submitted', $allowed, true ) ) : ?>
                    <button type="button" class="button button-large crg-submit-review"><?php echo esc_html( self::save_label( 'submitted', $status ) ); ?></button>
                <?php endif; ?>
                <?php if ( 'auto-draft' !== $post->post_status && current_user_can( 'delete_post', $post->ID ) ) : ?>
                    <a class="crg-trash" href="<?php echo esc_url( get_delete_post_link( $post->ID ) ); ?>"><?php esc_html_e( 'Move to Trash', 'cleanup-report-generator' ); ?></a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /* ================================================================== save */

    public static function save_meta( $post_id ) {
        if ( ! isset( $_POST['crg_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['crg_nonce'] ) ), 'crg_save_' . $post_id ) ) {
            return;
        }
        if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $before = self::history_snapshot( $post_id );

        // Review status: checked against what this user may do (only Buddyleads approve; completing needs approval).
        if ( isset( $_POST['crg_review_status'] ) ) {
            $old = self::status( $post_id );
            $new = self::next_status( $post_id, sanitize_key( wp_unslash( $_POST['crg_review_status'] ) ) );
            if ( $new !== $old ) {
                self::$changed_to = $new;
                // History: the step, with the note that goes with it (the Buddylead's when they review, else the writer's).
                $note_key = in_array( $new, [ 'needs_revision', 'approved' ], true ) ? 'crg_review_notes' : ( 'submitted' === $new ? 'crg_author_notes' : '' );
                $note     = $note_key && isset( $_POST[ $note_key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $note_key ] ) ) : '';
                $note     = get_post_meta( $post_id, $note_key, true ) === $note ? '' : $note; // only a note written for this step
                self::log( $post_id, 'submitted' === $new && 'needs_revision' === $old ? 'Resubmitted for review' : self::HISTORY_STEPS[ $new ], $note );
                update_post_meta( $post_id, 'crg_review_status', $new );
                $stamp = [ 'submitted' => 'submitted', 'approved' => 'reviewed', 'complete' => 'completed' ][ $new ] ?? '';
                if ( $stamp ) {
                    update_post_meta( $post_id, 'crg_' . $stamp . '_date', current_time( 'mysql' ) );
                    update_post_meta( $post_id, 'crg_' . $stamp . '_by', get_current_user_id() );
                }
            }
        }
        // An approved report is read-only for Writers: only its status (to Completed) is saved.
        if ( Cleanup_Report_Access::locked( $post_id ) ) {
            return;
        }
        Cleanup_Report_Types::save( $post_id, sanitize_key( wp_unslash( $_POST['crg_report_type'] ?? '' ) ) ); // only sets a missing type
        $type = Cleanup_Report_Types::of( $post_id );
        if ( isset( $_POST['crg_author_notes'] ) ) {
            update_post_meta( $post_id, 'crg_author_notes', wp_slash( sanitize_textarea_field( wp_unslash( $_POST['crg_author_notes'] ) ) ) );
        }
        if ( isset( $_POST['crg_review_notes'] ) && self::is_buddylead() ) {
            update_post_meta( $post_id, 'crg_review_notes', wp_slash( sanitize_textarea_field( wp_unslash( $_POST['crg_review_notes'] ) ) ) );
        }

        // The report type's fields, each sanitised by its field type (Cleanup_Report_Fields::save()).
        $definition = Cleanup_Report_Types::get( $type );
        foreach ( $definition['sections'] as $section ) {
            foreach ( $section['fields'] as $f ) {
                Cleanup_Report_Fields::save( $post_id, $f );
            }
        }
        if ( isset( $definition['on_save'] ) && is_callable( $definition['on_save'] ) ) {
            call_user_func( $definition['on_save'], $post_id );
        }

        // History: which sections this save changed (notes that went with a review step are already noted with it).
        $after   = self::history_snapshot( $post_id );
        $changed = array_keys( array_diff_assoc( $after, $before ) );
        if ( self::$changed_to ) {
            $changed = array_diff( $changed, [ 'Notes' ] );
        }
        if ( self::$title_changed ) {
            array_unshift( $changed, 'Title' );
        }
        if ( $changed && ! self::$created ) {
            self::log( $post_id, 'Edited', '', array_values( $changed ) );
        }
    }

    /**
     * Adds https:// to a bare domain; for the website URL also drops wp-admin/wp-login paths, queries and the
     * trailing slash, so pasting any link to the site gives its address. Mirrors normalizeUrl() in admin.js.
     */
    public static function normalize_url( $url, $site = false ) {
        $url = trim( $url );
        if ( '' === $url ) {
            return '';
        }
        if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $url ) ) {
            $url = 'https://' . ltrim( $url, '/' );
        }
        if ( $site ) {
            $url = preg_replace( '#[?\#].*$#', '', $url );
            $url = preg_replace( '#/(wp-admin|wp-login\.php|wp-content|wp-includes)(/.*)?$#i', '', $url );
            $url = untrailingslashit( $url );
            $url = preg_replace_callback( '#^([a-z]+://)([^/]+)#i', static function ( $m ) { return strtolower( $m[1] . $m[2] ); }, $url );
        }
        return $url;
    }

    /* ================================================================== Reports list */

    public static function columns( $columns ) {
        $date = $columns['date'] ?? __( 'Date', 'cleanup-report-generator' );
        unset( $columns['date'] );
        return $columns + [
            'crg_type'   => __( 'Type', 'cleanup-report-generator' ),
            'crg_client' => __( 'Prepared for', 'cleanup-report-generator' ),
            'crg_site'   => __( 'Website', 'cleanup-report-generator' ),
            'crg_review' => __( 'Review', 'cleanup-report-generator' ),
            'crg_date'   => __( 'Report date', 'cleanup-report-generator' ),
            'date'       => $date,
        ];
    }

    public static function column( $column, $post_id ) {
        switch ( $column ) {
            case 'crg_type':
                Cleanup_Report_Types::badge( Cleanup_Report_Types::of( $post_id ) );
                break;
            case 'crg_client':
                echo esc_html( get_post_meta( $post_id, 'crg_client_name', true ) ?: '—' );
                break;
            case 'crg_site':
                $url = get_post_meta( $post_id, 'crg_site_url', true );
                echo $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( wp_parse_url( $url, PHP_URL_HOST ) ?: $url ) . '</a>' : '—';
                break;
            case 'crg_review':
                self::status_badge( self::status( $post_id ) );
                break;
            case 'crg_date':
                $date = get_post_meta( $post_id, 'crg_report_date', true );
                echo $date && strtotime( $date ) ? esc_html( wp_date( 'j M Y', strtotime( $date ) ) ) : '—';
                break;
        }
    }

    public static function sortable_columns( $columns ) {
        $columns['crg_date']   = 'crg_report_date';
        $columns['crg_client'] = 'crg_client_name';
        return $columns;
    }

    public static function sort_by_report_date( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() || 'cleanup_report' !== $query->get( 'post_type' ) ) {
            return;
        }
        $orderby = $query->get( 'orderby' );
        if ( in_array( $orderby, [ 'crg_report_date', 'crg_client_name' ], true ) ) {
            $query->set( 'meta_key', $orderby ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin list sort only
            $query->set( 'orderby', 'meta_value' );
        }
    }
}
