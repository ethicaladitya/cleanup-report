<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once CLEANUP_REPORT_PATH . 'includes/class-cleanup-report-cpt.php';
require_once CLEANUP_REPORT_PATH . 'includes/class-cleanup-report-types.php';
require_once CLEANUP_REPORT_PATH . 'includes/class-cleanup-report-optimization.php';
require_once CLEANUP_REPORT_PATH . 'includes/class-cleanup-report-access.php';
require_once CLEANUP_REPORT_PATH . 'includes/class-cleanup-report-checklists.php';
require_once CLEANUP_REPORT_PATH . 'includes/class-cleanup-report-settings.php';
require_once CLEANUP_REPORT_PATH . 'includes/class-cleanup-report-plugin-check.php';
require_once CLEANUP_REPORT_PATH . 'includes/class-cleanup-report-plugin-registry.php';
require_once CLEANUP_REPORT_PATH . 'includes/class-cleanup-report-retention.php';
require_once CLEANUP_REPORT_PATH . 'includes/class-cleanup-report-admin.php';
require_once CLEANUP_REPORT_PATH . 'includes/class-cleanup-report-render.php';

class Cleanup_Report_Generator {

    public static function init() {
        add_action( 'init', [ 'Cleanup_Report_CPT', 'register' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_assets' ] );
        add_filter( 'post_row_actions', [ __CLASS__, 'row_actions' ], 10, 2 );
        add_action( 'admin_post_crg_view_report', [ __CLASS__, 'handle_view_report' ] );
        add_action( 'admin_post_crg_export_pdf', [ __CLASS__, 'handle_export_pdf' ] );
        Cleanup_Report_Types::init();
        Cleanup_Report_Access::init();
        Cleanup_Report_Admin::init();
        Cleanup_Report_Settings::init();
        Cleanup_Report_Plugin_Check::init();
        Cleanup_Report_Plugin_Registry::init();
        Cleanup_Report_Retention::init();

        // Review workflow hooks
        add_action( 'admin_notices', [ __CLASS__, 'review_admin_notices' ] );
        add_filter( 'post_updated_messages', [ __CLASS__, 'review_post_messages' ] );
        
        // PDF status notice
        add_action( 'admin_notices', [ 'Cleanup_Report_PDF', 'show_library_notice' ] );
    }

    public static function admin_assets( $hook ) {
        $screen   = get_current_screen();
        $edit     = $screen && 'cleanup_report' === $screen->post_type && in_array( $hook, [ 'post.php', 'post-new.php', 'edit.php' ], true );
        $settings = 'cleanup_report_page_' . Cleanup_Report_Settings::SLUG === $hook;
        if ( ! $edit && ! $settings ) {
            return;
        }
        $deps = [ 'jquery', 'jquery-ui-sortable' ];
        if ( 'edit.php' !== $hook ) {
            wp_enqueue_media();
        }
        if ( $settings ) {
            wp_enqueue_style( 'wp-color-picker' );
            $deps[] = 'wp-color-picker';
        }
        wp_enqueue_style( 'crg-admin', CLEANUP_REPORT_URL . 'assets/admin.css', [], CLEANUP_REPORT_VERSION . '.' . filemtime( CLEANUP_REPORT_PATH . 'assets/admin.css' ) );
        wp_enqueue_script( 'crg-admin', CLEANUP_REPORT_URL . 'assets/admin.js', $deps, CLEANUP_REPORT_VERSION . '.' . filemtime( CLEANUP_REPORT_PATH . 'assets/admin.js' ), true );
    }

    public static function row_actions( $actions, $post ) {
        if ( $post->post_type !== 'cleanup_report' ) return $actions;
        $view = wp_nonce_url( admin_url('admin-post.php?action=crg_view_report&post='.$post->ID), 'crg_view_'.$post->ID );
        $pdf  = wp_nonce_url( admin_url('admin-post.php?action=crg_export_pdf&post='.$post->ID), 'crg_pdf_'.$post->ID );
        if ( Cleanup_Report_Admin::can_download( $post->ID ) ) {
            $actions['crg_pdf'] = '<a href="' . esc_url( $pdf ) . '">Download PDF</a>';
        }
        $actions['crg_view'] = '<a href="' . esc_url( $view ) . '" target="_blank" rel="noopener">Preview</a>';
        $task                = get_post_meta( $post->ID, 'crg_task_url', true );
        if ( $task ) {
            $actions['crg_task'] = '<a href="' . esc_url( $task ) . '" target="_blank" rel="noopener">Open task</a>';
        }
        return $actions;
    }

    public static function handle_view_report() {
        $post_id = self::authorize_report_request( 'crg_view_' );
        Cleanup_Report_Render::render_report_page( $post_id );
        exit;
    }

    public static function handle_export_pdf() {
        $post_id = self::authorize_report_request( 'crg_pdf_' );
        if ( ! Cleanup_Report_Admin::can_download( $post_id ) ) {
            wp_die( esc_html( 'This report can be downloaded once it\'s approved. Until then, use "Preview in browser" (marked ' . Cleanup_Report_Admin::watermark() . ').' ), 'Not approved yet', [ 'response' => 403, 'back_link' => true ] );
        }
        Cleanup_Report_Admin::log( $post_id, 'Downloaded the PDF' );
        Cleanup_Report_PDF::generate_pdf( $post_id );
    }

    /**
     * Nonce + capability + post-type check shared by the view and export
     * handlers. Returns the report ID or dies.
     */
    private static function authorize_report_request( $nonce_prefix ) {
        $post_id = absint( $_GET['post'] ?? 0 );
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), $nonce_prefix . $post_id ) ) {
            wp_die( 'This link has expired. Go back to the Reports list and try again.', 'Link expired', [ 'response' => 403, 'back_link' => true ] );
        }
        if ( 'cleanup_report' !== get_post_type( $post_id ) ) {
            wp_die( 'Report not found.', 'Not found', [ 'response' => 404, 'back_link' => true ] );
        }
        // Everyone with access to Reports may preview or download any report (handovers); editing is separate.
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( 'You are not allowed to view this report.', 'Forbidden', [ 'response' => 403, 'back_link' => true ] );
        }
        return $post_id;
    }

    public static function review_admin_notices() {
        global $post, $pagenow;

        if ( 'post.php' !== $pagenow || ! $post || 'cleanup_report' !== $post->post_type ) {
            return;
        }
        $status = Cleanup_Report_Admin::status( $post->ID );
        if ( 'submitted' === $status ) {
            echo Cleanup_Report_Admin::is_buddylead()
                ? '<div class="notice notice-info"><p><strong>Waiting for your review.</strong> Approve it, or send it back with notes, in the Save &amp; review box.</p></div>'
                : '<div class="notice notice-info"><p><strong>Submitted for review.</strong> A Buddylead will check this report; their notes will appear in the Save &amp; review box.</p></div>';
        }
        if ( 'needs_revision' === $status ) {
            $notes = get_post_meta( $post->ID, 'crg_review_notes', true );
            echo '<div class="notice notice-warning"><p><strong>This report needs changes before it can be sent.</strong>' . ( $notes ? '<br>' . nl2br( esc_html( $notes ) ) : '' ) . '</p></div>';
        }
    }

    public static function review_post_messages( $messages ) {
        // Saving happens in the Save & review box (WordPress's Publish box is removed), so whatever WordPress status
        // results, the message is "saved"; a review step gets its own message (Cleanup_Report_Admin::saved_message()).
        $saved = 'Report saved.';
        $messages['cleanup_report'] = [
            0  => '', // Unused. Messages start at index 1.
            1  => $saved,
            2  => 'Custom field updated.',
            3  => 'Custom field deleted.',
            4  => $saved,
            5  => isset( $_GET['revision'] ) ? sprintf( 'Report restored to revision from %s', wp_post_revision_title( absint( $_GET['revision'] ), false ) ) : false, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- core's read-only post-updated notice
            6  => $saved,
            7  => $saved,
            8  => $saved,
            9  => $saved,
            10 => 'Draft saved.',
        ];
        foreach ( Cleanup_Report_Admin::STATUS_MESSAGES as $m ) {
            $messages['cleanup_report'][ $m[0] ] = $m[1];
        }
        return $messages;
    }
}
