<?php
/**
 * Reports › Settings: branding, and the two checklists staff fill in on every report.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Cleanup_Report_Settings {

    const SLUG = 'crg-settings';

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register' ] );
        add_action( 'admin_post_crg_reset_checklist', [ __CLASS__, 'handle_reset' ] );
        // Buddyleads can save the two checklists through options.php (which asks for manage_options by default).
        // Branding (crg_settings) and Review (crg_review) keep the default: administrators only.
        add_filter( 'option_page_capability_crg_followups', [ __CLASS__, 'checklist_capability' ] );
        add_filter( 'option_page_capability_crg_defender', [ __CLASS__, 'checklist_capability' ] );
        add_filter( 'option_page_capability_crg_blocklists', [ __CLASS__, 'checklist_capability' ] );
        add_filter( 'option_page_capability_crg_optimization', [ __CLASS__, 'checklist_capability' ] );
        add_filter( 'plugin_action_links_' . plugin_basename( CLEANUP_REPORT_PATH . 'cleanup-report-generator.php' ), [ __CLASS__, 'plugin_links' ] );
    }

    public static function url( $tab = 'general' ) {
        return admin_url( 'edit.php?post_type=cleanup_report&page=' . self::SLUG . '&tab=' . $tab );
    }

    public static function menu() {
        // Admins get every tab; Buddyleads the checklists and the Plugin list; Writers no Settings at all.
        if ( current_user_can( 'manage_options' ) ) {
            add_submenu_page( 'edit.php?post_type=cleanup_report', __( 'Report settings', 'cleanup-report-generator' ), __( 'Settings', 'cleanup-report-generator' ), 'manage_options', self::SLUG, [ __CLASS__, 'page' ] );
        } elseif ( Cleanup_Report_Access::can_manage() ) {
            add_submenu_page( 'edit.php?post_type=cleanup_report', __( 'Report settings', 'cleanup-report-generator' ), __( 'Settings', 'cleanup-report-generator' ), 'edit_posts', self::SLUG, [ __CLASS__, 'page' ] );
        }
    }

    /** Capability options.php checks before saving a checklist: Buddyleads pass with edit_posts. */
    public static function checklist_capability( $capability ) {
        return Cleanup_Report_Access::can_manage_checklists() ? 'edit_posts' : $capability;
    }

    public static function plugin_links( $links ) {
        array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'cleanup-report-generator' ) . '</a>' );
        return $links;
    }

    public static function register() {
        register_setting( 'crg_settings', Cleanup_Report_Presets::OPTION, [ 'default' => 'generic', 'sanitize_callback' => [ 'Cleanup_Report_Presets', 'sanitize' ] ] );
        register_setting( 'crg_settings', 'crg_brand_name', [ 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'crg_settings', 'crg_brand_logo', [ 'sanitize_callback' => 'esc_url_raw' ] );
        register_setting( 'crg_settings', 'crg_brand_logo_light', [ 'sanitize_callback' => 'esc_url_raw' ] );
        foreach ( Cleanup_Report_Render::COLORS as $key => $default ) {
            register_setting( 'crg_settings', 'crg_color_' . $key, [ 'default' => $default, 'sanitize_callback' => static function ( $v ) use ( $default ) { return sanitize_hex_color( $v ) ?: $default; } ] );
        }
        register_setting( 'crg_settings', 'crg_brand_primary', [ 'default' => '#0059FF', 'sanitize_callback' => [ __CLASS__, 'sanitize_primary' ] ] ); // wpmudev.com blue
        register_setting( 'crg_settings', 'crg_report_theme', [ 'default' => 'light', 'sanitize_callback' => static function ( $v ) { return 'dark' === $v ? 'dark' : 'light'; } ] );
        register_setting( 'crg_settings', 'crg_cover_glow', [ 'type' => 'integer', 'default' => 100, 'sanitize_callback' => [ 'Cleanup_Report_Render', 'cover_glow' ] ] );
        register_setting( 'crg_settings', 'crg_footer_text', [ 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'crg_followups', Cleanup_Report_Checklists::FOLLOWUP_OPTION, [ 'type' => 'array', 'sanitize_callback' => [ 'Cleanup_Report_Checklists', 'sanitize_followup_items' ] ] );
        register_setting( 'crg_review', 'crg_watermark_text', [ 'default' => 'NOT FINAL', 'sanitize_callback' => static function ( $v ) { return mb_substr( trim( sanitize_text_field( (string) $v ) ), 0, 40 ) ?: 'NOT FINAL'; } ] );
        register_setting( 'crg_review', 'crg_pdf_requires_approval', [ 'type' => 'boolean', 'default' => 1, 'sanitize_callback' => static function ( $v ) { return empty( $v ) ? 0 : 1; } ] );
        register_setting( 'crg_review', Cleanup_Report_Retention::OPTION, [ 'type' => 'integer', 'default' => Cleanup_Report_Retention::DEFAULT_DAYS, 'sanitize_callback' => [ 'Cleanup_Report_Retention', 'sanitize_days' ] ] );
        register_setting( 'crg_review', 'crg_plugin_recheck', [ 'type' => 'boolean', 'default' => 0, 'sanitize_callback' => static function ( $v ) { return empty( $v ) ? 0 : 1; } ] );
        register_setting( 'crg_review', 'crg_delete_data', [ 'type' => 'boolean', 'default' => 0, 'sanitize_callback' => static function ( $v ) { return empty( $v ) ? 0 : 1; } ] );
        register_setting( 'crg_review', Cleanup_Report_Admin::BUDDYLEADS_OPTION, [ 'type' => 'array', 'default' => [], 'sanitize_callback' => [ __CLASS__, 'sanitize_buddyleads' ] ] );
        register_setting( 'crg_defender', Cleanup_Report_Checklists::DEFENDER_OPTION, [ 'type' => 'array', 'sanitize_callback' => [ 'Cleanup_Report_Checklists', 'sanitize_defender_items' ] ] );
        register_setting( 'crg_blocklists', Cleanup_Report_Checklists::BLOCKLIST_OPTION, [ 'type' => 'array', 'sanitize_callback' => [ 'Cleanup_Report_Checklists', 'sanitize_blocklists' ] ] );
        register_setting( 'crg_optimization', Cleanup_Report_Optimization::CHECKS_OPTION, [ 'type' => 'array', 'sanitize_callback' => [ 'Cleanup_Report_Optimization', 'sanitize_checks' ] ] );
        foreach ( Cleanup_Report_Optimization::TARGETS as $device => $default ) {
            register_setting( 'crg_optimization', 'crg_opt_target_' . $device, [ 'type' => 'integer', 'default' => $default, 'sanitize_callback' => [ 'Cleanup_Report_Optimization', 'sanitize_target' ] ] );
        }
    }

    public static function sanitize_primary( $value ) {
        return sanitize_hex_color( $value ) ?: '#0059FF';
    }

    /** "Restore defaults" for one checklist: deleting the option brings the built-in items back. */
    public static function handle_reset() {
        $list = sanitize_key( wp_unslash( $_GET['list'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked on the next line
        check_admin_referer( 'crg_reset_' . $list );
        if ( ! Cleanup_Report_Access::can_manage_checklists() ) {
            wp_die( esc_html__( 'You are not allowed to change these settings.', 'cleanup-report-generator' ), esc_html__( 'Forbidden', 'cleanup-report-generator' ), [ 'response' => 403, 'back_link' => true ] );
        }
        $options = [ 'followups' => Cleanup_Report_Checklists::FOLLOWUP_OPTION, 'defender' => Cleanup_Report_Checklists::DEFENDER_OPTION, 'blocklists' => Cleanup_Report_Checklists::BLOCKLIST_OPTION, 'optimization' => Cleanup_Report_Optimization::CHECKS_OPTION ];
        if ( isset( $options[ $list ] ) ) {
            delete_option( $options[ $list ] );
        }
        wp_safe_redirect( add_query_arg( 'crg_reset', '1', self::url( $list ) ) );
        exit;
    }

    /** Settings tabs drawn by this class (a report type's own tabs may bring a render callable instead). */
    const BUILT_IN_TABS = [ 'general' => 'tab_general', 'review' => 'tab_review', 'plugins' => 'tab_plugins', 'followups' => 'tab_followups', 'defender' => 'tab_defender', 'blocklists' => 'tab_blocklists', 'optimization' => 'tab_optimization' ];

    /**
     * Tabs this user may open: the shared tabs (Branding and Review for admins; the Plugin list for Buddyleads too),
     * then each report type's own tabs (its checklists: admins and Buddyleads). Writers get none.
     */
    public static function tabs() {
        $tabs = [];
        if ( current_user_can( 'manage_options' ) ) {
            $tabs['general'] = __( 'Branding', 'cleanup-report-generator' );
            $tabs['review']  = __( 'Review', 'cleanup-report-generator' );
        }
        if ( Cleanup_Report_Access::can_manage_plugin_list() ) {
            $tabs['plugins'] = __( 'Plugin list', 'cleanup-report-generator' );
        }
        if ( Cleanup_Report_Access::can_manage_checklists() ) {
            foreach ( self::type_tabs() as $key => $tab ) {
                $tabs[ $key ] = $tab['label'];
            }
        }
        return $tabs;
    }

    /** Every report type's own tabs: key => [ label, type label, render callable or null ]. */
    private static function type_tabs() {
        $out = [];
        foreach ( Cleanup_Report_Types::definitions() as $def ) {
            foreach ( (array) $def['settings'] as $key => $tab ) {
                $key = sanitize_key( $key );
                if ( '' === $key || isset( $out[ $key ] ) || ( in_array( $key, [ 'general', 'review', 'plugins' ], true ) ) ) {
                    continue;
                }
                $out[ $key ] = [
                    'label'  => is_array( $tab ) ? (string) ( $tab['label'] ?? $tab[0] ?? $key ) : (string) $tab,
                    'group'  => $def['label'],
                    'render' => is_array( $tab ) ? ( $tab['render'] ?? $tab[1] ?? null ) : null,
                ];
            }
        }
        return $out;
    }

    public static function page() {
        $tabs = self::tabs();
        if ( ! $tabs ) {
            wp_die( esc_html__( 'You are not allowed to view these settings.', 'cleanup-report-generator' ), esc_html__( 'Forbidden', 'cleanup-report-generator' ), [ 'response' => 403 ] );
        }
        $tab = sanitize_key( wp_unslash( $_GET['tab'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab switch, read-only
        $tab = 'premium' === $tab ? 'plugins' : $tab; // the tab's old name
        $tab = isset( $tabs[ $tab ] ) ? $tab : array_key_first( $tabs );
        $type_tabs = self::type_tabs();
        ?>
        <div class="wrap crg-settings">
            <h1><?php esc_html_e( 'Report settings', 'cleanup-report-generator' ); ?></h1>
            <?php if ( isset( $_GET['crg_reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- notice only ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Default items restored.', 'cleanup-report-generator' ); ?></p></div>
            <?php endif; ?>
            <?php settings_errors(); ?>
            <nav class="nav-tab-wrapper">
                <?php $group = ''; ?>
                <?php foreach ( $tabs as $key => $label ) : ?>
                    <?php if ( isset( $type_tabs[ $key ] ) && $type_tabs[ $key ]['group'] !== $group ) : $group = $type_tabs[ $key ]['group']; ?><span class="crg-tab-group"><?php echo esc_html( $group ); ?></span><?php endif; ?>
                    <a href="<?php echo esc_url( self::url( $key ) ); ?>" class="nav-tab<?php echo $key === $tab ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
            </nav>
            <?php
            if ( isset( self::BUILT_IN_TABS[ $tab ] ) ) {
                call_user_func( [ __CLASS__, self::BUILT_IN_TABS[ $tab ] ] );
            } elseif ( isset( $type_tabs[ $tab ]['render'] ) && is_callable( $type_tabs[ $tab ]['render'] ) ) {
                call_user_func( $type_tabs[ $tab ]['render'] );
            }
            ?>
        </div>
        <?php
    }

    private static function tab_general() {
        $logo = get_option( 'crg_brand_logo', '' );
        ?>
        <form method="post" action="options.php" class="crg-settings-form">
            <?php settings_fields( 'crg_settings' ); ?>
            <p class="crg-lead"><?php esc_html_e( 'How the report and PDF are branded.', 'cleanup-report-generator' ); ?></p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="crg_defaults"><?php esc_html_e( 'Defaults', 'cleanup-report-generator' ); ?></label></th>
                    <td><select id="crg_defaults" name="<?php echo esc_attr( Cleanup_Report_Presets::OPTION ); ?>">
                        <?php foreach ( Cleanup_Report_Presets::choices() as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( Cleanup_Report_Presets::current(), $value ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php echo wp_kses( __( 'What reports start with when nothing is set: <strong>WPMU DEV</strong> uses the WPMU DEV support team\'s brand, the Defender checklist, the Hub check and WPMU DEV products in the standard texts; <strong>Generic</strong> uses this site\'s name and neutral texts. Your saved settings and checklists always win.', 'cleanup-report-generator' ), [ 'strong' => [] ] ); ?></p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="crg_brand_name"><?php esc_html_e( 'Brand name', 'cleanup-report-generator' ); ?></label></th>
                    <td><input type="text" id="crg_brand_name" name="crg_brand_name" value="<?php echo esc_attr( get_option( 'crg_brand_name', '' ) ?: Cleanup_Report_Presets::get( 'brand_name' ) ); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e( 'Shown on the cover and in "Prepared by … security team".', 'cleanup-report-generator' ); ?></p></td>
                </tr>
<?php foreach ( [ 'crg_brand_logo_light' => [ __( 'Logo for the light theme', 'cleanup-report-generator' ), __( 'A dark or coloured logo, for the light cover.', 'cleanup-report-generator' ) ], 'crg_brand_logo' => [ __( 'Logo for the dark theme', 'cleanup-report-generator' ), __( 'A white logo, for the dark cover.', 'cleanup-report-generator' ) ] ] as $opt => [ $label, $help ] ) : $logo = get_option( $opt, '' ); ?>
                <tr>
                    <th scope="row"><label for="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $label ); ?></label></th>
                    <td>
                        <div class="crg-logo-field">
                            <span class="crg-logo-preview<?php echo $logo ? '' : ' is-empty'; ?><?php echo 'crg_brand_logo' === $opt ? ' is-dark' : ''; ?>"><?php if ( $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt=""><?php endif; ?></span>
                            <input type="url" id="<?php echo esc_attr( $opt ); ?>" name="<?php echo esc_attr( $opt ); ?>" value="<?php echo esc_url( $logo ); ?>" class="regular-text crg-logo-url" placeholder="https://">
                            <button type="button" class="button crg-media" data-target="#<?php echo esc_attr( $opt ); ?>"><?php esc_html_e( 'Choose image', 'cleanup-report-generator' ); ?></button>
                        </div>
                        <p class="description"><?php echo esc_html( $help ); ?> <?php esc_html_e( 'Leave empty to show the brand name instead.', 'cleanup-report-generator' ); ?></p>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Colour presets', 'cleanup-report-generator' ); ?></th>
                    <td>
                        <?php foreach ( Cleanup_Report_Render::PRESETS as $key => $preset ) : ?>
                            <button type="button" class="button crg-preset" data-preset="<?php echo esc_attr( (string) wp_json_encode( $preset ) ); ?>">
                                <?php foreach ( [ 'primary', 'danger', 'warning', 'success' ] as $swatch ) : ?><span class="crg-preset-swatch" style="background:<?php echo esc_attr( $preset[ $swatch ] ); ?>"></span><?php endforeach; ?>
                                <?php echo esc_html( Cleanup_Report_Render::preset_labels()[ $key ] ?? $key ); ?>
                            </button>
                        <?php endforeach; ?>
                        <p class="description"><?php esc_html_e( 'Fills the colours and cover gradient below in one click. Nothing changes until you save; you can still adjust any colour afterwards. Bold is the original look.', 'cleanup-report-generator' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="crg_brand_primary"><?php esc_html_e( 'Primary colour', 'cleanup-report-generator' ); ?></label></th>
                    <td><input type="text" id="crg_brand_primary" name="crg_brand_primary" value="<?php echo esc_attr( get_option( 'crg_brand_primary', '#0059FF' ) ); ?>" class="crg-color" data-default-color="#0059FF">
                    <p class="description"><?php echo wp_kses( __( 'Headings, links and highlights. The default blue is <code>#0059FF</code>.', 'cleanup-report-generator' ), [ 'code' => [] ] ); ?></p></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Status colours', 'cleanup-report-generator' ); ?></th>
                    <td class="crg-colors">
                        <?php foreach ( Cleanup_Report_Render::COLORS as $key => $default ) : ?>
                            <div class="crg-color-row">
                                <input type="text" id="crg_color_<?php echo esc_attr( $key ); ?>" name="crg_color_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( get_option( 'crg_color_' . $key, $default ) ); ?>" class="crg-color" data-default-color="<?php echo esc_attr( $default ); ?>">
                                <?php [ $label, $use ] = Cleanup_Report_Render::color_labels()[ $key ] ?? [ $key, '' ]; ?>
                                <label for="crg_color_<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $label ); ?></strong> <span class="crg-muted"><?php echo esc_html( $use ); ?></span></label>
                            </div>
                        <?php endforeach; ?>
                        <p class="description"><?php esc_html_e( 'Text on each colour is chosen automatically (white or near-black) so it stays readable.', 'cleanup-report-generator' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Report theme', 'cleanup-report-generator' ); ?></th>
                    <td>
                        <?php $theme = get_option( 'crg_report_theme', 'light' ); ?>
                        <label class="crg-radio"><input type="radio" name="crg_report_theme" value="light" <?php checked( 'dark' !== $theme ); ?>> <?php esc_html_e( 'Light (day)', 'cleanup-report-generator' ); ?></label>
                        <label class="crg-radio"><input type="radio" name="crg_report_theme" value="dark" <?php checked( 'dark', $theme ); ?>> <?php esc_html_e( 'Dark', 'cleanup-report-generator' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Light prints well and suits most members; dark is a near-black cover and panels. Applies to the PDF and the preview.', 'cleanup-report-generator' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="crg_cover_glow"><?php esc_html_e( 'Cover gradient', 'cleanup-report-generator' ); ?></label></th>
                    <td><input type="number" id="crg_cover_glow" name="crg_cover_glow" value="<?php echo esc_attr( Cleanup_Report_Render::cover_glow( get_option( 'crg_cover_glow', 100 ) ) ); ?>" min="0" max="100" step="5" class="small-text"> %
                    <p class="description"><?php esc_html_e( 'How strong the blue glow on the cover is. 100% is the original look; lower is lighter, and 0 removes it.', 'cleanup-report-generator' ); ?></p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="crg_footer_text"><?php esc_html_e( 'Footer text', 'cleanup-report-generator' ); ?></label></th>
                    <td><input type="text" id="crg_footer_text" name="crg_footer_text" value="<?php echo esc_attr( get_option( 'crg_footer_text', Cleanup_Report_Render::footer_default() ) ); ?>" class="regular-text">
                    <p class="description"><?php echo wp_kses( __( 'Printed at the bottom of every PDF page. <code>{year}</code> is replaced with the current year.', 'cleanup-report-generator' ), [ 'code' => [] ] ); ?></p></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    private static function tab_followups() {
        $items = Cleanup_Report_Checklists::followup_items();
        ?>
        <form method="post" action="options.php" class="crg-settings-form">
            <?php settings_fields( 'crg_followups' ); ?>
            <p class="crg-lead"><?php echo wp_kses( __( 'The <strong>Follow-ups</strong> staff mark on every report. Each one the report includes appears under <em>Your next steps › Recommended actions</em>: as <strong>In place</strong> (already configured), <strong>Done</strong> (we did it), or as <strong>Required</strong>/<strong>Recommended</strong> when the member needs to act.', 'cleanup-report-generator' ), [ 'strong' => [], 'em' => [] ] ); ?></p>
            <?php self::checklist_notes(); ?>
            <div class="crg-items" data-name="<?php echo esc_attr( Cleanup_Report_Checklists::FOLLOWUP_OPTION ); ?>">
                <?php foreach ( $items as $i => $item ) { self::followup_item( $i, $item ); } ?>
            </div>
            <template class="crg-item-template"><?php self::followup_item( '__i__', [ 'enabled' => true ], true ); ?></template>
            <p><button type="button" class="button crg-item-add"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add follow-up', 'cleanup-report-generator' ); ?></button></p>
            <?php self::checklist_footer( 'followups' ); ?>
        </form>
        <?php
    }

    private static function tab_defender() {
        $items = Cleanup_Report_Checklists::defender_items();
        ?>
        <form method="post" action="options.php" class="crg-settings-form">
            <?php settings_fields( 'crg_defender' ); ?>
            <p class="crg-lead"><?php echo wp_kses( __( 'The <strong>Defender settings</strong> staff mark on every report, in two parts: <strong>to be done by the agent</strong> (what we configure during the cleanup) and <strong>to be done by the member</strong> (what needs the site owner). They appear in the report\'s <em>Defender security configuration</em> section.', 'cleanup-report-generator' ), [ 'strong' => [], 'em' => [] ] ); ?></p>
            <?php self::checklist_notes(); ?>
            <div class="crg-items" data-name="<?php echo esc_attr( Cleanup_Report_Checklists::DEFENDER_OPTION ); ?>">
                <?php foreach ( $items as $i => $item ) { self::defender_item( $i, $item ); } ?>
            </div>
            <template class="crg-item-template"><?php self::defender_item( '__i__', [ 'enabled' => true ], true ); ?></template>
            <p><button type="button" class="button crg-item-add"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add Defender setting', 'cleanup-report-generator' ); ?></button></p>
            <?php self::checklist_footer( 'defender' ); ?>
        </form>
        <?php
    }

    private static function tab_optimization() {
        $items = Cleanup_Report_Optimization::check_items();
        ?>
        <form method="post" action="options.php" class="crg-settings-form">
            <?php settings_fields( 'crg_optimization' ); ?>
            <p class="crg-lead"><?php echo wp_kses( __( 'For <strong>Website optimization</strong> reports: the score each average should reach after the work, and the tests staff answer before and after it (the report\'s <em>Pre-work</em> and <em>Post-work checklist</em>).', 'cleanup-report-generator' ), [ 'strong' => [], 'em' => [] ] ); ?></p>
            <h2 class="title"><?php esc_html_e( 'Targets', 'cleanup-report-generator' ); ?></h2>
            <table class="form-table" role="presentation">
                <?php foreach ( [ 'mobile' => __( 'Mobile', 'cleanup-report-generator' ), 'desktop' => __( 'Desktop', 'cleanup-report-generator' ) ] as $device => $label ) : ?>
                    <tr>
                        <th scope="row"><label for="crg_opt_target_<?php echo esc_attr( $device ); ?>"><?php
                        /* translators: %s: device, "Mobile" or "Desktop" */
                        echo esc_html( sprintf( __( '%s average', 'cleanup-report-generator' ), $label ) );
                        ?></label></th>
                        <td><input type="number" id="crg_opt_target_<?php echo esc_attr( $device ); ?>" name="crg_opt_target_<?php echo esc_attr( $device ); ?>" value="<?php echo (int) Cleanup_Report_Optimization::targets()[ $device ]; ?>" min="0" max="100" step="1" class="small-text"></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <p class="description"><?php echo wp_kses( __( 'The report\'s <em>Optimization result</em> says whether each was reached. A report keeps the targets it was last saved with.', 'cleanup-report-generator' ), [ 'em' => [] ] ); ?></p>
            <h2 class="title"><?php esc_html_e( 'Tests', 'cleanup-report-generator' ); ?></h2>
            <?php self::checklist_notes(); ?>
            <div class="crg-items" data-name="<?php echo esc_attr( Cleanup_Report_Optimization::CHECKS_OPTION ); ?>">
                <?php foreach ( $items as $i => $item ) { self::opt_item( $i, $item ); } ?>
            </div>
            <template class="crg-item-template"><?php self::opt_item( '__i__', [ 'enabled' => true, 'good' => 'no' ], true ); ?></template>
            <p><button type="button" class="button crg-item-add"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add test', 'cleanup-report-generator' ); ?></button></p>
            <?php self::checklist_footer( 'optimization' ); ?>
        </form>
        <?php
    }

    private static function opt_item( $i, array $item, $is_template = false ) {
        $name = Cleanup_Report_Optimization::CHECKS_OPTION;
        $good = $item['good'] ?? '';
        self::item_head( $name, $i, $item, $is_template );
        ?>
            <div class="crg-item-body">
                <?php self::field( $name, $i, 'label', __( 'Test', 'cleanup-report-generator' ), $item['label'] ?? '', __( 'The question, as it appears in the report.', 'cleanup-report-generator' ) ); ?>
                <?php self::field( $name, $i, 'hint', __( 'Hint for staff', 'cleanup-report-generator' ), $item['hint'] ?? '', __( 'Edit screen only: how to test it.', 'cleanup-report-generator' ) ); ?>
                <div class="crg-field">
                    <span class="crg-field-label"><?php esc_html_e( 'Good answer', 'cleanup-report-generator' ); ?></span>
                    <?php foreach ( [ 'yes' => __( 'Yes', 'cleanup-report-generator' ), 'no' => __( 'No', 'cleanup-report-generator' ), '' => __( 'Neither (just information)', 'cleanup-report-generator' ) ] as $value => $label ) : ?>
                        <label class="crg-radio"><input type="radio" name="<?php echo esc_attr( "{$name}[{$i}][good]" ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( $good, $value ); ?>> <?php echo esc_html( $label ); ?></label>
                    <?php endforeach; ?>
                    <p class="description"><?php esc_html_e( 'The report shows the good answer in green and the other in red. N/A is always grey.', 'cleanup-report-generator' ); ?></p>
                </div>
                <?php self::field( $name, $i, 'pre_note', __( 'Report note before the work', 'cleanup-report-generator' ), $item['pre_note'] ?? '', __( 'Used in the pre-work checklist when the report has no note of its own.', 'cleanup-report-generator' ), 'textarea' ); ?>
                <?php self::field( $name, $i, 'post_note', __( 'Report note after the work', 'cleanup-report-generator' ), $item['post_note'] ?? '', __( 'Used in the post-work checklist when the report has no note of its own.', 'cleanup-report-generator' ), 'textarea' ); ?>
            </div>
        </details>
        <?php
    }

    private static function tab_blocklists() {
        $items = Cleanup_Report_Checklists::blocklist_items();
        ?>
        <form method="post" action="options.php" class="crg-settings-form">
            <?php settings_fields( 'crg_blocklists' ); ?>
            <p class="crg-lead"><?php echo wp_kses( __( 'The <strong>security vendors</strong> staff can tick under <em>Security blocklists (delist request)</em> when VirusTotal or another scanner still flags a site. Each one ticked appears in the report with where the member requests delisting.', 'cleanup-report-generator' ), [ 'strong' => [], 'em' => [] ] ); ?></p>
            <ul class="crg-notes">
                <li><?php
                /* translators: %s: the drag handle icon */
                echo wp_kses( sprintf( __( 'Drag %s to change the order. Turn a vendor off to hide it from new reports without deleting it.', 'cleanup-report-generator' ), '<span class="dashicons dashicons-menu"></span>' ), [ 'span' => [ 'class' => [] ] ] );
                ?></li>
                <li><?php esc_html_e( 'Use the vendor\'s name as VirusTotal shows it, so staff can find it quickly.', 'cleanup-report-generator' ); ?></li>
                <li><?php esc_html_e( 'Reports keep the contact details they were saved with.', 'cleanup-report-generator' ); ?></li>
            </ul>
            <div class="crg-items" data-name="<?php echo esc_attr( Cleanup_Report_Checklists::BLOCKLIST_OPTION ); ?>">
                <?php foreach ( $items as $i => $item ) { self::blocklist_item( $i, $item ); } ?>
            </div>
            <template class="crg-item-template"><?php self::blocklist_item( '__i__', [ 'enabled' => true ], true ); ?></template>
            <p><button type="button" class="button crg-item-add"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add vendor', 'cleanup-report-generator' ); ?></button></p>
            <?php self::checklist_footer( 'blocklists' ); ?>
        </form>
        <?php
    }

    private static function blocklist_item( $i, array $item, $is_template = false ) {
        $name = Cleanup_Report_Checklists::BLOCKLIST_OPTION;
        self::item_head( $name, $i, $item, $is_template );
        ?>
            <div class="crg-item-body">
                <?php self::field( $name, $i, 'label', __( 'Vendor', 'cleanup-report-generator' ), $item['label'] ?? '', __( 'As VirusTotal shows it.', 'cleanup-report-generator' ) ); ?>
                <?php self::field( $name, $i, 'url', __( 'Delisting form', 'cleanup-report-generator' ), $item['url'] ?? '', __( 'Where the site owner reports a false positive or asks for a review.', 'cleanup-report-generator' ), 'url', 'https://' ); ?>
                <?php self::field( $name, $i, 'email', __( 'Email', 'cleanup-report-generator' ), $item['email'] ?? '', __( 'Optional. Only if the vendor accepts delisting requests by email.', 'cleanup-report-generator' ), 'email', 'falsepositive@vendor.com' ); ?>
                <?php self::field( $name, $i, 'note', __( 'How to ask', 'cleanup-report-generator' ), $item['note'] ?? '', __( 'Optional. Shown in the report under the contact, e.g. a required subject line or the option to choose.', 'cleanup-report-generator' ) ); ?>
            </div>
        </details>
        <?php
    }

    /** Buddyleads: user IDs of people who can edit reports. */
    public static function sanitize_buddyleads( $value ) {
        $ids = array_map( 'absint', is_array( $value ) ? $value : [] );
        // Editors and up only: approving means saving someone else's report, which WordPress allows only with edit_others_posts.
        return array_values( array_filter( array_unique( $ids ), static function ( $id ) { return $id && user_can( $id, 'edit_others_posts' ); } ) );
    }

    private static function tab_review() {
        $leads = Cleanup_Report_Admin::buddyleads();
        $users = get_users( [ 'capability' => 'edit_others_posts', 'orderby' => 'display_name', 'fields' => [ 'ID', 'display_name', 'user_email' ] ] );
        ?>
        <form method="post" action="options.php" class="crg-settings-form">
            <?php settings_fields( 'crg_review' ); ?>
            <p class="crg-lead"><?php esc_html_e( 'How a report moves from draft to sent:', 'cleanup-report-generator' ); ?></p>
            <ol class="crg-flow">
                <li><span class="crg-status-badge crg-status-draft"><?php esc_html_e( 'Draft', 'cleanup-report-generator' ); ?></span> <?php echo wp_kses( __( 'Anyone writing reports can draft one and <strong>submit it for review</strong>.', 'cleanup-report-generator' ), [ 'strong' => [] ] ); ?></li>
                <li><span class="crg-status-badge crg-status-needs_revision"><?php esc_html_e( 'Needs revision', 'cleanup-report-generator' ); ?></span> / <span class="crg-status-badge crg-status-approved"><?php esc_html_e( 'Approved', 'cleanup-report-generator' ); ?></span> <?php echo wp_kses( __( 'Only <strong>Buddyleads</strong> and administrators can approve a report or send it back with notes.', 'cleanup-report-generator' ), [ 'strong' => [] ] ); ?></li>
                <li><span class="crg-status-badge crg-status-complete"><?php esc_html_e( 'Completed', 'cleanup-report-generator' ); ?></span> <?php esc_html_e( 'Once approved, anyone can mark it completed after sending it to the member.', 'cleanup-report-generator' ); ?></li>
            </ol>
            <h2 class="title"><?php esc_html_e( 'Who can do what', 'cleanup-report-generator' ); ?></h2>
            <table class="crg-table crg-access">
                <thead><tr><th><?php esc_html_e( 'Who', 'cleanup-report-generator' ); ?></th><th><?php esc_html_e( 'WordPress role', 'cleanup-report-generator' ); ?></th><th><?php esc_html_e( 'Their own reports', 'cleanup-report-generator' ); ?></th><th><?php esc_html_e( 'Others\' reports', 'cleanup-report-generator' ); ?></th><th><?php esc_html_e( 'Approve / send back', 'cleanup-report-generator' ); ?></th><th><?php esc_html_e( 'Settings', 'cleanup-report-generator' ); ?></th></tr></thead>
                <tbody>
                    <tr><td><strong><?php esc_html_e( 'Writers', 'cleanup-report-generator' ); ?></strong><br><span class="crg-muted"><?php esc_html_e( 'support staff who write reports', 'cleanup-report-generator' ); ?></span></td><td><?php echo esc_html( _x( 'Editor', 'WordPress user role', 'cleanup-report-generator' ) ); ?></td><td><?php esc_html_e( 'Create, edit, submit for review', 'cleanup-report-generator' ); ?></td><td><?php esc_html_e( 'Preview; download the PDF and mark Completed once approved', 'cleanup-report-generator' ); ?></td><td>—</td><td>—</td></tr>
                    <tr><td><strong><?php esc_html_e( 'Buddyleads', 'cleanup-report-generator' ); ?></strong><br><span class="crg-muted"><?php esc_html_e( 'ticked below', 'cleanup-report-generator' ); ?></span></td><td><?php echo esc_html( _x( 'Editor', 'WordPress user role', 'cleanup-report-generator' ) ); ?></td><td><?php esc_html_e( 'Everything', 'cleanup-report-generator' ); ?></td><td><?php esc_html_e( 'Open and edit any report', 'cleanup-report-generator' ); ?></td><td>✓</td><td><?php esc_html_e( 'Plugin list, and the checklists: Follow-ups, Defender checklist, Blocklists, Website optimization', 'cleanup-report-generator' ); ?></td></tr>
                    <tr><td><strong><?php esc_html_e( 'Administrators', 'cleanup-report-generator' ); ?></strong></td><td><?php echo esc_html( _x( 'Administrator', 'WordPress user role', 'cleanup-report-generator' ) ); ?></td><td><?php esc_html_e( 'Everything', 'cleanup-report-generator' ); ?></td><td><?php esc_html_e( 'Everything', 'cleanup-report-generator' ); ?></td><td>✓</td><td><?php esc_html_e( 'All tabs, including Branding and Review', 'cleanup-report-generator' ); ?></td></tr>
                </tbody>
            </table>
            <p class="description"><?php
            echo wp_kses(
                sprintf(
                    /* translators: 1: URL of the Users screen, 2: URL of the Add New User screen */
                    __( 'Approved reports are read-only for Writers, except marking them Completed. Give everyone who works on reports the <strong>Editor</strong> role in <a href="%1$s">Users</a> (<a href="%2$s">add someone</a>). Lower roles can\'t save someone else\'s report (WordPress asks for Editor), so a Buddylead couldn\'t approve and a writer couldn\'t mark a report Completed. The report rules above still apply to Editors.', 'cleanup-report-generator' ),
                    esc_url( admin_url( 'users.php' ) ),
                    esc_url( admin_url( 'user-new.php' ) )
                ),
                [ 'strong' => [], 'a' => [ 'href' => [] ] ]
            );
            ?></p>

            <h2 class="title"><?php esc_html_e( 'PDF download', 'cleanup-report-generator' ); ?></h2>
            <input type="hidden" name="crg_pdf_requires_approval" value="0">
            <label><input type="checkbox" name="crg_pdf_requires_approval" value="1" <?php checked( (int) get_option( 'crg_pdf_requires_approval', 1 ), 1 ); ?>> <?php esc_html_e( 'Only approved reports can be downloaded as PDF', 'cleanup-report-generator' ); ?></label>
            <p class="description"><?php esc_html_e( 'Until a report is approved, staff can preview it in the browser, with the watermark below across every page. Turn this off to allow downloading earlier; those PDFs carry the same watermark.', 'cleanup-report-generator' ); ?></p>
            <p>
                <label for="crg_watermark_text"><strong><?php esc_html_e( 'Watermark on reports that aren\'t approved', 'cleanup-report-generator' ); ?></strong></label><br>
                <input type="text" id="crg_watermark_text" name="crg_watermark_text" value="<?php echo esc_attr( Cleanup_Report_Admin::watermark() ); ?>" class="regular-text" maxlength="40" placeholder="NOT FINAL">
            </p>
            <p class="description"><?php esc_html_e( 'Up to 40 characters, e.g. NOT FINAL, DRAFT or FOR REVIEW ONLY. Longer text is set smaller to fit the page.', 'cleanup-report-generator' ); ?></p>

            <h2 class="title"><?php esc_html_e( 'Data retention', 'cleanup-report-generator' ); ?></h2>
            <?php
            $retention_days = Cleanup_Report_Retention::days();
            $retention_due  = Cleanup_Report_Retention::due_count();
            $retention_next = wp_next_scheduled( Cleanup_Report_Retention::CRON_HOOK );
            $retention_last = get_option( Cleanup_Report_Retention::LAST_RUN );
            ?>
            <p>
                <label for="crg_retention_days"><strong><?php esc_html_e( 'Delete reports nobody has edited for', 'cleanup-report-generator' ); ?></strong></label><br>
                <input type="number" id="crg_retention_days" name="<?php echo esc_attr( Cleanup_Report_Retention::OPTION ); ?>" value="<?php echo esc_attr( $retention_days ); ?>" min="0" max="<?php echo (int) Cleanup_Report_Retention::MAX_DAYS; ?>" step="1" class="small-text"> <?php esc_html_e( 'days', 'cleanup-report-generator' ); ?>
            </p>
            <p class="description"><?php echo wp_kses( __( 'Checked once a day. A report is deleted <strong>permanently</strong>, including the Trash, with its history and the screenshots uploaded to it (a screenshot another report uses is kept; images chosen from the Media Library that belong elsewhere are never deleted). This can\'t be undone. Use 0 to keep reports forever.', 'cleanup-report-generator' ), [ 'strong' => [] ] ); ?></p>
            <p class="description">
                <?php if ( ! $retention_days ) : ?>
                    <?php esc_html_e( 'Retention is off: reports are kept until someone deletes them.', 'cleanup-report-generator' ); ?>
                <?php else : ?>
                    <?php
                    if ( ! $retention_due ) {
                        /* translators: %d: number of days */
                        echo esc_html( sprintf( _n( 'Nothing to delete yet: every report was edited in the last %d day.', 'Nothing to delete yet: every report was edited in the last %d days.', $retention_days, 'cleanup-report-generator' ), $retention_days ) );
                    } else {
                        /* translators: 1: number of reports, 2: number of days */
                        echo esc_html( sprintf( _n( '%1$d report hasn\'t been edited for %2$d days.', '%1$d reports haven\'t been edited for %2$d days.', $retention_due, 'cleanup-report-generator' ), $retention_due, $retention_days ) );
                    }
                    ?>
                    <?php if ( $retention_due && $retention_next ) : ?>
                        <?php
                        /* translators: %s: date and time of the next cleanup */
                        echo esc_html( sprintf( _n( 'The next cleanup, around %s, deletes it.', 'The next cleanup, around %s, deletes them.', $retention_due, 'cleanup-report-generator' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $retention_next ) ) );
                        ?>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ( is_array( $retention_last ) && ! empty( $retention_last['time'] ) ) : ?>
                    <?php
                    /* translators: %d: number of reports */
                    $retention_reports = sprintf( _n( '%d report', '%d reports', (int) $retention_last['reports'], 'cleanup-report-generator' ), (int) $retention_last['reports'] );
                    /* translators: %d: number of screenshots */
                    $retention_files = sprintf( _n( '%d screenshot', '%d screenshots', (int) $retention_last['files'], 'cleanup-report-generator' ), (int) $retention_last['files'] );
                    /* translators: 1: date and time of the last cleanup, 2: "N reports", 3: "N screenshots" */
                    echo esc_html( sprintf( __( 'Last cleanup %1$s: %2$s and %3$s deleted.', 'cleanup-report-generator' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $retention_last['time'] ), $retention_reports, $retention_files ) );
                    ?>
                <?php endif; ?>
            </p>

            <h2 class="title"><?php esc_html_e( 'Outside services', 'cleanup-report-generator' ); ?></h2>
            <input type="hidden" name="crg_plugin_recheck" value="0">
            <label><input type="checkbox" name="crg_plugin_recheck" value="1" <?php checked( (int) get_option( 'crg_plugin_recheck', 0 ), 1 ); ?>> <?php esc_html_e( 'Re-check the plugin list every day', 'cleanup-report-generator' ); ?></label>
            <p class="description"><?php
            echo wp_kses(
                sprintf(
                    /* translators: 1: number of plugins and themes checked per day, 2: URL of the plugin list, 3: number of days */
                    __( 'Once a day, WP-Cron asks WordPress.org (api.wordpress.org) and WPVulnerability.net for the status of up to %1$d plugins and themes on the <a href="%2$s">plugin list</a> not checked for %3$d days. Only their folder names are sent. Off: the list is updated only when someone runs "Check plugins &amp; themes" on a report or clicks "Re-check WordPress.org now".', 'cleanup-report-generator' ),
                    (int) Cleanup_Report_Plugin_Registry::REFRESH_BATCH,
                    esc_url( self::url( 'plugins' ) ),
                    (int) Cleanup_Report_Plugin_Registry::REFRESH_DAYS
                ),
                [ 'a' => [ 'href' => [] ] ]
            );
            ?></p>

            <h2 class="title"><?php esc_html_e( 'Removing the plugin', 'cleanup-report-generator' ); ?></h2>
            <input type="hidden" name="crg_delete_data" value="0">
            <label><input type="checkbox" name="crg_delete_data" value="1" <?php checked( (int) get_option( 'crg_delete_data', 0 ), 1 ); ?>> <?php esc_html_e( 'Delete all reports and settings when the plugin is deleted', 'cleanup-report-generator' ); ?></label>
            <p class="description"><?php esc_html_e( 'Off (recommended): deleting the plugin leaves every report and setting in place, so reinstalling brings them back. On: deleting it in Plugins removes all reports (with their history), every setting, the plugin list and the plugin\'s cache folder, permanently. Screenshots stay in the Media Library. Deactivating never deletes anything.', 'cleanup-report-generator' ); ?></p>

            <h2 class="title"><?php esc_html_e( 'Buddyleads', 'cleanup-report-generator' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Who can approve reports or send them back, besides administrators (who have full access). Only Editors and administrators are listed: approving saves someone else\'s report, which WordPress allows only from the Editor role up.', 'cleanup-report-generator' ); ?></p>
            <input type="hidden" name="<?php echo esc_attr( Cleanup_Report_Admin::BUDDYLEADS_OPTION ); ?>[]" value="0">
            <fieldset class="crg-leads">
                <?php foreach ( $users as $u ) : ?>
                    <label><input type="checkbox" name="<?php echo esc_attr( Cleanup_Report_Admin::BUDDYLEADS_OPTION ); ?>[]" value="<?php echo (int) $u->ID; ?>" <?php checked( in_array( (int) $u->ID, $leads, true ) ); ?>> <?php echo esc_html( $u->display_name ); ?> <span class="crg-muted"><?php echo esc_html( $u->user_email ); ?></span></label>
                <?php endforeach; ?>
            </fieldset>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    private static function tab_plugins() {
        $list    = Cleanup_Report_Plugin_Registry::all();
        $filters = [
            ''          => __( 'All', 'cleanup-report-generator' ),
            'undecided' => __( 'Needs a decision', 'cleanup-report-generator' ),
            'bad'       => __( 'Not maintained', 'cleanup-report-generator' ),
            'vulns'     => __( 'Unfixed vulnerabilities', 'cleanup-report-generator' ),
            'ok'        => __( 'Maintained', 'cleanup-report-generator' ),
        ];
        $match = static function ( $e, $f ) {
            $st = Cleanup_Report_Plugin_Registry::status( $e );
            switch ( $f ) {
                case 'undecided':
                    return 'undecided' === $st;
                case 'bad':
                    return in_array( $st, [ 'abandoned', 'closed' ], true );
                case 'vulns':
                    return $e['open_vulns'] > 0;
                case 'ok':
                    return 'maintained' === $st;
            }
            return true;
        };
        $filter = sanitize_key( wp_unslash( $_GET['filter'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter, read-only
        $filter = isset( $filters[ $filter ] ) ? $filter : '';
        $rows   = array_filter( $list, static function ( $e ) use ( $match, $filter ) { return $match( $e, $filter ); } );
        $link   = static function ( $action ) { return wp_nonce_url( admin_url( 'admin-post.php?action=' . $action ), $action ); };
        $labels = [ 'maintained' => __( 'Maintained', 'cleanup-report-generator' ), 'abandoned' => __( 'Not maintained', 'cleanup-report-generator' ), 'closed' => __( 'Closed', 'cleanup-report-generator' ), 'undecided' => __( 'Needs a decision', 'cleanup-report-generator' ) ];
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- notices only
        ?>
        <?php if ( isset( $_GET['saved'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Plugin list saved.', 'cleanup-report-generator' ); ?></p></div><?php endif; ?>
        <?php if ( isset( $_GET['refreshed'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php
        /* translators: %d: number of plugins and themes re-checked */
        echo esc_html( sprintf( _n( '%d item re-checked on WordPress.org.', '%d items re-checked on WordPress.org.', (int) $_GET['refreshed'], 'cleanup-report-generator' ), (int) $_GET['refreshed'] ) );
        ?></p></div><?php endif; ?>
        <?php // phpcs:enable ?>
        <p class="crg-lead"><?php echo wp_kses( __( 'Every plugin and theme checked on a report is added here automatically. <strong>WordPress.org</strong> items keep their status up to date by themselves (re-checked weekly). Anything else (premium or custom) needs a one-time decision: is it still maintained? Reports then use that answer.', 'cleanup-report-generator' ), [ 'strong' => [] ] ); ?></p>
        <div class="crg-pl-bar">
            <ul class="subsubsub">
                <?php
                $i = 0;
                foreach ( $filters as $key => $label ) :
                    $count = count( array_filter( $list, static function ( $e ) use ( $match, $key ) { return $match( $e, $key ); } ) );
                    ?>
                    <li><?php echo $i++ ? ' | ' : ''; ?><a href="<?php echo esc_url( add_query_arg( 'filter', $key, Cleanup_Report_Settings::url( 'plugins' ) ) ); ?>" class="<?php echo $key === $filter ? 'current' : ''; ?>"><?php echo esc_html( $label ); ?> <span class="count">(<?php echo (int) $count; ?>)</span></a></li>
                <?php endforeach; ?>
            </ul>
            <span class="crg-pl-tools">
                <input type="search" class="crg-pl-search" placeholder="<?php esc_attr_e( 'Filter by name or folder', 'cleanup-report-generator' ); ?>" aria-label="<?php esc_attr_e( 'Filter by name or folder', 'cleanup-report-generator' ); ?>">
                <a class="button" href="<?php echo esc_url( $link( 'crg_refresh_plugin_list' ) ); ?>"><?php esc_html_e( 'Re-check WordPress.org now', 'cleanup-report-generator' ); ?></a>
                <a class="button" href="<?php echo esc_url( $link( 'crg_export_plugin_list' ) ); ?>"><?php esc_html_e( 'Download CSV', 'cleanup-report-generator' ); ?></a>
            </span>
        </div>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="crg_save_plugin_list">
            <input type="hidden" name="filter" value="<?php echo esc_attr( $filter ); ?>">
            <?php wp_nonce_field( 'crg_save_plugin_list' ); ?>
            <table class="crg-table crg-pl">
                <thead><tr><th><?php esc_html_e( 'Plugin / theme', 'cleanup-report-generator' ); ?></th><th class="crg-pl-st"><?php esc_html_e( 'Status', 'cleanup-report-generator' ); ?></th><th><?php esc_html_e( 'Why', 'cleanup-report-generator' ); ?></th><th class="crg-pl-seen"><?php esc_html_e( 'Seen in', 'cleanup-report-generator' ); ?></th><th class="crg-pl-dec"><?php esc_html_e( 'Your decision', 'cleanup-report-generator' ); ?></th><th><?php esc_html_e( 'Vendor or changelog link', 'cleanup-report-generator' ); ?></th><th><?php esc_html_e( 'Note', 'cleanup-report-generator' ); ?></th><th class="crg-col-x" title="<?php esc_attr_e( 'Delete', 'cleanup-report-generator' ); ?>"><span class="dashicons dashicons-trash"></span></th></tr></thead>
                <tbody>
                <?php if ( ! $rows ) : ?>
                    <tr><td colspan="8" class="crg-pl-empty"><?php echo esc_html( $list ? __( 'Nothing here.', 'cleanup-report-generator' ) : __( 'Nothing yet: run "Check plugins & themes" on a report and its plugins appear here.', 'cleanup-report-generator' ) ); ?></td></tr>
                <?php endif; ?>
                <?php
                // One query for the reports each row links to, not one per row.
                _prime_post_caches( array_values( array_unique( array_filter( array_map( static function ( $e ) { return (int) ( $e['reports'][0] ?? 0 ); }, $rows ) ) ) ), false, false );
                foreach ( $rows as $key => $e ) : self::plugin_row( $key, $e, $labels ); endforeach;
                ?>
                </tbody>
            </table>
            <details class="crg-pl-add">
                <summary><?php esc_html_e( 'Add a premium plugin or theme by hand', 'cleanup-report-generator' ); ?></summary>
                <p>
                    <input type="text" name="new[slug]" placeholder="<?php esc_attr_e( 'Folder, e.g. js_composer', 'cleanup-report-generator' ); ?>" class="code" aria-label="<?php esc_attr_e( 'Folder', 'cleanup-report-generator' ); ?>">
                    <input type="text" name="new[name]" placeholder="<?php esc_attr_e( 'Name', 'cleanup-report-generator' ); ?>" aria-label="<?php esc_attr_e( 'Name', 'cleanup-report-generator' ); ?>">
                    <select name="new[type]" aria-label="<?php esc_attr_e( 'Type', 'cleanup-report-generator' ); ?>"><option value="plugin"><?php esc_html_e( 'Plugin', 'cleanup-report-generator' ); ?></option><option value="theme"><?php esc_html_e( 'Theme', 'cleanup-report-generator' ); ?></option></select>
                    <select name="new[decision]" aria-label="<?php esc_attr_e( 'Decision', 'cleanup-report-generator' ); ?>"><option value=""><?php esc_html_e( 'Check WordPress.org', 'cleanup-report-generator' ); ?></option><option value="maintained"><?php esc_html_e( 'Maintained', 'cleanup-report-generator' ); ?></option><option value="abandoned"><?php esc_html_e( 'Not maintained', 'cleanup-report-generator' ); ?></option></select>
                    <input type="url" name="new[vendor]" placeholder="<?php esc_attr_e( 'Vendor link', 'cleanup-report-generator' ); ?>" aria-label="<?php esc_attr_e( 'Vendor link', 'cleanup-report-generator' ); ?>">
                </p>
            </details>
            <?php submit_button( __( 'Save changes', 'cleanup-report-generator' ) ); ?>
        </form>
        <?php
    }

    private static function plugin_row( $key, array $e, array $labels ) {
        $st    = Cleanup_Report_Plugin_Registry::status( $e );
        $name  = 'items[' . $key . ']';
        $org   = 'wporg' === $e['source'];
        $why   = [];
        if ( $e['reason'] ) {
            $why[] = $e['reason'];
        } elseif ( $e['decision'] ) {
            $why[] = __( 'Decided by staff', 'cleanup-report-generator' );
        } elseif ( $org ) {
            if ( $e['last_release'] ) {
                /* translators: %s: date of the last release */
                $on_org = sprintf( __( 'On WordPress.org, last release %s', 'cleanup-report-generator' ), wp_date( 'j M Y', strtotime( $e['last_release'] ) ) );
            } else {
                $on_org = __( 'On WordPress.org', 'cleanup-report-generator' );
            }
            if ( $e['latest'] ) {
                /* translators: 1: "On WordPress.org…" text, 2: latest version number */
                $on_org = sprintf( _x( '%1$s (%2$s)', 'status and the latest version', 'cleanup-report-generator' ), $on_org, $e['latest'] );
            }
            $why[] = $on_org;
        } else {
            $why[] = __( 'Not on WordPress.org: is it still maintained?', 'cleanup-report-generator' );
        }
        $report = $e['reports'] ? get_edit_post_link( $e['reports'][0] ) : '';
        ?>
        <tr class="crg-pl-row is-<?php echo esc_attr( $st ); ?>" data-search="<?php echo esc_attr( strtolower( $e['name'] . ' ' . $e['slug'] ) ); ?>">
            <td><strong><?php echo esc_html( $e['name'] ); ?></strong><span class="crg-pc-slug"><?php
            /* translators: %s: theme folder name */
            echo esc_html( 'theme' === $e['type'] ? sprintf( __( '%s · theme', 'cleanup-report-generator' ), $e['slug'] ) : $e['slug'] );
            ?></span></td>
            <td class="crg-pl-st">
                <span class="crg-pc-pill is-<?php echo esc_attr( [ 'maintained' => 'ok', 'abandoned' => 'abandoned', 'closed' => 'closed', 'undecided' => 'outdated' ][ $st ] ); ?>"><?php echo esc_html( $labels[ $st ] ); ?></span>
                <?php if ( $e['open_vulns'] ) : ?><span class="crg-pc-pill is-vulnerable"><?php
                /* translators: %d: number of vulnerabilities with no fix */
                echo esc_html( sprintf( _n( '%d unfixed vulnerability', '%d unfixed vulnerabilities', (int) $e['open_vulns'], 'cleanup-report-generator' ), (int) $e['open_vulns'] ) );
                ?></span><?php endif; ?>
            </td>
            <td class="crg-pc-why"><?php echo esc_html( implode( ' ', $why ) ); ?><?php if ( $org ) : ?> <a href="<?php echo esc_url( 'https://wordpress.org/' . $e['type'] . 's/' . strtolower( $e['slug'] ) . '/' ); ?>" target="_blank" rel="noopener">WordPress.org</a><?php endif; ?></td>
            <td class="crg-pl-seen"><?php if ( $e['reports'] ) : ?><a href="<?php echo esc_url( $report ); ?>"><?php
            /* translators: %d: number of reports */
            echo esc_html( sprintf( _n( '%d report', '%d reports', count( $e['reports'] ), 'cleanup-report-generator' ), count( $e['reports'] ) ) );
            ?></a><br><span class="crg-muted"><?php echo esc_html( $e['last_seen'] ? wp_date( 'j M Y', strtotime( $e['last_seen'] ) ) : '' ); ?></span><?php else : ?><span class="crg-muted">—</span><?php endif; ?></td>
            <td class="crg-pl-dec"><select name="<?php echo esc_attr( $name . '[decision]' ); ?>" aria-label="<?php esc_attr_e( 'Decision', 'cleanup-report-generator' ); ?>">
                <option value=""><?php echo esc_html( $org ? __( 'Automatic', 'cleanup-report-generator' ) : __( 'Not decided', 'cleanup-report-generator' ) ); ?></option>
                <option value="maintained" <?php selected( $e['decision'], 'maintained' ); ?>><?php esc_html_e( 'Maintained', 'cleanup-report-generator' ); ?></option>
                <option value="abandoned" <?php selected( $e['decision'], 'abandoned' ); ?>><?php esc_html_e( 'Not maintained', 'cleanup-report-generator' ); ?></option>
            </select></td>
            <td><input type="url" name="<?php echo esc_attr( $name . '[vendor]' ); ?>" value="<?php echo esc_attr( $e['vendor'] ); ?>" placeholder="https://" aria-label="<?php esc_attr_e( 'Vendor link', 'cleanup-report-generator' ); ?>"></td>
            <td><input type="text" name="<?php echo esc_attr( $name . '[note]' ); ?>" value="<?php echo esc_attr( $e['note'] ); ?>" aria-label="<?php esc_attr_e( 'Note', 'cleanup-report-generator' ); ?>"></td>
            <td class="crg-col-x"><input type="checkbox" name="<?php echo esc_attr( $name . '[delete]' ); ?>" value="1" aria-label="<?php
            /* translators: %s: plugin or theme name */
            echo esc_attr( sprintf( __( 'Delete %s', 'cleanup-report-generator' ), $e['name'] ) );
            ?>"></td>
        </tr>
        <?php
    }

    private static function checklist_notes() {
        ?>
        <ul class="crg-notes">
            <li><?php
            /* translators: %s: the drag handle icon */
            echo wp_kses( sprintf( __( 'Drag %s to change the order. Turn an item off to hide it from new reports without deleting it.', 'cleanup-report-generator' ), '<span class="dashicons dashicons-menu"></span>' ), [ 'span' => [ 'class' => [] ] ] );
            ?></li>
            <li><?php esc_html_e( 'Changes apply to a report the next time it\'s saved; reports keep the wording they were saved with.', 'cleanup-report-generator' ); ?></li>
            <li><?php echo wp_kses( __( 'Report texts can use <code>{site}</code>, <code>{host}</code> and <code>{wp_admin}</code>, filled in from the report\'s website URL.', 'cleanup-report-generator' ), [ 'code' => [] ] ); ?></li>
        </ul>
        <?php
    }

    private static function checklist_footer( $list ) {
        $reset = wp_nonce_url( admin_url( 'admin-post.php?action=crg_reset_checklist&list=' . $list ), 'crg_reset_' . $list );
        ?>
        <div class="crg-settings-actions">
            <?php submit_button( __( 'Save changes', 'cleanup-report-generator' ), 'primary', 'submit', false ); ?>
            <a href="<?php echo esc_url( $reset ); ?>" class="crg-reset" data-confirm="<?php esc_attr_e( 'Replace this list with the default items? Your custom items will be removed.', 'cleanup-report-generator' ); ?>"><?php esc_html_e( 'Restore default items', 'cleanup-report-generator' ); ?></a>
        </div>
        <?php
    }

    /** Opening of one repeater card: drag handle, title, on/off switch, remove. */
    private static function item_head( $name, $i, array $item, $is_template, $badge = '' ) {
        $label = $item['label'] ?? '';
        ?>
        <details class="crg-item<?php echo empty( $item['enabled'] ) ? ' is-off' : ''; ?>"<?php echo $is_template ? ' open' : ''; ?>>
            <summary>
                <span class="crg-drag dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'cleanup-report-generator' ); ?>"></span>
                <span class="crg-item-title"><?php echo esc_html( $label ?: __( 'New item', 'cleanup-report-generator' ) ); ?></span>
                <?php if ( $badge ) : ?><span class="crg-tag"><?php echo esc_html( $badge ); ?></span><?php endif; ?>
                <span class="crg-item-tools">
                    <label class="crg-switch" title="<?php esc_attr_e( 'Show on new reports', 'cleanup-report-generator' ); ?>">
                        <input type="checkbox" name="<?php echo esc_attr( "{$name}[{$i}][enabled]" ); ?>" value="1" <?php checked( ! empty( $item['enabled'] ) ); ?>>
                        <span class="crg-switch-ui"></span><span class="screen-reader-text"><?php esc_html_e( 'Show on new reports', 'cleanup-report-generator' ); ?></span>
                    </label>
                    <button type="button" class="button-link crg-item-remove" title="<?php esc_attr_e( 'Delete', 'cleanup-report-generator' ); ?>"><span class="dashicons dashicons-trash"></span><span class="screen-reader-text"><?php esc_html_e( 'Delete', 'cleanup-report-generator' ); ?></span></button>
                </span>
            </summary>
            <input type="hidden" name="<?php echo esc_attr( "{$name}[{$i}][key]" ); ?>" value="<?php echo esc_attr( $item['key'] ?? '' ); ?>">
        <?php
    }

    private static function field( $name, $i, $field, $label, $value, $help = '', $type = 'text', $placeholder = '' ) {
        $id = 'crg-' . md5( "{$name}{$i}{$field}" );
        ?>
        <div class="crg-field">
            <label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
            <?php if ( 'textarea' === $type ) : ?>
                <textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( "{$name}[{$i}][{$field}]" ); ?>" rows="2" placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
            <?php else : ?>
                <input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( "{$name}[{$i}][{$field}]" ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php echo 'label' === $field ? ' class="crg-item-label"' : ''; ?>>
            <?php endif; ?>
            <?php if ( $help ) : ?><p class="description"><?php echo esc_html( $help ); ?></p><?php endif; ?>
        </div>
        <?php
    }

    private static function followup_item( $i, array $item, $is_template = false ) {
        $name = Cleanup_Report_Checklists::FOLLOWUP_OPTION;
        $tone = $item['tone'] ?? 'required';
        self::item_head( $name, $i, $item, $is_template, 'recommended' === $tone ? __( 'Recommended', 'cleanup-report-generator' ) : '' );
        ?>
            <div class="crg-item-body">
                <?php self::field( $name, $i, 'label', __( 'Name', 'cleanup-report-generator' ), $item['label'] ?? '', __( 'Shown in the report and on the edit screen.', 'cleanup-report-generator' ) ); ?>
                <?php self::field( $name, $i, 'hint', __( 'Hint for staff', 'cleanup-report-generator' ), $item['hint'] ?? '', __( 'Edit screen only: when to mark it.', 'cleanup-report-generator' ) ); ?>
                <?php self::field( $name, $i, 'done', __( 'Report text when already configured', 'cleanup-report-generator' ), $item['done'] ?? '', __( 'It was in place before the cleanup, or nothing was needed.', 'cleanup-report-generator' ), 'textarea', __( 'e.g. Your site already has SSL.', 'cleanup-report-generator' ) ); ?>
                <?php self::field( $name, $i, 'fixed', __( 'Report text when done', 'cleanup-report-generator' ), ( $item['fixed'] ?? '' ) ?: ( in_array( $item['key'] ?? '', Cleanup_Report_Checklists::DEFAULT_FIXED, true ) ? Cleanup_Report_Checklists::fixed_text( $item['key'] ) : '' ), __( 'We did it during the cleanup.', 'cleanup-report-generator' ), 'textarea', __( 'e.g. We installed an SSL certificate.', 'cleanup-report-generator' ) ); ?>
                <?php self::field( $name, $i, 'todo', __( 'Report text when the member needs to act', 'cleanup-report-generator' ), $item['todo'] ?? '', '', 'textarea', __( 'e.g. Install an SSL certificate so all traffic is encrypted.', 'cleanup-report-generator' ) ); ?>
                <?php self::field( $name, $i, 'link', __( 'Link on the member\'s site', 'cleanup-report-generator' ), $item['link'] ?? '', __( 'Optional. Shown in the report so the member can open the right screen. {wp_admin} is filled in from the report\'s website URL.', 'cleanup-report-generator' ), 'text', '{wp_admin}/users.php' ); ?>
                <?php self::field( $name, $i, 'docs', __( 'Documentation link for staff', 'cleanup-report-generator' ), $item['docs'] ?? '', __( 'Optional. Shown next to the item on the edit screen.', 'cleanup-report-generator' ), 'url', 'https://' ); ?>
                <div class="crg-field">
                    <span class="crg-field-label"><?php esc_html_e( 'When the member needs to act, label it', 'cleanup-report-generator' ); ?></span>
                    <label class="crg-radio"><input type="radio" name="<?php echo esc_attr( "{$name}[{$i}][tone]" ); ?>" value="required" <?php checked( 'recommended' !== $tone ); ?>> <span class="crg-pill crg-pill-required"><?php esc_html_e( 'Required', 'cleanup-report-generator' ); ?></span></label>
                    <label class="crg-radio"><input type="radio" name="<?php echo esc_attr( "{$name}[{$i}][tone]" ); ?>" value="recommended" <?php checked( 'recommended', $tone ); ?>> <span class="crg-pill crg-pill-recommended"><?php esc_html_e( 'Recommended', 'cleanup-report-generator' ); ?></span></label>
                </div>
            </div>
        </details>
        <?php
    }

    private static function defender_item( $i, array $item, $is_template = false ) {
        $name  = Cleanup_Report_Checklists::DEFENDER_OPTION;
        $owner = $is_template ? 'agent' : Cleanup_Report_Checklists::owner( $item );
        self::item_head( $name, $i, $item, $is_template, 'member' === $owner ? __( 'Member', 'cleanup-report-generator' ) : '' );
        ?>
            <div class="crg-item-body">
                <?php self::field( $name, $i, 'label', __( 'Setting', 'cleanup-report-generator' ), $item['label'] ?? '', __( 'As it appears in the report.', 'cleanup-report-generator' ) ); ?>
                <div class="crg-field">
                    <span class="crg-field-label"><?php esc_html_e( 'To be done by', 'cleanup-report-generator' ); ?></span>
                    <label class="crg-radio"><input type="radio" name="<?php echo esc_attr( "{$name}[{$i}][owner]" ); ?>" value="agent" <?php checked( 'agent', $owner ); ?>> <?php esc_html_e( 'Agent', 'cleanup-report-generator' ); ?></label>
                    <label class="crg-radio"><input type="radio" name="<?php echo esc_attr( "{$name}[{$i}][owner]" ); ?>" value="member" <?php checked( 'member', $owner ); ?>> <?php esc_html_e( 'Member', 'cleanup-report-generator' ); ?></label>
                    <p class="description"><?php esc_html_e( 'Which part of the report\'s Defender section it\'s listed in by default: what we configure during the cleanup, or what the member has to set up.', 'cleanup-report-generator' ); ?></p>
                </div>
                <?php self::field( $name, $i, 'hint', __( 'Hint for staff', 'cleanup-report-generator' ), $item['hint'] ?? '', __( 'Edit screen only: where to find it, what to check.', 'cleanup-report-generator' ) ); ?>
                <?php self::field( $name, $i, 'applied_note', __( 'Report text when applied', 'cleanup-report-generator' ), $item['applied_note'] ?? '', __( 'Used when the report has no note of its own.', 'cleanup-report-generator' ), 'textarea', __( 'Configured to recommended settings.', 'cleanup-report-generator' ) ); ?>
                <?php self::field( $name, $i, 'progress_note', __( 'Report text when pending', 'cleanup-report-generator' ), $item['progress_note'] ?? '', __( 'Not configured yet. Used when the report has no note of its own.', 'cleanup-report-generator' ), 'textarea', __( 'Not configured yet.', 'cleanup-report-generator' ) ); ?>
                <?php self::field( $name, $i, 'pending_note', __( 'Report text when the member needs to act', 'cleanup-report-generator' ), $item['pending_note'] ?? '', __( 'Tell the member what to do. Used when the report has no note of its own.', 'cleanup-report-generator' ), 'textarea', __( 'e.g. Turn this on in {wp_admin} › Defender.', 'cleanup-report-generator' ) ); ?>
                <?php self::field( $name, $i, 'link', __( 'Defender screen for this setting', 'cleanup-report-generator' ), $item['link'] ?? '', __( 'Shown in the report as "Visit to configure". {wp_admin} is filled in from the report\'s website URL, e.g. {wp_admin}/admin.php?page=wdf-ip-lockout&view=local-firewall', 'cleanup-report-generator' ), 'text', '{wp_admin}/admin.php?page=wdf-…' ); ?>
                <?php self::field( $name, $i, 'docs', __( 'Public doc', 'cleanup-report-generator' ), $item['docs'] ?? '', __( 'Optional. Shown in the report as "Public doc", like the team\'s report template.', 'cleanup-report-generator' ), 'url', Cleanup_Report_Checklists::DEFENDER_DOCS . '#…' ); ?>
            </div>
        </details>
        <?php
    }
}
