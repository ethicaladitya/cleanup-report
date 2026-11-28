<?php
/**
 * The report edit screen (meta boxes, in the same order as the report), saving, and the Reports list.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Cleanup_Report_Admin {

    /**
     * Review workflow. Anyone who can edit a report can draft it and submit it for review; only Buddyleads
     * (Reports › Settings › Review) can approve it or send it back; once approved, anyone can mark it completed.
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

    /** "Report approved." etc. after a save that changed the review status (post_updated_messages indexes). */
    const STATUS_MESSAGES = [
        'draft'          => [ 51, 'Report moved back to draft.' ],
        'submitted'      => [ 52, 'Report submitted for review.' ],
        'needs_revision' => [ 53, 'Report sent back for revision. The author will see your notes.' ],
        'approved'       => [ 54, 'Report approved. It can now be sent to the member.' ],
        'complete'       => [ 55, 'Report marked completed.' ],
    ];

    /** Bump to run upgrade() once more on every site (stored in the autoloaded option crg_db_version). */
    const DB_VERSION = 2;

    /** The review status this request changed the report to, for the "saved" message. */
    private static $changed_to = '';

    /** History: the fields in each section, so an edit is noted as "Edited Findings, Defender". */
    const HISTORY_FIELDS = [
        'Member & website'         => [ 'crg_client_name', 'crg_site_url', 'crg_report_date', 'crg_request_link', 'crg_task_url' ],
        'Findings'                 => [ 'crg_screenshots', 'crg_infection_summary', 'crg_summary_critical', 'crg_summary_medium', 'crg_summary_low', 'crg_components', 'crg_improvements', 'crg_plugin_list' ],
        'Work done & verification' => [ 'crg_highlights', 'crg_verification_summary', 'crg_additional_sites', 'crg_delist' ],
        'Defender'                 => [ 'crg_defender' ],
        'Member next steps'        => [ 'crg_pending_actions', 'crg_followups', 'crg_reinstall_list', 'crg_replace_list' ],
        'Member guidance'          => [ 'crg_documentation_links', 'crg_seo_note', 'crg_protect_note' ],
        'Notes'                    => [ 'crg_author_notes', 'crg_review_notes' ],
    ];

    /** What each review step is called in the history. */
    const HISTORY_STEPS = [
        'draft'          => 'Moved back to draft',
        'submitted'      => 'Submitted for review',
        'needs_revision' => 'Sent back for revision',
        'approved'       => 'Approved',
        'complete'       => 'Marked completed',
    ];

    /** Set when this request renamed the report (post_updated runs before save_post). */
    private static $title_changed = false;

    /** Set when this request created the report: its first save fills in defaults, which aren't edits. */
    private static $created = false;

    /** Rich-text editor settings shared by every editor on the form (the PDF only uses these tags). */
    const EDITOR = [
        'media_buttons' => false,
        'teeny'         => false,
        'tinymce'       => [ 'toolbar1' => 'bold,italic,bullist,numlist,link,unlink,undo,redo', 'toolbar2' => '' ],
        'quicktags'     => [ 'buttons' => 'strong,em,ul,ol,li,link,code,close' ],
    ];

    const HELP_TAGS = [ 'code' => [], 'strong' => [], 'em' => [], 'button' => [ 'type' => [], 'class' => [], 'hidden' => [] ] ];

    const SECTIONS = [
        'crg_box_site'     => 'Member & website',
        'crg_box_findings' => 'Findings',
        'crg_box_work'     => 'Work done & verification',
        'crg_box_defender' => 'Defender',
        'crg_box_next'     => 'Member next steps',
        'crg_box_guidance' => 'Member guidance',
    ];

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
        }
        add_action( 'added_option', [ __CLASS__, 'checklists_not_autoloaded' ] );
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
     * Every step is safe to repeat, so a version bump simply runs them all again once.
     */
    public static function upgrade() {
        if ( ! current_user_can( 'edit_posts' ) || get_transient( 'crg_upgrading' ) ) {
            return;
        }
        set_transient( 'crg_upgrading', 1, 5 * MINUTE_IN_SECONDS ); // one admin request at a time
        global $wpdb;
        // Whole posts (not just IDs) so their meta is loaded in one query, not one per report.
        $reports = get_posts( [ 'post_type' => 'cleanup_report', 'post_status' => [ 'draft', 'pending', 'publish', 'private', 'future', 'trash' ], 'numberposts' => -1, 'no_found_rows' => true ] );
        foreach ( $reports as $post ) {
            $want = self::POST_STATUS[ self::status( $post->ID ) ];
            if ( 'trash' !== $post->post_status && $post->post_status !== $want ) {
                $wpdb->update( $wpdb->posts, [ 'post_status' => $want ], [ 'ID' => $post->ID ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time fix; wp_update_post() would change the modified date; the cache is cleared next
                clean_post_cache( $post->ID );
            }
            $text = (string) get_post_meta( $post->ID, 'crg_verification_summary', true );
            $new  = preg_replace( '#Front-end scan with \S+ SiteCheck and VirusTotal#i', 'Front-end scan with external malware scanners such as VirusTotal', $text );
            if ( $new !== $text ) {
                update_post_meta( $post->ID, 'crg_verification_summary', wp_slash( $new ) );
            }
        }
        if ( function_exists( 'wp_set_options_autoload' ) ) {
            wp_set_options_autoload( [ Cleanup_Report_Checklists::FOLLOWUP_OPTION, Cleanup_Report_Checklists::DEFENDER_OPTION ], false );
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
        update_option( 'crg_db_version', self::DB_VERSION, true );
        delete_transient( 'crg_upgrading' );
    }

    /** The checklists are only needed in wp-admin: when first saved, keep them out of every page's autoload. */
    public static function checklists_not_autoloaded( $option ) {
        if ( in_array( $option, [ Cleanup_Report_Checklists::FOLLOWUP_OPTION, Cleanup_Report_Checklists::DEFENDER_OPTION ], true ) && function_exists( 'wp_set_option_autoload' ) ) {
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
        foreach ( self::HISTORY_FIELDS as $section => $keys ) {
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
            echo '<p class="crg-muted">Edits, review steps and PDF downloads will be noted here.</p>';
            return;
        }
        $row = static function ( $e ) {
            $user = get_userdata( (int) $e['u'] );
            $what = $e['a'] . ( ! empty( $e['s'] ) ? ' ' . implode( ', ', (array) $e['s'] ) : '' );
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
            echo '<details class="crg-history-more"><summary>Show ' . (int) ( count( $history ) - 8 ) . ' earlier</summary><ul class="crg-history">';
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

    public static function register_meta_boxes() {
        foreach ( self::SECTIONS as $id => $title ) {
            add_meta_box( $id, $title, [ __CLASS__, 'box_' . substr( $id, 8 ) ], 'cleanup_report', 'normal', 'high' );
        }
        // The Review box saves the report, so WordPress's Publish box (its own status, visibility and publish date,
        // which mean nothing for reports) is removed. The Review box comes first, where Publish used to be.
        remove_meta_box( 'submitdiv', 'cleanup_report', 'side' );
        add_meta_box( 'crg_review', 'Save & review', [ __CLASS__, 'box_review' ], 'cleanup_report', 'side', 'high' );
        // 'core', not 'high': a saved box order moves the Review box to WordPress's 'sorted' slot, which comes after 'high'.
        add_meta_box( 'crg_box_export', 'Report', [ __CLASS__, 'box_export' ], 'cleanup_report', 'side', 'core' );
        add_meta_box( 'crg_history', 'History', [ __CLASS__, 'box_history' ], 'cleanup_report', 'side', 'low' );
    }

    public static function title_placeholder( $text, $post ) {
        return 'cleanup_report' === $post->post_type ? 'Report title, e.g. Acme Bakery – malware cleanup' : $text;
    }

    /** "Jump to" links under the title, one per section. */
    public static function section_nav( $post ) {
        if ( 'cleanup_report' !== $post->post_type ) {
            return;
        }
        echo '<nav class="crg-nav" aria-label="Report sections">';
        $n = 0;
        foreach ( self::SECTIONS as $id => $title ) {
            printf( '<a href="#%s"><span>%d</span>%s</a>', esc_attr( $id ), (int) ++$n, esc_html( $title ) );
        }
        echo '</nav>';
    }

    private static function meta( $post, $key, $default = '' ) {
        $value = get_post_meta( $post->ID, $key, true );
        return '' === $value || null === $value ? $default : $value;
    }

    /** Label + input + help, the building block of the form. */
    private static function input( $post, $key, $label, array $args = [] ) {
        $args += [ 'type' => 'text', 'help' => '', 'placeholder' => '', 'default' => '', 'class' => '', 'attrs' => '' ];
        $value = self::meta( $post, $key, $args['default'] );
        ?>
        <div class="crg-field <?php echo esc_attr( $args['class'] ); ?>">
            <label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
            <?php if ( 'textarea' === $args['type'] ) : ?>
                <textarea id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" rows="<?php echo (int) ( $args['rows'] ?? 4 ); ?>" placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
            <?php else : ?>
                <input type="<?php echo esc_attr( $args['type'] ); ?>" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>" <?php echo $args['attrs']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed attribute strings from this class ?>>
            <?php endif; ?>
            <?php if ( $args['help'] ) : ?><p class="description"><?php echo wp_kses( $args['help'], self::HELP_TAGS ); ?></p><?php endif; ?>
        </div>
        <?php
    }

    private static function editor( $post, $key, $label, $help = '', $rows = 6, $default = '' ) {
        ?>
        <div class="crg-field crg-field-editor">
            <label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
            <?php if ( $help ) : ?><p class="description"><?php echo wp_kses( $help, self::HELP_TAGS ); ?></p><?php endif; ?>
            <?php wp_editor( (string) self::meta( $post, $key, $default ), $key, [ 'textarea_name' => $key, 'textarea_rows' => $rows ] + self::EDITOR ); ?>
        </div>
        <?php
    }

    /* ---------- 1. Member & website */

    public static function box_site( $post ) {
        wp_nonce_field( 'crg_save_' . $post->ID, 'crg_nonce' );
        ?>
        <div class="crg-cols crg-cols-2">
            <?php
            self::input( $post, 'crg_client_name', 'Prepared for', [ 'placeholder' => 'Member or business name', 'help' => 'Optional. The cover shows the website as its headline, with "Prepared for …" under it.' ] );
            self::input( $post, 'crg_site_url', 'Website URL', [ 'type' => 'url', 'placeholder' => 'https://example.com', 'help' => 'Paste any page or wp-admin link; it\'s trimmed to the site address.', 'attrs' => 'data-crg-url="site" autocomplete="off"' ] );
            self::input( $post, 'crg_report_date', 'Report date', [ 'type' => 'date', 'default' => wp_date( 'Y-m-d' ) ] );
            self::input( $post, 'crg_request_link', 'Support request link', [ 'type' => 'url', 'placeholder' => 'https://wpmudev.com/…', 'help' => 'The member\'s ticket or forum thread. The report ends with "Reply on your support request" linking here.', 'attrs' => 'data-crg-url="link" autocomplete="off"' ] );
            self::input( $post, 'crg_task_url', 'Task URL', [ 'type' => 'url', 'placeholder' => 'https://…', 'help' => 'Internal: our task for this cleanup. Not shown in the report.', 'attrs' => 'data-crg-url="link" autocomplete="off"', 'class' => 'crg-col-right' ] );
            ?>
        </div>
        <?php
    }

    /* ---------- 2. Findings */

    public static function box_findings( $post ) {
        $screenshots = get_post_meta( $post->ID, 'crg_screenshots', true );
        if ( ! is_array( $screenshots ) ) {
            $screenshots = [];
            foreach ( [ 'before' => 'crg_ss_before', 'after' => 'crg_ss_after' ] as $type => $key ) { // reports from 1.x
                if ( self::meta( $post, $key ) ) {
                    $screenshots[] = [ 'url' => self::meta( $post, $key ), 'title' => 'Security scan – ' . $type . ' cleanup', 'type' => $type ];
                }
            }
        }
        if ( ! $screenshots && 'auto-draft' === $post->post_status ) {
            $screenshots = [
                [ 'url' => '', 'title' => 'Security scan – before cleanup', 'type' => 'before' ],
                [ 'url' => '', 'title' => 'Security scan – after cleanup', 'type' => 'after' ],
            ];
        }
        ?>
        <div class="crg-field">
            <span class="crg-field-label">Screenshots</span>
            <p class="description">Scan results before and after the cleanup, plus any evidence. Shown in the report in this order; drag to reorder.</p>
            <div class="crg-shots" data-next="<?php echo count( $screenshots ); ?>">
                <?php foreach ( array_values( $screenshots ) as $i => $shot ) { self::screenshot_row( $i, $shot ); } ?>
            </div>
            <template class="crg-shot-template"><?php self::screenshot_row( '__i__', [ 'type' => 'evidence' ] ); ?></template>
            <button type="button" class="button crg-shot-add"><span class="dashicons dashicons-format-image"></span> Add screenshot</button>
        </div>

        <?php self::editor( $post, 'crg_infection_summary', 'Infection summary', 'What was found and how the site was compromised. The first sentence becomes the <strong>Key finding</strong> on the summary page.', 8 ); ?>

        <div class="crg-field">
            <span class="crg-field-label">Issues found</span>
            <p class="description">The numbers on the summary page.</p>
            <div class="crg-cols crg-cols-3 crg-counts">
                <?php
                self::input( $post, 'crg_summary_critical', 'Critical', [ 'type' => 'number', 'default' => 0, 'help' => 'Malware and backdoors removed', 'attrs' => 'min="0" step="1"' ] );
                self::input( $post, 'crg_summary_medium', 'Medium', [ 'type' => 'number', 'default' => 0, 'help' => 'Vulnerable components <button type="button" class="button-link crg-count-components" hidden>use the table below</button>', 'attrs' => 'min="0" step="1"' ] );
                self::input( $post, 'crg_summary_low', 'Low', [ 'type' => 'number', 'default' => 0, 'help' => 'Hardening issues', 'attrs' => 'min="0" step="1"' ] );
                ?>
            </div>
        </div>

        <?php self::plugin_check_panel( $post ); ?>

        <div class="crg-field crg-components">
            <span class="crg-field-label">Vulnerable &amp; outdated components</span>
            <p class="description">Plugins and themes with known vulnerabilities or no longer maintained.</p>
            <table class="crg-table hide-if-no-js">
                <thead><tr><th class="crg-col-drag"></th><th class="crg-col-name">Plugin / theme</th><th class="crg-col-version">Version</th><th>Issue</th><th class="crg-col-sev">Severity</th><th class="crg-col-x"></th></tr></thead>
                <tbody class="crg-components-rows"></tbody>
            </table>
            <p class="crg-components-empty hide-if-no-js description">No components listed.</p>
            <div class="crg-components-warn notice notice-warning inline" hidden><p></p></div>
            <div class="crg-components-text">
                <textarea id="crg_components" name="crg_components" rows="5" class="code" placeholder="Slider Revolution | 6.8.1 | 5 known vulnerabilities; update to 7.1.0+ | critical"><?php echo esc_textarea( self::meta( $post, 'crg_components' ) ); ?></textarea>
                <p class="description">One per line: <code>Name | Version | Issue | Severity</code> (critical, high, medium or low).</p>
            </div>
            <p class="hide-if-no-js">
                <button type="button" class="button crg-component-add"><span class="dashicons dashicons-plus-alt2"></span> Add component</button>
                <button type="button" class="button-link crg-components-toggle">Edit as text</button>
            </p>
        </div>

        <?php self::editor( $post, 'crg_improvements', 'Suggested improvements & hardening', 'Optional: changes that would make the site harder to attack (fewer plugins, replacing risky ones…).', 5 ); ?>
        <?php
    }

    /** "Check plugins & themes": paste `wp plugin list` output, see what's abandoned or vulnerable, add it to the report. */
    private static function plugin_check_panel( $post ) {
        $saved = (string) self::meta( $post, 'crg_plugin_list' );
        ?>
        <details class="crg-pc crg-field hide-if-no-js" data-post="<?php echo (int) $post->ID; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'crg_check_plugins' ) ); ?>" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"<?php echo $saved ? '' : ' open'; ?>>
            <summary><span class="dashicons dashicons-search"></span> <strong>Check plugins &amp; themes</strong> <span class="crg-pc-sub">Find what's abandoned, vulnerable or out of date, and add it to the report.</span></summary>
            <ol class="crg-pc-steps">
                <li>On the member's server, in the WordPress folder, run:
                    <div class="crg-pc-cmd"><code><?php echo esc_html( Cleanup_Report_Plugin_Check::COMMAND ); ?></code><button type="button" class="button button-small crg-pc-copy">Copy</button></div>
                </li>
                <li><label for="crg_plugin_list">Paste the output here</label> (the default table from <code>wp plugin list</code> works too):
                    <textarea id="crg_plugin_list" name="crg_plugin_list" rows="4" class="code" placeholder="name,title,version,update_version,status&#10;revslider,&quot;Slider Revolution&quot;,6.8.1,,active"><?php echo esc_textarea( $saved ); ?></textarea>
                </li>
            </ol>
            <p class="crg-pc-bar">
                <button type="button" class="button button-primary crg-pc-run">Check</button>
                <span class="spinner"></span>
                <span class="crg-pc-msg" aria-live="polite"></span>
            </p>
            <div class="crg-pc-results"></div>
            <p class="description">Each folder name is looked up on WordPress.org (closed, last release, "Tested up to") and WPVulnerability.net (known vulnerabilities in the installed version); results are cached for 12 hours. Everything checked is added to the <?php echo isset( Cleanup_Report_Settings::tabs()['plugins'] ) ? '<a href="' . esc_url( Cleanup_Report_Settings::url( 'plugins' ) ) . '">plugin list</a>' : 'team\'s plugin list'; ?>, where premium items are marked as maintained or not.</p>
        </details>
        <?php
    }

    private static function screenshot_row( $i, array $shot ) {
        $name = 'crg_screenshots[' . $i . ']';
        $url  = $shot['url'] ?? '';
        ?>
        <div class="crg-shot">
            <span class="crg-drag dashicons dashicons-menu" title="Drag to reorder"></span>
            <button type="button" class="crg-shot-thumb crg-media" data-target="<?php echo esc_attr( '[name="' . $name . '[url]"]' ); ?>" title="Choose from the media library">
                <?php if ( $url ) : ?><img src="<?php echo esc_url( $url ); ?>" alt=""><?php else : ?><span class="dashicons dashicons-format-image"></span><?php endif; ?>
            </button>
            <div class="crg-shot-fields">
                <input type="text" name="<?php echo esc_attr( $name . '[title]' ); ?>" value="<?php echo esc_attr( $shot['title'] ?? '' ); ?>" placeholder="Caption, e.g. Defender scan after cleanup" class="crg-shot-title" aria-label="Caption">
                <div class="crg-shot-line">
                    <select name="<?php echo esc_attr( $name . '[type]' ); ?>" aria-label="Type">
                        <?php foreach ( [ 'before' => 'Before', 'after' => 'After', 'evidence' => 'Evidence', 'other' => 'Other' ] as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $shot['type'] ?? 'other', $value ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="url" name="<?php echo esc_attr( $name . '[url]' ); ?>" value="<?php echo esc_attr( $url ); ?>" placeholder="Image URL, or choose from the media library" class="crg-shot-url" aria-label="Image URL">
                    <button type="button" class="button crg-media" data-target="<?php echo esc_attr( '[name="' . $name . '[url]"]' ); ?>">Choose</button>
                </div>
            </div>
            <button type="button" class="button-link crg-remove" title="Remove"><span class="dashicons dashicons-no-alt"></span><span class="screen-reader-text">Remove screenshot</span></button>
        </div>
        <?php
    }

    /* ---------- 3. Work done & verification */

    public static function box_work( $post ) {
        $default = 'auto-draft' === $post->post_status ? Cleanup_Report_Render::default_texts()['verification'] : '';
        self::editor( $post, 'crg_highlights', 'Work carried out', 'What we did to clean the site. Shown under <strong>Actions taken</strong>, before the list of standard steps every cleanup includes.', 6 );
        self::editor( $post, 'crg_verification_summary', 'Verification', 'How the site was checked after the cleanup, and anything still showing.', 6, $default );
        ?>
        <div class="crg-cols crg-cols-2">
            <?php
            self::input( $post, 'crg_additional_sites', 'Additional sites on this hosting account', [ 'type' => 'textarea', 'rows' => 3, 'placeholder' => 'No problem identified', 'help' => 'Other sites found and what was done about them, one per line. Leave empty for "No problem identified".' ] );
            self::input( $post, 'crg_delist', 'Security blocklists (delist request)', [ 'type' => 'textarea', 'rows' => 3, 'placeholder' => 'No problem identified', 'help' => 'Where the site was listed and what was requested, one per line. Leave empty for "No problem identified".' ] );
            ?>
        </div>
        <?php
    }

    /* ---------- 4. Defender */

    public static function box_defender( $post ) {
        $answers = Cleanup_Report_Checklists::report_defender( $post->ID );
        $items   = Cleanup_Report_Checklists::form_items( Cleanup_Report_Checklists::defender_items(), $answers );
        ?>
        <div class="crg-checklist" data-kind="defender">
            <div class="crg-checklist-bar">
                <p class="description"><strong>Applied</strong>: we configured it. <strong>Pending</strong>: not configured yet. <strong>Member to do</strong>: the member has to finish it. Click a selected button again to leave the setting out of the report.</p>
                <span class="crg-checklist-count" aria-live="polite"></span>
                <button type="button" class="button button-small crg-set-all" data-status="applied">Mark all applied</button>
                <button type="button" class="button-link crg-set-all" data-status="">Clear</button>
            </div>
            <?php if ( ! $items ) : ?>
                <p class="crg-empty">No Defender settings are set up. <?php self::settings_link( 'defender', 'Add them in Settings' ); ?></p>
            <?php endif; ?>
            <?php
            foreach ( $items as $key => $item ) {
                $answer = $answers[ $key ] ?? [];
                self::check_row( 'crg_defender', $key, $item, $answer['status'] ?? '', $answer['notes'] ?? '', 'notes', [
                    ''            => 'Leave out', // hidden: click the selected button again to clear
                    'applied'     => 'Applied',
                    'in_progress' => 'Pending',
                    'pending'     => 'Member to do',
                ], [
                    ''            => 'Not in the report',
                    'applied'     => ( $item['applied_note'] ?? '' ) ?: 'Configured to recommended settings.',
                    'in_progress' => ( $item['progress_note'] ?? '' ) ?: Cleanup_Report_Checklists::PROGRESS_FALLBACK,
                    'pending'     => ( $item['pending_note'] ?? '' ) ?: 'Note for the member: what they need to do',
                ], true );
            }
            ?>
            <p class="crg-checklist-foot"><?php self::settings_link( 'defender', 'Edit the Defender checklist' ); ?></p>
        </div>
        <?php
    }

    /* ---------- 5. Member next steps */

    public static function box_next( $post ) {
        $answers = Cleanup_Report_Checklists::report_followups( $post->ID );
        $items   = Cleanup_Report_Checklists::form_items( Cleanup_Report_Checklists::followup_items(), $answers );
        self::input( $post, 'crg_pending_actions', 'Pending actions', [
            'type'        => 'textarea',
            'rows'        => 4,
            'placeholder' => "1. Review the two files Defender still reports in the site root.\n2. …",
            'help'        => 'Anything the member must still do, one per line (numbered or "•" lines become a list). Leave empty if nothing is pending.',
        ] );
        ?>
        <div class="crg-field">
            <span class="crg-field-label">Follow-ups</span>
            <div class="crg-checklist" data-kind="followups">
                <div class="crg-checklist-bar">
                    <p class="description">Shown as <em>Recommended actions</em>. <strong>Already configured</strong>: in place before the cleanup. <strong>Done</strong>: we did it during the cleanup. <strong>Member to do</strong>: the member needs to act. Click a selected button again to leave the item out of the report.</p>
                    <span class="crg-checklist-count" aria-live="polite"></span>
                </div>
                <?php if ( ! $items ) : ?>
                    <p class="crg-empty">No follow-ups are set up. <?php self::settings_link( 'followups', 'Add them in Settings' ); ?></p>
                <?php endif; ?>
                <?php
                foreach ( $items as $key => $item ) {
                    $answer = $answers[ $key ] ?? [];
                    self::check_row( 'crg_followups', $key, $item, $answer['status'] ?? '', $answer['note'] ?? '', 'note', [
                        ''             => 'Leave out', // hidden: click the selected button again to clear
                        'not_required' => 'Already configured',
                        'fixed'        => 'Done',
                        'required'     => 'Member to do',
                    ], [
                        ''             => 'Not in the report',
                        'not_required' => $item['done'] ?? '',
                        'fixed'        => ( $item['fixed'] ?? '' ) ?: ( Cleanup_Report_Checklists::DEFAULT_FIXED[ $key ] ?? Cleanup_Report_Checklists::FIXED_FALLBACK ),
                        'required'     => $item['todo'] ?? '',
                    ], true );
                }
                ?>
                <p class="crg-checklist-foot"><?php self::settings_link( 'followups', 'Edit the follow-ups list' ); ?></p>
            </div>
        </div>

        <?php
            $list_help = 'A bulleted list, one per item. Put the folder in brackets and a reason after a dash, e.g. <em>Slider Revolution (revslider) – no licence provided</em>.';
            self::editor( $post, 'crg_reinstall_list', 'Reinstall: premium plugins & themes not provided', $list_help, 4 );
            self::editor( $post, 'crg_replace_list', 'Replace: plugins & themes no longer maintained', $list_help, 4 );
    }

    /**
     * One checklist row: name and hint, a segmented status control, and a note whose placeholder shows what the
     * report will say for the chosen status.
     */
    private static function check_row( $field, $key, array $item, $status, $note, $note_field, array $choices, array $previews, $hide_none = false ) {
        $name = $field . '[' . $key . ']';
        ?>
        <div class="crg-check<?php echo $status ? ' is-' . esc_attr( $status ) : ''; ?>" data-previews="<?php echo esc_attr( wp_json_encode( $previews ) ); ?>">
            <div class="crg-check-name">
                <strong><?php echo esc_html( $item['label'] ); ?></strong>
                <?php if ( 'recommended' === ( $item['tone'] ?? '' ) ) : ?><span class="crg-tag" title="Shown in the report as Recommended rather than Required">Recommended</span><?php endif; ?>
                <?php if ( ! empty( $item['orphan'] ) ) : ?><span class="crg-tag" title="Kept because this report already uses it"><?php echo esc_html( 'off' === $item['orphan'] ? 'Switched off in settings' : 'Removed from settings' ); ?></span><?php endif; ?>
                <?php if ( ! empty( $item['hint'] ) ) : ?><span class="crg-check-hint"><?php echo esc_html( $item['hint'] ); ?></span><?php endif; ?>
                <?php if ( ! empty( $item['docs'] ) ) : ?><a class="crg-check-docs" href="<?php echo esc_url( $item['docs'] ); ?>" target="_blank" rel="noopener">Docs<span class="screen-reader-text"> (opens in a new tab)</span></a><?php endif; ?>
                <?php if ( ! empty( $item['link'] ) ) : ?><a class="crg-check-docs crg-check-link" data-link="<?php echo esc_attr( $item['link'] ); ?>" href="#" target="_blank" rel="noopener" hidden>Open on site<span class="screen-reader-text"> (opens in a new tab)</span></a><?php endif; ?>
            </div>
            <div class="crg-seg" role="radiogroup" aria-label="<?php echo esc_attr( $item['label'] ); ?>">
                <?php foreach ( $choices as $value => $label ) : ?>
                    <label class="crg-seg-<?php echo esc_attr( $value ?: 'none' ); ?>"<?php echo ( $hide_none && '' === $value ) ? ' hidden' : ''; ?>><input type="radio" name="<?php echo esc_attr( $name . '[status]' ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( $status, $value ); ?>><span><?php echo esc_html( $label ); ?></span></label>
                <?php endforeach; ?>
            </div>
            <input type="text" class="crg-check-note" name="<?php echo esc_attr( $name . '[' . $note_field . ']' ); ?>" value="<?php echo esc_attr( $note ); ?>" placeholder="<?php echo esc_attr( $previews[ $status ] ?? '' ); ?>" aria-label="<?php echo esc_attr( 'Note for ' . $item['label'] ); ?>">
        </div>
        <?php
    }

    private static function settings_link( $tab, $text ) {
        if ( isset( Cleanup_Report_Settings::tabs()[ $tab ] ) ) {
            printf( '<a href="%s">%s</a>', esc_url( Cleanup_Report_Settings::url( $tab ) ), esc_html( $text ) );
        }
    }

    /* ---------- 6. Member guidance */

    public static function box_guidance( $post ) {
        $host     = wp_parse_url( (string) self::meta( $post, 'crg_site_url' ), PHP_URL_HOST ) ?: '';
        $defaults = Cleanup_Report_Render::default_texts( $host );
        self::input( $post, 'crg_documentation_links', 'Helpful links', [
            'type'        => 'textarea',
            'rows'        => 3,
            'placeholder' => "Supported PHP versions: https://www.php.net/supported-versions.php",
            'help'        => 'One per line as <code>Title: https://…</code>. Links to the Defender docs, 2FA and login masking are always added.',
        ] );
        ?>
        <?php
        self::note_editor( $post, 'crg_seo_note', 'Search engines (SEO)', 'What to check in Google and how to clean up search results. Leave empty to use the standard text.', $defaults['seo'] );
        self::note_editor( $post, 'crg_protect_note', 'Staying protected', 'How to keep the site safe from now on. Leave empty to use the standard text.', $defaults['protect'] );
    }

    /**
     * A rich-text note with the standard text (used when it's left empty) shown underneath, ready to copy in.
     * Notes saved before 2.1 are plain text; they're converted so their lists and headings carry over.
     */
    private static function note_editor( $post, $key, $label, $help, $standard ) {
        $value = (string) self::meta( $post, $key );
        if ( '' !== trim( $value ) && ! preg_match( '/<[a-z]/i', $value ) ) {
            $value = Cleanup_Report_Render::text_to_html( $value );
        }
        ?>
        <div class="crg-field crg-field-editor">
            <label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
            <p class="description"><?php echo esc_html( $help ); ?></p>
            <?php wp_editor( $value, $key, [ 'textarea_name' => $key, 'textarea_rows' => 6 ] + self::EDITOR ); ?>
            <details class="crg-std">
                <summary>Standard text, used when this is empty</summary>
                <div class="crg-std-text"><?php echo wp_kses_post( Cleanup_Report_Render::note_html( $standard ) ); ?></div>
                <button type="button" class="button button-small crg-use-std" data-editor="<?php echo esc_attr( $key ); ?>">Copy into the editor to change it</button>
            </details>
        </div>
        <?php
    }

    /* ---------- side: preview / PDF */

    public static function box_export( $post ) {
        if ( 'auto-draft' === $post->post_status ) {
            echo '<p class="crg-muted">Save the report to preview it or download the PDF.</p>';
            return;
        }
        $view = wp_nonce_url( admin_url( 'admin-post.php?action=crg_view_report&post=' . $post->ID ), 'crg_view_' . $post->ID );
        $pdf  = wp_nonce_url( admin_url( 'admin-post.php?action=crg_export_pdf&post=' . $post->ID ), 'crg_pdf_' . $post->ID );
        ?>
        <div class="crg-export">
            <?php if ( self::can_download( $post->ID ) ) : ?>
                <a class="button button-primary crg-export-pdf" href="<?php echo esc_url( $pdf ); ?>"><span class="dashicons dashicons-pdf"></span> Download PDF</a>
            <?php else : ?>
                <span class="button button-primary crg-export-pdf disabled" aria-disabled="true"><span class="dashicons dashicons-pdf"></span> Download PDF</span>
            <?php endif; ?>
            <a class="button" href="<?php echo esc_url( $view ); ?>" target="_blank" rel="noopener">Preview in browser</a>
        </div>
        <?php if ( ! self::can_download( $post->ID ) ) : ?>
            <p class="crg-muted crg-export-locked">The PDF can be downloaded once the report is approved. Until then the preview is marked <strong><?php echo esc_html( self::watermark() ); ?></strong>.</p>
        <?php endif; ?>
        <p class="crg-muted crg-export-note">Uses the last saved version.</p>
        <p class="crg-dirty" hidden><span class="dashicons dashicons-warning"></span> You have unsaved changes. Save to include them.</p>
        <?php
    }

    /* ---------- side: review */

    private static function status_badge( $status ) {
        printf( '<span class="crg-status-badge crg-status-%s">%s</span>', esc_attr( $status ), esc_html( self::STATUSES[ $status ] ?? $status ) );
    }

    private static function review_dates( $post ) {
        foreach ( [ 'submitted' => 'Submitted', 'reviewed' => 'Approved', 'completed' => 'Completed' ] as $key => $label ) {
            $date = get_post_meta( $post->ID, 'crg_' . $key . '_date', true );
            $by   = get_userdata( (int) get_post_meta( $post->ID, 'crg_' . $key . '_by', true ) );
            if ( $date && strtotime( $date ) ) {
                printf( '<p class="crg-muted">%s %s%s</p>', esc_html( $label ), $by ? 'by ' . esc_html( $by->display_name ) . ', ' : '', esc_html( mysql2date( 'j M Y, H:i', $date ) ) );
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
        return trim( (string) get_option( 'crg_watermark_text', 'NOT FINAL' ) ) ?: 'NOT FINAL';
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
            return 'draft' === $current ? 'Save draft' : 'Save';
        }
        $labels = [
            'draft'          => 'Move back to draft',
            'submitted'      => 'needs_revision' === $current ? 'Resubmit for review' : 'Submit for review',
            'needs_revision' => 'Send back for revision',
            'approved'       => 'Approve',
            'complete'       => 'Mark completed',
        ];
        return $labels[ $target ] ?? 'Save';
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
                <div class="crg-note-box is-review"><strong>Buddylead's notes</strong><br><?php echo nl2br( esc_html( $review_notes ) ); ?></div>
            <?php endif; ?>
            <?php if ( $author_notes && $lead ) : ?>
                <div class="crg-note-box"><strong>Notes from the author</strong><br><?php echo nl2br( esc_html( $author_notes ) ); ?></div>
            <?php endif; ?>
            <p>
                <label for="crg_review_status"><strong>Status</strong></label>
                <select id="crg_review_status" name="crg_review_status" class="widefat" data-current="<?php echo esc_attr( $status ); ?>">
                    <?php foreach ( $allowed as $value ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" data-label="<?php echo esc_attr( self::save_label( $value, $status ) ); ?>" data-post-status="<?php echo esc_attr( self::POST_STATUS[ $value ] ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( self::STATUSES[ $value ] ); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p class="crg-muted">
                <?php if ( 'approved' === $status ) : ?>
                    Approved. Once the report is sent to the member, mark it Completed.
                <?php elseif ( $lead ) : ?>
                    <?php echo current_user_can( 'manage_options' ) ? 'As an administrator' : 'As a Buddylead'; ?>, you can approve this report or send it back for changes.
                <?php else : ?>
                    Buddyleads<?php echo $names ? ' (' . esc_html( implode( ', ', $names ) ) . ')' : ''; ?> and administrators approve reports. Submit it for review when it's ready.
                <?php endif; ?>
            </p>
            <?php if ( $lead ) : ?>
                <p>
                    <label for="crg_review_notes"><strong>Notes for the author</strong></label>
                    <textarea id="crg_review_notes" name="crg_review_notes" rows="3" class="widefat" placeholder="What needs changing, or why it's approved"><?php echo esc_textarea( $review_notes ); ?></textarea>
                </p>
            <?php endif; ?>
            <?php if ( in_array( $status, [ 'draft', 'needs_revision' ], true ) ) : ?>
                <p>
                    <label for="crg_author_notes"><strong>Notes for the Buddylead</strong></label>
                    <textarea id="crg_author_notes" name="crg_author_notes" rows="2" class="widefat" placeholder="Anything they should check"><?php echo esc_textarea( $author_notes ); ?></textarea>
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
                    <a class="crg-trash" href="<?php echo esc_url( get_delete_post_link( $post->ID ) ); ?>">Move to Trash</a>
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
        if ( isset( $_POST['crg_author_notes'] ) ) {
            update_post_meta( $post_id, 'crg_author_notes', wp_slash( sanitize_textarea_field( wp_unslash( $_POST['crg_author_notes'] ) ) ) );
        }
        if ( isset( $_POST['crg_review_notes'] ) && self::is_buddylead() ) {
            update_post_meta( $post_id, 'crg_review_notes', wp_slash( sanitize_textarea_field( wp_unslash( $_POST['crg_review_notes'] ) ) ) );
        }

        // Screenshots.
        if ( isset( $_POST['crg_screenshots'] ) && is_array( $_POST['crg_screenshots'] ) ) {
            $screenshots = [];
            // Each part is sanitised below; wp_unslash() first, wp_slash() before saving (update_post_meta() unslashes).
            foreach ( wp_unslash( $_POST['crg_screenshots'] ) as $shot ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                if ( is_array( $shot ) && ! empty( $shot['url'] ) ) {
                    $type          = sanitize_key( $shot['type'] ?? 'other' );
                    $screenshots[] = [
                        'url'   => esc_url_raw( $shot['url'] ),
                        'title' => sanitize_text_field( $shot['title'] ?? '' ),
                        'type'  => in_array( $type, [ 'before', 'after', 'evidence', 'other' ], true ) ? $type : 'other',
                    ];
                }
            }
            update_post_meta( $post_id, 'crg_screenshots', wp_slash( $screenshots ) );
        } elseif ( isset( $_POST['crg_client_name'] ) ) {
            update_post_meta( $post_id, 'crg_screenshots', [] ); // the form was submitted with every screenshot removed
        }

        // Checklists (wording comes from settings, not from the request).
        Cleanup_Report_Checklists::save_report(
            $post_id,
            isset( $_POST['crg_followups'] ) ? map_deep( wp_unslash( $_POST['crg_followups'] ), 'sanitize_text_field' ) : null,
            isset( $_POST['crg_defender'] ) ? map_deep( wp_unslash( $_POST['crg_defender'] ), 'sanitize_text_field' ) : null
        );

        $fields = [
            'text'  => [ 'crg_client_name', 'crg_report_date' ],
            'url'   => [ 'crg_site_url', 'crg_request_link', 'crg_task_url' ],
            'int'   => [ 'crg_summary_critical', 'crg_summary_medium', 'crg_summary_low' ],
            'plain' => [ 'crg_plugin_list', 'crg_components', 'crg_pending_actions', 'crg_documentation_links', 'crg_delist', 'crg_additional_sites' ],
            'html'  => [ 'crg_infection_summary', 'crg_highlights', 'crg_improvements', 'crg_verification_summary', 'crg_reinstall_list', 'crg_replace_list', 'crg_seo_note', 'crg_protect_note' ],
        ];
        foreach ( $fields as $type => $keys ) {
            foreach ( $keys as $key ) {
                if ( ! isset( $_POST[ $key ] ) ) {
                    continue;
                }
                $raw = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised by type below
                switch ( $type ) {
                    case 'int':
                        $value = max( 0, (int) $raw );
                        break;
                    case 'url':
                        $value = esc_url_raw( self::normalize_url( sanitize_text_field( $raw ), 'crg_site_url' === $key ) );
                        break;
                    case 'plain':
                        $value = sanitize_textarea_field( $raw );
                        break;
                    case 'html':
                        $value = wp_kses_post( $raw );
                        break;
                    default:
                        $value = sanitize_text_field( $raw );
                }
                update_post_meta( $post_id, $key, wp_slash( $value ) );
            }
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
        $date = $columns['date'] ?? 'Date';
        unset( $columns['date'] );
        return $columns + [
            'crg_client' => 'Prepared for',
            'crg_site'   => 'Website',
            'crg_review' => 'Review',
            'crg_date'   => 'Report date',
            'date'       => $date,
        ];
    }

    public static function column( $column, $post_id ) {
        switch ( $column ) {
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
