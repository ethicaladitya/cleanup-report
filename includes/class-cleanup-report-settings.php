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
            add_submenu_page( 'edit.php?post_type=cleanup_report', 'Report settings', 'Settings', 'manage_options', self::SLUG, [ __CLASS__, 'page' ] );
        } elseif ( Cleanup_Report_Access::can_manage() ) {
            add_submenu_page( 'edit.php?post_type=cleanup_report', 'Report settings', 'Settings', 'edit_posts', self::SLUG, [ __CLASS__, 'page' ] );
        }
    }

    /** Capability options.php checks before saving a checklist: Buddyleads pass with edit_posts. */
    public static function checklist_capability( $capability ) {
        return Cleanup_Report_Access::can_manage_checklists() ? 'edit_posts' : $capability;
    }

    public static function plugin_links( $links ) {
        array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">Settings</a>' );
        return $links;
    }

    public static function register() {
        register_setting( 'crg_settings', Cleanup_Report_Presets::OPTION, [ 'default' => 'generic', 'sanitize_callback' => [ 'Cleanup_Report_Presets', 'sanitize' ] ] );
        register_setting( 'crg_settings', 'crg_brand_name', [ 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'crg_settings', 'crg_brand_logo', [ 'sanitize_callback' => 'esc_url_raw' ] );
        register_setting( 'crg_settings', 'crg_brand_logo_light', [ 'sanitize_callback' => 'esc_url_raw' ] );
        foreach ( Cleanup_Report_Render::COLORS as $key => $c ) {
            register_setting( 'crg_settings', 'crg_color_' . $key, [ 'default' => $c[2], 'sanitize_callback' => static function ( $v ) use ( $c ) { return sanitize_hex_color( $v ) ?: $c[2]; } ] );
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
            wp_die( 'You are not allowed to change these settings.', 'Forbidden', [ 'response' => 403, 'back_link' => true ] );
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
            wp_die( 'You are not allowed to view these settings.', 'Forbidden', [ 'response' => 403 ] );
        }
        $tab = sanitize_key( wp_unslash( $_GET['tab'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab switch, read-only
        $tab = 'premium' === $tab ? 'plugins' : $tab; // the tab's old name
        $tab = isset( $tabs[ $tab ] ) ? $tab : array_key_first( $tabs );
        $type_tabs = self::type_tabs();
        ?>
        <div class="wrap crg-settings">
            <h1>Report settings</h1>
            <?php if ( isset( $_GET['crg_reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- notice only ?>
                <div class="notice notice-success is-dismissible"><p>Default items restored.</p></div>
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
            <p class="crg-lead">How the report and PDF are branded.</p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="crg_defaults">Defaults</label></th>
                    <td><select id="crg_defaults" name="<?php echo esc_attr( Cleanup_Report_Presets::OPTION ); ?>">
                        <?php foreach ( Cleanup_Report_Presets::choices() as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( Cleanup_Report_Presets::current(), $value ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">What reports start with when nothing is set: <strong>WPMU DEV</strong> uses the WPMU DEV support team's brand, the Defender checklist, the Hub check and WPMU DEV products in the standard texts; <strong>Generic</strong> uses this site's name and neutral texts. Your saved settings and checklists always win.</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="crg_brand_name">Brand name</label></th>
                    <td><input type="text" id="crg_brand_name" name="crg_brand_name" value="<?php echo esc_attr( get_option( 'crg_brand_name', '' ) ?: Cleanup_Report_Presets::get( 'brand_name' ) ); ?>" class="regular-text">
                    <p class="description">Shown on the cover and in "Prepared by … security team".</p></td>
                </tr>
<?php foreach ( [ 'crg_brand_logo_light' => [ 'Logo for the light theme', 'A dark or coloured logo, for the light cover.' ], 'crg_brand_logo' => [ 'Logo for the dark theme', 'A white logo, for the dark cover.' ] ] as $opt => [ $label, $help ] ) : $logo = get_option( $opt, '' ); ?>
                <tr>
                    <th scope="row"><label for="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $label ); ?></label></th>
                    <td>
                        <div class="crg-logo-field">
                            <span class="crg-logo-preview<?php echo $logo ? '' : ' is-empty'; ?><?php echo 'crg_brand_logo' === $opt ? ' is-dark' : ''; ?>"><?php if ( $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt=""><?php endif; ?></span>
                            <input type="url" id="<?php echo esc_attr( $opt ); ?>" name="<?php echo esc_attr( $opt ); ?>" value="<?php echo esc_url( $logo ); ?>" class="regular-text crg-logo-url" placeholder="https://">
                            <button type="button" class="button crg-media" data-target="#<?php echo esc_attr( $opt ); ?>">Choose image</button>
                        </div>
                        <p class="description"><?php echo esc_html( $help ); ?> Leave empty to show the brand name instead.</p>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <th scope="row">Colour presets</th>
                    <td>
                        <?php foreach ( Cleanup_Report_Render::PRESETS as $key => [ $label, $preset ] ) : ?>
                            <button type="button" class="button crg-preset" data-preset="<?php echo esc_attr( (string) wp_json_encode( $preset ) ); ?>">
                                <?php foreach ( [ 'primary', 'danger', 'warning', 'success' ] as $swatch ) : ?><span class="crg-preset-swatch" style="background:<?php echo esc_attr( $preset[ $swatch ] ); ?>"></span><?php endforeach; ?>
                                <?php echo esc_html( $label ); ?>
                            </button>
                        <?php endforeach; ?>
                        <p class="description">Fills the colours and cover gradient below in one click. Nothing changes until you save; you can still adjust any colour afterwards. Bold is the original look.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="crg_brand_primary">Primary colour</label></th>
                    <td><input type="text" id="crg_brand_primary" name="crg_brand_primary" value="<?php echo esc_attr( get_option( 'crg_brand_primary', '#0059FF' ) ); ?>" class="crg-color" data-default-color="#0059FF">
                    <p class="description">Headings, links and highlights. The default blue is <code>#0059FF</code>.</p></td>
                </tr>
                <tr>
                    <th scope="row">Status colours</th>
                    <td class="crg-colors">
                        <?php foreach ( Cleanup_Report_Render::COLORS as $key => [ $label, $use, $default ] ) : ?>
                            <div class="crg-color-row">
                                <input type="text" id="crg_color_<?php echo esc_attr( $key ); ?>" name="crg_color_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( get_option( 'crg_color_' . $key, $default ) ); ?>" class="crg-color" data-default-color="<?php echo esc_attr( $default ); ?>">
                                <label for="crg_color_<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $label ); ?></strong> <span class="crg-muted"><?php echo esc_html( $use ); ?></span></label>
                            </div>
                        <?php endforeach; ?>
                        <p class="description">Text on each colour is chosen automatically (white or near-black) so it stays readable.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Report theme</th>
                    <td>
                        <?php $theme = get_option( 'crg_report_theme', 'light' ); ?>
                        <label class="crg-radio"><input type="radio" name="crg_report_theme" value="light" <?php checked( 'dark' !== $theme ); ?>> Light (day)</label>
                        <label class="crg-radio"><input type="radio" name="crg_report_theme" value="dark" <?php checked( 'dark', $theme ); ?>> Dark</label>
                        <p class="description">Light prints well and suits most members; dark is a near-black cover and panels. Applies to the PDF and the preview.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="crg_cover_glow">Cover gradient</label></th>
                    <td><input type="number" id="crg_cover_glow" name="crg_cover_glow" value="<?php echo esc_attr( Cleanup_Report_Render::cover_glow( get_option( 'crg_cover_glow', 100 ) ) ); ?>" min="0" max="100" step="5" class="small-text"> %
                    <p class="description">How strong the blue glow on the cover is. 100% is the original look; lower is lighter, and 0 removes it.</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="crg_footer_text">Footer text</label></th>
                    <td><input type="text" id="crg_footer_text" name="crg_footer_text" value="<?php echo esc_attr( get_option( 'crg_footer_text', Cleanup_Report_Render::footer_default() ) ); ?>" class="regular-text">
                    <p class="description">Printed at the bottom of every PDF page. <code>{year}</code> is replaced with the current year.</p></td>
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
            <p class="crg-lead">The <strong>Follow-ups</strong> staff mark on every report. Each one the report includes appears under <em>Your next steps › Recommended actions</em>: as <strong>In place</strong> (already configured), <strong>Done</strong> (we did it), or as <strong>Required</strong>/<strong>Recommended</strong> when the member needs to act.</p>
            <?php self::checklist_notes(); ?>
            <div class="crg-items" data-name="<?php echo esc_attr( Cleanup_Report_Checklists::FOLLOWUP_OPTION ); ?>">
                <?php foreach ( $items as $i => $item ) { self::followup_item( $i, $item ); } ?>
            </div>
            <template class="crg-item-template"><?php self::followup_item( '__i__', [ 'enabled' => true ], true ); ?></template>
            <p><button type="button" class="button crg-item-add"><span class="dashicons dashicons-plus-alt2"></span> Add follow-up</button></p>
            <?php self::checklist_footer( 'followups' ); ?>
        </form>
        <?php
    }

    private static function tab_defender() {
        $items = Cleanup_Report_Checklists::defender_items();
        ?>
        <form method="post" action="options.php" class="crg-settings-form">
            <?php settings_fields( 'crg_defender' ); ?>
            <p class="crg-lead">The <strong>Defender settings</strong> staff mark on every report, in two parts: <strong>to be done by the agent</strong> (what we configure during the cleanup) and <strong>to be done by the member</strong> (what needs the site owner). They appear in the report's <em>Defender security configuration</em> section.</p>
            <?php self::checklist_notes(); ?>
            <div class="crg-items" data-name="<?php echo esc_attr( Cleanup_Report_Checklists::DEFENDER_OPTION ); ?>">
                <?php foreach ( $items as $i => $item ) { self::defender_item( $i, $item ); } ?>
            </div>
            <template class="crg-item-template"><?php self::defender_item( '__i__', [ 'enabled' => true ], true ); ?></template>
            <p><button type="button" class="button crg-item-add"><span class="dashicons dashicons-plus-alt2"></span> Add Defender setting</button></p>
            <?php self::checklist_footer( 'defender' ); ?>
        </form>
        <?php
    }

    private static function tab_optimization() {
        $items = Cleanup_Report_Optimization::check_items();
        ?>
        <form method="post" action="options.php" class="crg-settings-form">
            <?php settings_fields( 'crg_optimization' ); ?>
            <p class="crg-lead">For <strong>Website optimization</strong> reports: the score each average should reach after the work, and the tests staff answer before and after it (the report's <em>Pre-work</em> and <em>Post-work checklist</em>).</p>
            <h2 class="title">Targets</h2>
            <table class="form-table" role="presentation">
                <?php foreach ( [ 'mobile' => 'Mobile', 'desktop' => 'Desktop' ] as $device => $label ) : ?>
                    <tr>
                        <th scope="row"><label for="crg_opt_target_<?php echo esc_attr( $device ); ?>"><?php echo esc_html( $label ); ?> average</label></th>
                        <td><input type="number" id="crg_opt_target_<?php echo esc_attr( $device ); ?>" name="crg_opt_target_<?php echo esc_attr( $device ); ?>" value="<?php echo (int) Cleanup_Report_Optimization::targets()[ $device ]; ?>" min="0" max="100" step="1" class="small-text"></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <p class="description">The report's <em>Optimization result</em> says whether each was reached. A report keeps the targets it was last saved with.</p>
            <h2 class="title">Tests</h2>
            <?php self::checklist_notes(); ?>
            <div class="crg-items" data-name="<?php echo esc_attr( Cleanup_Report_Optimization::CHECKS_OPTION ); ?>">
                <?php foreach ( $items as $i => $item ) { self::opt_item( $i, $item ); } ?>
            </div>
            <template class="crg-item-template"><?php self::opt_item( '__i__', [ 'enabled' => true, 'good' => 'no' ], true ); ?></template>
            <p><button type="button" class="button crg-item-add"><span class="dashicons dashicons-plus-alt2"></span> Add test</button></p>
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
                <?php self::field( $name, $i, 'label', 'Test', $item['label'] ?? '', 'The question, as it appears in the report.' ); ?>
                <?php self::field( $name, $i, 'hint', 'Hint for staff', $item['hint'] ?? '', 'Edit screen only: how to test it.' ); ?>
                <div class="crg-field">
                    <span class="crg-field-label">Good answer</span>
                    <?php foreach ( [ 'yes' => 'Yes', 'no' => 'No', '' => 'Neither (just information)' ] as $value => $label ) : ?>
                        <label class="crg-radio"><input type="radio" name="<?php echo esc_attr( "{$name}[{$i}][good]" ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( $good, $value ); ?>> <?php echo esc_html( $label ); ?></label>
                    <?php endforeach; ?>
                    <p class="description">The report shows the good answer in green and the other in red. N/A is always grey.</p>
                </div>
                <?php self::field( $name, $i, 'pre_note', 'Report note before the work', $item['pre_note'] ?? '', 'Used in the pre-work checklist when the report has no note of its own.', 'textarea' ); ?>
                <?php self::field( $name, $i, 'post_note', 'Report note after the work', $item['post_note'] ?? '', 'Used in the post-work checklist when the report has no note of its own.', 'textarea' ); ?>
            </div>
        </details>
        <?php
    }

    private static function tab_blocklists() {
        $items = Cleanup_Report_Checklists::blocklist_items();
        ?>
        <form method="post" action="options.php" class="crg-settings-form">
            <?php settings_fields( 'crg_blocklists' ); ?>
            <p class="crg-lead">The <strong>security vendors</strong> staff can tick under <em>Security blocklists (delist request)</em> when VirusTotal or another scanner still flags a site. Each one ticked appears in the report with where the member requests delisting.</p>
            <ul class="crg-notes">
                <li>Drag <span class="dashicons dashicons-menu"></span> to change the order. Turn a vendor off to hide it from new reports without deleting it.</li>
                <li>Use the vendor's name as VirusTotal shows it, so staff can find it quickly.</li>
                <li>Reports keep the contact details they were saved with.</li>
            </ul>
            <div class="crg-items" data-name="<?php echo esc_attr( Cleanup_Report_Checklists::BLOCKLIST_OPTION ); ?>">
                <?php foreach ( $items as $i => $item ) { self::blocklist_item( $i, $item ); } ?>
            </div>
            <template class="crg-item-template"><?php self::blocklist_item( '__i__', [ 'enabled' => true ], true ); ?></template>
            <p><button type="button" class="button crg-item-add"><span class="dashicons dashicons-plus-alt2"></span> Add vendor</button></p>
            <?php self::checklist_footer( 'blocklists' ); ?>
        </form>
        <?php
    }

    private static function blocklist_item( $i, array $item, $is_template = false ) {
        $name = Cleanup_Report_Checklists::BLOCKLIST_OPTION;
        self::item_head( $name, $i, $item, $is_template );
        ?>
            <div class="crg-item-body">
                <?php self::field( $name, $i, 'label', 'Vendor', $item['label'] ?? '', 'As VirusTotal shows it.' ); ?>
                <?php self::field( $name, $i, 'url', 'Delisting form', $item['url'] ?? '', 'Where the site owner reports a false positive or asks for a review.', 'url', 'https://' ); ?>
                <?php self::field( $name, $i, 'email', 'Email', $item['email'] ?? '', 'Optional. Only if the vendor accepts delisting requests by email.', 'email', 'falsepositive@vendor.com' ); ?>
                <?php self::field( $name, $i, 'note', 'How to ask', $item['note'] ?? '', 'Optional. Shown in the report under the contact, e.g. a required subject line or the option to choose.' ); ?>
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
            <p class="crg-lead">How a report moves from draft to sent:</p>
            <ol class="crg-flow">
                <li><span class="crg-status-badge crg-status-draft">Draft</span> Anyone writing reports can draft one and <strong>submit it for review</strong>.</li>
                <li><span class="crg-status-badge crg-status-needs_revision">Needs revision</span> / <span class="crg-status-badge crg-status-approved">Approved</span> Only <strong>Buddyleads</strong> and administrators can approve a report or send it back with notes.</li>
                <li><span class="crg-status-badge crg-status-complete">Completed</span> Once approved, anyone can mark it completed after sending it to the member.</li>
            </ol>
            <h2 class="title">Who can do what</h2>
            <table class="crg-table crg-access">
                <thead><tr><th>Who</th><th>WordPress role</th><th>Their own reports</th><th>Others' reports</th><th>Approve / send back</th><th>Settings</th></tr></thead>
                <tbody>
                    <tr><td><strong>Writers</strong><br><span class="crg-muted">support staff who write reports</span></td><td>Editor</td><td>Create, edit, submit for review</td><td>Preview; download the PDF and mark Completed once approved</td><td>—</td><td>—</td></tr>
                    <tr><td><strong>Buddyleads</strong><br><span class="crg-muted">ticked below</span></td><td>Editor</td><td>Everything</td><td>Open and edit any report</td><td>✓</td><td>Plugin list, and the checklists: Follow-ups, Defender checklist, Blocklists, Website optimization</td></tr>
                    <tr><td><strong>Administrators</strong></td><td>Administrator</td><td>Everything</td><td>Everything</td><td>✓</td><td>All tabs, including Branding and Review</td></tr>
                </tbody>
            </table>
            <p class="description">Approved reports are read-only for Writers, except marking them Completed. Give everyone who works on reports the <strong>Editor</strong> role in <a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>">Users</a> (<a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>">add someone</a>). Lower roles can't save someone else's report (WordPress asks for Editor), so a Buddylead couldn't approve and a writer couldn't mark a report Completed. The report rules above still apply to Editors.</p>

            <h2 class="title">PDF download</h2>
            <input type="hidden" name="crg_pdf_requires_approval" value="0">
            <label><input type="checkbox" name="crg_pdf_requires_approval" value="1" <?php checked( (int) get_option( 'crg_pdf_requires_approval', 1 ), 1 ); ?>> Only approved reports can be downloaded as PDF</label>
            <p class="description">Until a report is approved, staff can preview it in the browser, with the watermark below across every page. Turn this off to allow downloading earlier; those PDFs carry the same watermark.</p>
            <p>
                <label for="crg_watermark_text"><strong>Watermark on reports that aren't approved</strong></label><br>
                <input type="text" id="crg_watermark_text" name="crg_watermark_text" value="<?php echo esc_attr( Cleanup_Report_Admin::watermark() ); ?>" class="regular-text" maxlength="40" placeholder="NOT FINAL">
            </p>
            <p class="description">Up to 40 characters, e.g. NOT FINAL, DRAFT or FOR REVIEW ONLY. Longer text is set smaller to fit the page.</p>

            <h2 class="title">Data retention</h2>
            <?php
            $retention_days = Cleanup_Report_Retention::days();
            $retention_due  = Cleanup_Report_Retention::due_count();
            $retention_next = wp_next_scheduled( Cleanup_Report_Retention::CRON_HOOK );
            $retention_last = get_option( Cleanup_Report_Retention::LAST_RUN );
            ?>
            <p>
                <label for="crg_retention_days"><strong>Delete reports nobody has edited for</strong></label><br>
                <input type="number" id="crg_retention_days" name="<?php echo esc_attr( Cleanup_Report_Retention::OPTION ); ?>" value="<?php echo esc_attr( $retention_days ); ?>" min="0" max="<?php echo (int) Cleanup_Report_Retention::MAX_DAYS; ?>" step="1" class="small-text"> days
            </p>
            <p class="description">Checked once a day. A report is deleted <strong>permanently</strong>, including the Trash, with its history and its screenshots in the Media Library (a screenshot another report uses is kept). This can't be undone. Use 0 to keep reports forever.</p>
            <p class="description">
                <?php if ( ! $retention_days ) : ?>
                    Retention is off: reports are kept until someone deletes them.
                <?php else : ?>
                    <?php
                    if ( ! $retention_due ) {
                        echo esc_html( sprintf( 'Nothing to delete yet: every report was edited in the last %d days.', $retention_days ) );
                    } else {
                        echo esc_html( sprintf( '%d %s been edited for %d days.', $retention_due, 1 === $retention_due ? 'report hasn\'t' : 'reports haven\'t', $retention_days ) );
                    }
                    ?>
                    <?php if ( $retention_due && $retention_next ) : ?>
                        <?php echo esc_html( sprintf( 'The next cleanup, around %s, deletes %s.', wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $retention_next ), 1 === $retention_due ? 'it' : 'them' ) ); ?>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ( is_array( $retention_last ) && ! empty( $retention_last['time'] ) ) : ?>
                    <?php echo esc_html( sprintf( 'Last cleanup %s: %d %s and %d %s deleted.', wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $retention_last['time'] ), (int) $retention_last['reports'], 1 === (int) $retention_last['reports'] ? 'report' : 'reports', (int) $retention_last['files'], 1 === (int) $retention_last['files'] ? 'screenshot' : 'screenshots' ) ); ?>
                <?php endif; ?>
            </p>

            <h2 class="title">Outside services</h2>
            <input type="hidden" name="crg_plugin_recheck" value="0">
            <label><input type="checkbox" name="crg_plugin_recheck" value="1" <?php checked( (int) get_option( 'crg_plugin_recheck', 0 ), 1 ); ?>> Re-check the plugin list every day</label>
            <p class="description">Once a day, WP-Cron asks WordPress.org (api.wordpress.org) and WPVulnerability.net for the status of up to <?php echo (int) Cleanup_Report_Plugin_Registry::REFRESH_BATCH; ?> plugins and themes on the <a href="<?php echo esc_url( self::url( 'plugins' ) ); ?>">plugin list</a> not checked for <?php echo (int) Cleanup_Report_Plugin_Registry::REFRESH_DAYS; ?> days. Only their folder names are sent. Off: the list is updated only when someone runs "Check plugins &amp; themes" on a report or clicks "Re-check WordPress.org now".</p>

            <h2 class="title">Removing the plugin</h2>
            <input type="hidden" name="crg_delete_data" value="0">
            <label><input type="checkbox" name="crg_delete_data" value="1" <?php checked( (int) get_option( 'crg_delete_data', 0 ), 1 ); ?>> Delete all reports and settings when the plugin is deleted</label>
            <p class="description">Off (recommended): deleting the plugin leaves every report and setting in place, so reinstalling brings them back. On: deleting it in Plugins removes all reports (with their history), every setting, the plugin list and the plugin's cache folder, permanently. Screenshots stay in the Media Library. Deactivating never deletes anything.</p>

            <h2 class="title">Buddyleads</h2>
            <p class="description">Who can approve reports or send them back, besides administrators (who have full access). Only Editors and administrators are listed: approving saves someone else's report, which WordPress allows only from the Editor role up.</p>
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
            ''          => 'All',
            'undecided' => 'Needs a decision',
            'bad'       => 'Not maintained',
            'vulns'     => 'Unfixed vulnerabilities',
            'ok'        => 'Maintained',
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
        $labels = [ 'maintained' => 'Maintained', 'abandoned' => 'Not maintained', 'closed' => 'Closed', 'undecided' => 'Needs a decision' ];
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- notices only
        ?>
        <?php if ( isset( $_GET['saved'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Plugin list saved.</p></div><?php endif; ?>
        <?php if ( isset( $_GET['refreshed'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php echo (int) $_GET['refreshed']; ?> items re-checked on WordPress.org.</p></div><?php endif; ?>
        <?php // phpcs:enable ?>
        <p class="crg-lead">Every plugin and theme checked on a report is added here automatically. <strong>WordPress.org</strong> items keep their status up to date by themselves (re-checked weekly). Anything else (premium or custom) needs a one-time decision: is it still maintained? Reports then use that answer.</p>
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
                <input type="search" class="crg-pl-search" placeholder="Filter by name or folder" aria-label="Filter by name or folder">
                <a class="button" href="<?php echo esc_url( $link( 'crg_refresh_plugin_list' ) ); ?>">Re-check WordPress.org now</a>
                <a class="button" href="<?php echo esc_url( $link( 'crg_export_plugin_list' ) ); ?>">Download CSV</a>
            </span>
        </div>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="crg_save_plugin_list">
            <input type="hidden" name="filter" value="<?php echo esc_attr( $filter ); ?>">
            <?php wp_nonce_field( 'crg_save_plugin_list' ); ?>
            <table class="crg-table crg-pl">
                <thead><tr><th>Plugin / theme</th><th class="crg-pl-st">Status</th><th>Why</th><th class="crg-pl-seen">Seen in</th><th class="crg-pl-dec">Your decision</th><th>Vendor or changelog link</th><th>Note</th><th class="crg-col-x" title="Delete"><span class="dashicons dashicons-trash"></span></th></tr></thead>
                <tbody>
                <?php if ( ! $rows ) : ?>
                    <tr><td colspan="8" class="crg-pl-empty"><?php echo $list ? 'Nothing here.' : 'Nothing yet: run "Check plugins & themes" on a report and its plugins appear here.'; ?></td></tr>
                <?php endif; ?>
                <?php foreach ( $rows as $key => $e ) : self::plugin_row( $key, $e, $labels ); endforeach; ?>
                </tbody>
            </table>
            <details class="crg-pl-add">
                <summary>Add a premium plugin or theme by hand</summary>
                <p>
                    <input type="text" name="new[slug]" placeholder="Folder, e.g. js_composer" class="code" aria-label="Folder">
                    <input type="text" name="new[name]" placeholder="Name" aria-label="Name">
                    <select name="new[type]" aria-label="Type"><option value="plugin">Plugin</option><option value="theme">Theme</option></select>
                    <select name="new[decision]" aria-label="Decision"><option value="">Check WordPress.org</option><option value="maintained">Maintained</option><option value="abandoned">Not maintained</option></select>
                    <input type="url" name="new[vendor]" placeholder="Vendor link" aria-label="Vendor link">
                </p>
            </details>
            <?php submit_button( 'Save changes' ); ?>
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
            $why[] = 'Decided by staff';
        } elseif ( $org ) {
            $why[] = 'On WordPress.org' . ( $e['last_release'] ? ', last release ' . wp_date( 'j M Y', strtotime( $e['last_release'] ) ) : '' ) . ( $e['latest'] ? ' (' . $e['latest'] . ')' : '' );
        } else {
            $why[] = 'Not on WordPress.org: is it still maintained?';
        }
        $report = $e['reports'] ? get_edit_post_link( $e['reports'][0] ) : '';
        ?>
        <tr class="crg-pl-row is-<?php echo esc_attr( $st ); ?>" data-search="<?php echo esc_attr( strtolower( $e['name'] . ' ' . $e['slug'] ) ); ?>">
            <td><strong><?php echo esc_html( $e['name'] ); ?></strong><span class="crg-pc-slug"><?php echo esc_html( $e['slug'] . ( 'theme' === $e['type'] ? ' · theme' : '' ) ); ?></span></td>
            <td class="crg-pl-st">
                <span class="crg-pc-pill is-<?php echo esc_attr( [ 'maintained' => 'ok', 'abandoned' => 'abandoned', 'closed' => 'closed', 'undecided' => 'outdated' ][ $st ] ); ?>"><?php echo esc_html( $labels[ $st ] ); ?></span>
                <?php if ( $e['open_vulns'] ) : ?><span class="crg-pc-pill is-vulnerable"><?php echo (int) $e['open_vulns']; ?> unfixed <?php echo 1 === (int) $e['open_vulns'] ? 'vulnerability' : 'vulnerabilities'; ?></span><?php endif; ?>
            </td>
            <td class="crg-pc-why"><?php echo esc_html( implode( ' ', $why ) ); ?><?php if ( $org ) : ?> <a href="<?php echo esc_url( 'https://wordpress.org/' . $e['type'] . 's/' . strtolower( $e['slug'] ) . '/' ); ?>" target="_blank" rel="noopener">WordPress.org</a><?php endif; ?></td>
            <td class="crg-pl-seen"><?php if ( $e['reports'] ) : ?><a href="<?php echo esc_url( $report ); ?>"><?php echo (int) count( $e['reports'] ); ?> <?php echo 1 === count( $e['reports'] ) ? 'report' : 'reports'; ?></a><br><span class="crg-muted"><?php echo esc_html( $e['last_seen'] ? wp_date( 'j M Y', strtotime( $e['last_seen'] ) ) : '' ); ?></span><?php else : ?><span class="crg-muted">—</span><?php endif; ?></td>
            <td class="crg-pl-dec"><select name="<?php echo esc_attr( $name . '[decision]' ); ?>" aria-label="Decision">
                <option value=""><?php echo $org ? 'Automatic' : 'Not decided'; ?></option>
                <option value="maintained" <?php selected( $e['decision'], 'maintained' ); ?>>Maintained</option>
                <option value="abandoned" <?php selected( $e['decision'], 'abandoned' ); ?>>Not maintained</option>
            </select></td>
            <td><input type="url" name="<?php echo esc_attr( $name . '[vendor]' ); ?>" value="<?php echo esc_attr( $e['vendor'] ); ?>" placeholder="https://" aria-label="Vendor link"></td>
            <td><input type="text" name="<?php echo esc_attr( $name . '[note]' ); ?>" value="<?php echo esc_attr( $e['note'] ); ?>" aria-label="Note"></td>
            <td class="crg-col-x"><input type="checkbox" name="<?php echo esc_attr( $name . '[delete]' ); ?>" value="1" aria-label="<?php echo esc_attr( 'Delete ' . $e['name'] ); ?>"></td>
        </tr>
        <?php
    }

    private static function checklist_notes() {
        ?>
        <ul class="crg-notes">
            <li>Drag <span class="dashicons dashicons-menu"></span> to change the order. Turn an item off to hide it from new reports without deleting it.</li>
            <li>Changes apply to a report the next time it's saved; reports keep the wording they were saved with.</li>
            <li>Report texts can use <code>{site}</code>, <code>{host}</code> and <code>{wp_admin}</code>, filled in from the report's website URL.</li>
        </ul>
        <?php
    }

    private static function checklist_footer( $list ) {
        $reset = wp_nonce_url( admin_url( 'admin-post.php?action=crg_reset_checklist&list=' . $list ), 'crg_reset_' . $list );
        ?>
        <div class="crg-settings-actions">
            <?php submit_button( 'Save changes', 'primary', 'submit', false ); ?>
            <a href="<?php echo esc_url( $reset ); ?>" class="crg-reset" data-confirm="Replace this list with the default items? Your custom items will be removed.">Restore default items</a>
        </div>
        <?php
    }

    /** Opening of one repeater card: drag handle, title, on/off switch, remove. */
    private static function item_head( $name, $i, array $item, $is_template, $badge = '' ) {
        $label = $item['label'] ?? '';
        ?>
        <details class="crg-item<?php echo empty( $item['enabled'] ) ? ' is-off' : ''; ?>"<?php echo $is_template ? ' open' : ''; ?>>
            <summary>
                <span class="crg-drag dashicons dashicons-menu" title="Drag to reorder"></span>
                <span class="crg-item-title"><?php echo esc_html( $label ?: 'New item' ); ?></span>
                <?php if ( $badge ) : ?><span class="crg-tag"><?php echo esc_html( $badge ); ?></span><?php endif; ?>
                <span class="crg-item-tools">
                    <label class="crg-switch" title="Show on new reports">
                        <input type="checkbox" name="<?php echo esc_attr( "{$name}[{$i}][enabled]" ); ?>" value="1" <?php checked( ! empty( $item['enabled'] ) ); ?>>
                        <span class="crg-switch-ui"></span><span class="screen-reader-text">Show on new reports</span>
                    </label>
                    <button type="button" class="button-link crg-item-remove" title="Delete"><span class="dashicons dashicons-trash"></span><span class="screen-reader-text">Delete</span></button>
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
        self::item_head( $name, $i, $item, $is_template, 'recommended' === $tone ? 'Recommended' : '' );
        ?>
            <div class="crg-item-body">
                <?php self::field( $name, $i, 'label', 'Name', $item['label'] ?? '', 'Shown in the report and on the edit screen.' ); ?>
                <?php self::field( $name, $i, 'hint', 'Hint for staff', $item['hint'] ?? '', 'Edit screen only: when to mark it.' ); ?>
                <?php self::field( $name, $i, 'done', 'Report text when already configured', $item['done'] ?? '', 'It was in place before the cleanup, or nothing was needed.', 'textarea', 'e.g. Your site already has SSL.' ); ?>
                <?php self::field( $name, $i, 'fixed', 'Report text when done', ( $item['fixed'] ?? '' ) ?: ( Cleanup_Report_Checklists::DEFAULT_FIXED[ $item['key'] ?? '' ] ?? '' ), 'We did it during the cleanup.', 'textarea', 'e.g. We installed an SSL certificate.' ); ?>
                <?php self::field( $name, $i, 'todo', 'Report text when the member needs to act', $item['todo'] ?? '', '', 'textarea', 'e.g. Install an SSL certificate so all traffic is encrypted.' ); ?>
                <?php self::field( $name, $i, 'link', 'Link on the member\'s site', $item['link'] ?? '', 'Optional. Shown in the report so the member can open the right screen. {wp_admin} is filled in from the report\'s website URL.', 'text', '{wp_admin}/users.php' ); ?>
                <?php self::field( $name, $i, 'docs', 'Documentation link for staff', $item['docs'] ?? '', 'Optional. Shown next to the item on the edit screen.', 'url', 'https://' ); ?>
                <div class="crg-field">
                    <span class="crg-field-label">When the member needs to act, label it</span>
                    <label class="crg-radio"><input type="radio" name="<?php echo esc_attr( "{$name}[{$i}][tone]" ); ?>" value="required" <?php checked( 'recommended' !== $tone ); ?>> <span class="crg-pill crg-pill-required">Required</span></label>
                    <label class="crg-radio"><input type="radio" name="<?php echo esc_attr( "{$name}[{$i}][tone]" ); ?>" value="recommended" <?php checked( 'recommended', $tone ); ?>> <span class="crg-pill crg-pill-recommended">Recommended</span></label>
                </div>
            </div>
        </details>
        <?php
    }

    private static function defender_item( $i, array $item, $is_template = false ) {
        $name  = Cleanup_Report_Checklists::DEFENDER_OPTION;
        $owner = $is_template ? 'agent' : Cleanup_Report_Checklists::owner( $item );
        self::item_head( $name, $i, $item, $is_template, 'member' === $owner ? 'Member' : '' );
        ?>
            <div class="crg-item-body">
                <?php self::field( $name, $i, 'label', 'Setting', $item['label'] ?? '', 'As it appears in the report.' ); ?>
                <div class="crg-field">
                    <span class="crg-field-label">To be done by</span>
                    <label class="crg-radio"><input type="radio" name="<?php echo esc_attr( "{$name}[{$i}][owner]" ); ?>" value="agent" <?php checked( 'agent', $owner ); ?>> Agent</label>
                    <label class="crg-radio"><input type="radio" name="<?php echo esc_attr( "{$name}[{$i}][owner]" ); ?>" value="member" <?php checked( 'member', $owner ); ?>> Member</label>
                    <p class="description">Which part of the report's Defender section it's listed in by default: what we configure during the cleanup, or what the member has to set up.</p>
                </div>
                <?php self::field( $name, $i, 'hint', 'Hint for staff', $item['hint'] ?? '', 'Edit screen only: where to find it, what to check.' ); ?>
                <?php self::field( $name, $i, 'applied_note', 'Report text when applied', $item['applied_note'] ?? '', 'Used when the report has no note of its own.', 'textarea', 'Configured to recommended settings.' ); ?>
                <?php self::field( $name, $i, 'progress_note', 'Report text when pending', $item['progress_note'] ?? '', 'Not configured yet. Used when the report has no note of its own.', 'textarea', 'Not configured yet.' ); ?>
                <?php self::field( $name, $i, 'pending_note', 'Report text when the member needs to act', $item['pending_note'] ?? '', 'Tell the member what to do. Used when the report has no note of its own.', 'textarea', 'e.g. Turn this on in {wp_admin} › Defender.' ); ?>
                <?php self::field( $name, $i, 'link', 'Defender screen for this setting', $item['link'] ?? '', 'Shown in the report as "Visit to configure". {wp_admin} is filled in from the report\'s website URL, e.g. {wp_admin}/admin.php?page=wdf-ip-lockout&view=local-firewall', 'text', '{wp_admin}/admin.php?page=wdf-…' ); ?>
                <?php self::field( $name, $i, 'docs', 'Public doc', $item['docs'] ?? '', 'Optional. Shown in the report as "Public doc", like the team\'s report template.', 'url', Cleanup_Report_Checklists::DEFENDER_DOCS . '#…' ); ?>
            </div>
        </details>
        <?php
    }
}
