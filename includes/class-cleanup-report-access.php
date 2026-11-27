<?php
/**
 * Who can do what with reports. Three groups, independent of the general WordPress roles beyond "can write posts":
 *
 *  - Writers (Editor role; WordPress needs it to save someone else's report, e.g. to mark it Completed): create and edit their own reports, submit them for review, preview / download
 *    the PDF of any report. An approved report is read-only for them except for marking it Completed, which they
 *    can do on any approved report.
 *  - Buddyleads (picked by name in Reports › Settings › Review): open and edit anyone's report, approve or send
 *    back, and manage the Follow-ups, Defender checklist and Plugin list settings tabs.
 *  - Administrators: full access, including approving and every setting (only they see Branding and Review).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Cleanup_Report_Access {

    public static function init() {
        add_filter( 'map_meta_cap', [ __CLASS__, 'map_meta_cap' ], 10, 4 );
        add_filter( 'is_protected_meta', [ __CLASS__, 'protect_meta' ], 10, 3 );
        add_filter( 'wp_insert_post_data', [ __CLASS__, 'keep_author' ], 20, 2 );
        add_filter( 'wp_insert_post_data', [ __CLASS__, 'keep_locked_title' ], 20, 2 );
        add_filter( 'admin_body_class', [ __CLASS__, 'body_class' ] );
        add_action( 'edit_form_after_title', [ __CLASS__, 'locked_notice' ], 5 );
    }

    public static function is_admin_user( $user_id = 0 ) {
        return user_can( $user_id ?: get_current_user_id(), 'manage_options' );
    }

    /** Admin or Buddylead: may open and change any report. */
    public static function can_manage( $user_id = 0 ) {
        return self::is_admin_user( $user_id ) || Cleanup_Report_Admin::is_buddylead( $user_id );
    }

    /** May open the Plugin list tab (Buddyleads and admins). */
    public static function can_manage_plugin_list( $user_id = 0 ) {
        return self::can_manage( $user_id );
    }

    /** May edit the Follow-ups and Defender checklists (Buddyleads and admins). */
    public static function can_manage_checklists( $user_id = 0 ) {
        return self::can_manage( $user_id );
    }

    /** Approved or completed, and this user is a Writer: read-only except for marking it Completed. */
    public static function locked( $post_id, $user_id = 0 ) {
        return 'cleanup_report' === get_post_type( $post_id )
            && in_array( Cleanup_Report_Admin::status( $post_id ), [ 'approved', 'complete' ], true )
            && ! self::can_manage( $user_id );
    }

    /**
     * Editing and deleting reports. Admins keep WordPress's own rules; Buddyleads can edit and delete any report;
     * Writers their own; anyone with access can open an approved report to mark it Completed.
     */
    public static function map_meta_cap( $caps, $cap, $user_id, $args ) {
        if ( ! in_array( $cap, [ 'edit_post', 'delete_post' ], true ) || empty( $args[0] ) ) {
            return $caps;
        }
        $post = get_post( $args[0] );
        if ( ! $post || 'cleanup_report' !== $post->post_type || 'auto-draft' === $post->post_status ) {
            return $caps;
        }
        if ( self::is_admin_user( $user_id ) ) {
            return $caps;
        }
        if ( Cleanup_Report_Admin::is_buddylead( $user_id ) ) {
            return [ 'edit_post' === $cap ? 'edit_posts' : 'delete_posts' ];
        }
        if ( 'delete_post' === $cap && self::locked( $post->ID, $user_id ) ) {
            return [ 'do_not_allow' ]; // approved or completed: read-only for Writers, so not deletable either
        }
        if ( (int) $post->post_author === (int) $user_id ) {
            return $caps;
        }
        if ( 'edit_post' === $cap && in_array( Cleanup_Report_Admin::status( $post->ID ), [ 'approved', 'complete' ], true ) ) {
            return [ 'edit_posts' ]; // open it to mark it Completed; everything else is locked
        }
        return [ 'do_not_allow' ];
    }

    /**
     * Report fields (crg_*) are written only by this plugin, through its checks (CRG_Admin::save_meta()). Protected
     * meta keeps WordPress's generic custom-field paths away from them: the hidden custom-field inputs every post save
     * accepts, Quick Edit, admin-ajax add-meta and XML-RPC. Without this a Writer could set crg_review_status to
     * "approved" on their own report, or change an approved one.
     */
    public static function protect_meta( $protected, $key, $type ) {
        return $protected || ( 'post' === $type && 0 === strpos( (string) $key, 'crg_' ) );
    }

    /**
     * Only Buddyleads and administrators can change who a report belongs to. Editors may otherwise reassign it (the
     * edit form, Quick Edit and Bulk Edit all accept an author), e.g. to take over a colleague's report and delete it.
     */
    public static function keep_author( $data, $postarr ) {
        if ( 'cleanup_report' === $data['post_type'] && ! empty( $postarr['ID'] ) && ! self::can_manage() ) {
            $author = get_post_field( 'post_author', (int) $postarr['ID'], 'raw' );
            if ( '' !== (string) $author ) {
                $data['post_author'] = (int) $author;
            }
        }
        return $data;
    }

    /** A locked report keeps its title (the meta boxes are skipped in Cleanup_Report_Admin::save_meta()). */
    public static function keep_locked_title( $data, $postarr ) {
        if ( 'cleanup_report' === $data['post_type'] && ! empty( $postarr['ID'] ) && self::locked( (int) $postarr['ID'] ) ) {
            $data['post_title'] = get_post_field( 'post_title', (int) $postarr['ID'], 'raw' );
        }
        return $data;
    }

    public static function body_class( $classes ) {
        global $post;
        if ( $post && 'cleanup_report' === $post->post_type && self::locked( $post->ID ) ) {
            $classes .= ' crg-locked';
        }
        return $classes;
    }

    public static function locked_notice( $post ) {
        if ( 'cleanup_report' === $post->post_type && self::locked( $post->ID ) ) {
            echo '<div class="notice notice-info inline crg-locked-notice"><p><strong>This report is approved, so it can\'t be changed.</strong> Once it\'s been sent to the member, set its status to Completed in the Save &amp; review box. A Buddylead can reopen it if something needs fixing.</p></div>';
        }
    }
}
