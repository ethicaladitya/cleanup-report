<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once CRG_PATH . 'includes/class-crg-cpt.php';
require_once CRG_PATH . 'includes/class-crg-admin.php';
require_once CRG_PATH . 'includes/class-crg-render.php';

class Cleanup_Report_Generator {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'includes' ] );
        add_action( 'init', [ 'CRG_CPT', 'register' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_assets' ] );
        add_action( 'admin_menu', [ __CLASS__, 'settings_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_filter( 'post_row_actions', [ __CLASS__, 'row_actions' ], 10, 2 );
        add_action( 'admin_post_crg_view_report', [ __CLASS__, 'handle_view_report' ] );
        add_action( 'admin_post_crg_export_pdf', [ __CLASS__, 'handle_export_pdf' ] );
        add_action( 'add_meta_boxes', [ 'CRG_Admin', 'register_meta_boxes' ] );
        add_action( 'save_post_cleanup_report', [ 'CRG_Admin', 'save_meta' ] );
        
        // Review workflow hooks
        add_filter( 'wp_insert_post_data', [ __CLASS__, 'control_post_status' ], 10, 2 );
        add_action( 'admin_notices', [ __CLASS__, 'review_admin_notices' ] );
        add_filter( 'post_updated_messages', [ __CLASS__, 'review_post_messages' ] );
        
        // PDF status notice
        add_action( 'admin_notices', [ 'CRG_PDF', 'show_library_notice' ] );
    }

    public static function includes() {
        // noop: files already required
    }

    public static function admin_assets( $hook ) {
        global $post_type;
        
        if ($post_type === 'cleanup_report' || strpos($hook, 'cleanup_report') !== false || $hook === 'post.php' || $hook === 'post-new.php') {
            wp_enqueue_media(); // Added: Enable media picker
            wp_enqueue_style( 'crg-admin', CRG_URL . 'assets/admin.css', [], CRG_VERSION );
            wp_enqueue_script( 'crg-admin', CRG_URL . 'assets/admin.js', ['jquery', 'media-upload', 'media-views'], CRG_VERSION, true );
        }
    }

    public static function settings_menu() {
        add_options_page( 'Cleanup Reports', 'Cleanup Reports', 'manage_options', 'crg-settings', [ __CLASS__, 'settings_page' ] );
    }

    public static function register_settings() {
        register_setting( 'crg_settings', 'crg_brand_name' );
        register_setting( 'crg_settings', 'crg_brand_logo' );
        register_setting( 'crg_settings', 'crg_brand_primary', ['default' => '#003366'] ); // WPMU DEV Navy Blue
        register_setting( 'crg_settings', 'crg_brand_accent', ['default' => '#00C851'] );  // WPMU DEV Secondary Green
        register_setting( 'crg_settings', 'crg_footer_text', ['default' => '© ' . date('Y') . ' WPMU DEV Services' ] );
    }

    public static function settings_page() { ?>
        <div class="wrap">
        <h1>Cleanup Report Generator – Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'crg_settings' ); do_settings_sections( 'crg_settings' ); ?>
            <table class="form-table" role="presentation">
                <tr><th scope="row"><label>Brand Name</label></th>
                    <td><input type="text" name="crg_brand_name" value="<?php echo esc_attr( get_option('crg_brand_name','WPMU DEV Services') ); ?>" class="regular-text"></td></tr>
                <tr><th scope="row"><label>Brand Logo URL</label></th>
                    <td><input type="url" name="crg_brand_logo" value="<?php echo esc_url( get_option('crg_brand_logo','') ); ?>" class="regular-text"></td></tr>
                <tr><th scope="row"><label>Primary Color (WPMU DEV Blue)</label></th>
                    <td><input type="text" name="crg_brand_primary" value="<?php echo esc_attr( get_option('crg_brand_primary','#003366') ); ?>" class="regular-text"></td></tr>
                <tr><th scope="row"><label>Accent Color (WPMU DEV Green)</label></th>
                    <td><input type="text" name="crg_brand_accent" value="<?php echo esc_attr( get_option('crg_brand_accent','#00C851') ); ?>" class="regular-text"></td></tr>
                <tr><th scope="row"><label>Footer Text</label></th>
                    <td><input type="text" name="crg_footer_text" value="<?php echo esc_attr( get_option('crg_footer_text','© ' . date('Y') . ' WPMU DEV Services') ); ?>" class="regular-text"></td></tr>
            </table>
            <?php submit_button(); ?>
        </form>
        </div>
    <?php }

    public static function row_actions( $actions, $post ) {
        if ( $post->post_type !== 'cleanup_report' ) return $actions;
        $view = wp_nonce_url( admin_url('admin-post.php?action=crg_view_report&post='.$post->ID), 'crg_view_'.$post->ID );
        $pdf  = wp_nonce_url( admin_url('admin-post.php?action=crg_export_pdf&post='.$post->ID), 'crg_pdf_'.$post->ID );
        $actions['crg_view'] = '<a href="'.esc_url($view).'">View Report</a>';
        $actions['crg_pdf']  = '<a href="'.esc_url($pdf).'">Export PDF</a>';
        return $actions;
    }

    public static function handle_view_report() {
        $post_id = intval($_GET['post'] ?? 0);
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'crg_view_'.$post_id ) ) wp_die('Invalid nonce');
        CRG_Render::render_report_page( $post_id );
        exit;
    }

    public static function handle_export_pdf() {
        $post_id = intval($_GET['post'] ?? 0);
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'crg_pdf_'.$post_id ) ) wp_die('Invalid nonce');
        
        try {
            // Use the comprehensive PDF system
            CRG_PDF::generate_pdf( $post_id );
        } catch ( Exception $e ) {
            // Fallback to simple text-based PDF
            error_log( 'PDF Generation Error: ' . $e->getMessage() );
            
            // Simple fallback - redirect to print view
            $view_url = wp_nonce_url( admin_url('admin-post.php?action=crg_view_report&post='.$post_id.'&print=1'), 'crg_view_'.$post_id );
            wp_redirect( $view_url );
            exit;
        }
    }

    // Review workflow methods
    public static function control_post_status( $data, $postarr ) {
        if ( $data['post_type'] !== 'cleanup_report' ) {
            return $data;
        }

        // Only apply workflow controls if this is not an auto-save
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
            return $data;
        }

        $current_user = wp_get_current_user();
        $post_id = $postarr['ID'] ?? 0;
        
        // If user is an editor/admin, they can publish directly
        if ( current_user_can( 'edit_others_posts' ) ) {
            return $data;
        }
        
        // Authors can only create drafts or submit for review
        if ( current_user_can( 'edit_posts' ) ) {
            // If trying to publish, change to pending review
            if ( $data['post_status'] === 'publish' ) {
                $data['post_status'] = 'pending';
                
                // Update review status
                if ( $post_id ) {
                    update_post_meta( $post_id, '_review_status', 'submitted' );
                    update_post_meta( $post_id, '_review_submitted_at', current_time( 'mysql' ) );
                    update_post_meta( $post_id, '_review_submitted_by', $current_user->ID );
                }
            }
        }
        
        return $data;
    }
    
    public static function review_admin_notices() {
        global $post, $pagenow;
        
        if ( $pagenow !== 'post.php' || ! $post || $post->post_type !== 'cleanup_report' ) {
            return;
        }
        
        $review_status = get_post_meta( $post->ID, '_review_status', true );
        
        if ( $review_status === 'submitted' && ! current_user_can( 'edit_others_posts' ) ) {
            echo '<div class="notice notice-info"><p><strong>This report has been submitted for review.</strong> You cannot make changes until it\'s approved or returned.</p></div>';
        }
        
        if ( $review_status === 'returned' && current_user_can( 'edit_posts' ) ) {
            $reviewer_notes = get_post_meta( $post->ID, '_review_notes', true );
            echo '<div class="notice notice-warning"><p><strong>This report has been returned for revision.</strong>';
            if ( $reviewer_notes ) {
                echo '<br>Notes: ' . esc_html( $reviewer_notes );
            }
            echo '</p></div>';
        }
    }
    
    public static function review_post_messages( $messages ) {
        $messages['cleanup_report'] = array(
            0  => '', // Unused. Messages start at index 1.
            1  => 'Cleanup report updated.',
            2  => 'Custom field updated.',
            3  => 'Custom field deleted.',
            4  => 'Cleanup report updated.',
            5  => isset($_GET['revision']) ? sprintf( 'Cleanup report restored to revision from %s', wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
            6  => 'Cleanup report published.',
            7  => 'Cleanup report saved.',
            8  => 'Cleanup report submitted for review.',
            9  => sprintf( 'Cleanup report scheduled for: <strong>%1$s</strong>.', date_i18n( 'M j, Y @ G:i', strtotime( get_the_date() . ' ' . get_the_time() ) ) ),
            10 => 'Cleanup report draft updated.'
        );
        
        return $messages;
    }
}
