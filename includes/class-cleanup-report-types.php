<?php
/**
 * Report types. Every report is a `cleanup_report` post; its type is the meta crg_report_type. Reports saved before
 * types existed have no such meta and are Malware cleanup reports.
 *
 * A type is a definition (see definitions()): its labels, its sections (each an edit-screen box and a group in the
 * history) and their fields (Cleanup_Report_Fields), the Settings tabs that belong to it, and the template that draws
 * its report. The edit screen, saving and the history come from the definition alone; a type without a template of
 * its own gets the generic report (templates/types/generic.php). Add or change types with the `cleanup_report_types`
 * filter.
 *
 * The type is picked when a report is added (Reports › Add … report, or the chooser behind the list's "Add report"
 * button) and doesn't change afterwards. Branding, review, retention and the plugin list are shared by all types.
 *
 * Every type can be turned off in Reports › Report types: the ready-made ones (this file, and types added by code)
 * through the option crg_types_off, custom types through their own setting. A type that's off can't be picked for a
 * new report; if no report uses it, it doesn't show anywhere else either (menu, list views, filter, Settings tabs).
 * A new install starts with the ready-made types off.
 *
 * A report whose type is no longer registered (its code or its definition was removed) keeps its type and values: it
 * is listed as "Unknown type (key)", is edited and printed with the definition it was last saved with (a custom
 * type's snapshot), and without one shows only its client and website and can't be printed.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Cleanup_Report_Types {

    const META         = 'crg_report_type';
    const DEFAULT_TYPE = 'malware_cleanup';
    const CHOOSER      = 'crg-add-report';

    /** Ready-made types that are turned off (not autoloaded; read with the custom types, in one query). */
    const OFF_OPTION = 'crg_types_off';

    /** The types that come with the plugin: off on a new install. */
    const BUILT_IN = [ 'malware_cleanup', 'site_optimization' ];

    /** Templates bundled with the plugin (templates/types/<name>.php and <name>-body.php). */
    const TEMPLATES = [ 'malware-cleanup', 'site-optimization', 'generic' ];

    /** @var array|null definitions() for this request */
    private static $definitions = null;

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ], 9 ); // before Settings (priority 10), so the Add items come first
        add_action( 'load-post-new.php', [ __CLASS__, 'require_type' ] );
        add_action( 'load-post-new.php', [ __CLASS__, 'name_headings' ], 20 );
        add_action( 'load-post.php', [ __CLASS__, 'name_headings' ] );
        add_action( 'add_meta_boxes_cleanup_report', [ __CLASS__, 'store_new_type' ], 1 );
        add_filter( 'submenu_file', [ __CLASS__, 'submenu_file' ] );
        add_action( 'restrict_manage_posts', [ __CLASS__, 'list_filter' ] );
        add_action( 'pre_get_posts', [ __CLASS__, 'filter_list' ] );
        add_filter( 'views_edit-cleanup_report', [ __CLASS__, 'list_views' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'chooser_assets' ] );
        add_action( 'load-edit.php', [ __CLASS__, 'empty_list' ] );
        add_action( 'admin_notices', [ __CLASS__, 'notices' ] );
        add_filter( 'wp_insert_post_data', [ __CLASS__, 'refuse_off_type' ], 5, 2 );
        add_filter( 'sanitize_option_' . self::OFF_OPTION, [ __CLASS__, 'sanitize_off' ] );
    }

    /** The chooser page uses the plugin's admin stylesheet (the edit and settings screens load it themselves). */
    public static function chooser_assets( $hook ) {
        if ( 'cleanup_report_page_' . self::CHOOSER === $hook ) {
            wp_enqueue_style( 'crg-admin', CLEANUP_REPORT_URL . 'assets/admin.css', [ 'dashicons' ], CLEANUP_REPORT_VERSION . '.' . filemtime( CLEANUP_REPORT_PATH . 'assets/admin.css' ) );
        }
    }

    /* ================================================================== definitions */

    /**
     * Every report type: key => definition. A definition:
     *  - label, add (menu item), about and icon (chooser card; a Dashicons name), eyebrow (cover), title (running header), file (PDF file name part),
     *    suffix (suggested title), prepared_by (cover card: "<brand> <prepared_by>")
     *  - sections: key => [ title, fields => [ field, … ], columns (optional) ]; box ID crg_box_<key>. Fields are
     *    described in Cleanup_Report_Fields. May be a callable returning them, so they're only built when needed
     *    (definitions() runs on every admin page for the menu; get() builds a type's sections).
     *  - settings: Settings tabs of this type, tab key => label (built-in tabs) or [ label, render callable ].
     *  - template: one of TEMPLATES ('generic' when left out).
     *  - on_save: optional callable( $post_id ), after the fields are saved.
     *  - hidden: true when the type is turned off (set from Reports › Report types).
     */
    public static function definitions() {
        if ( null === self::$definitions ) {
            if ( function_exists( 'wp_prime_option_caches' ) ) { // both are read on every admin page (the menu): one query
                wp_prime_option_caches( [ Cleanup_Report_Type_Builder::OPTION, self::OFF_OPTION ] );
            }
            $definitions = (array) apply_filters( 'cleanup_report_types', [
                'malware_cleanup'   => self::malware_cleanup(),
                'site_optimization' => self::site_optimization(),
            ] );
            if ( ! isset( $definitions[ self::DEFAULT_TYPE ] ) || ! is_array( $definitions[ self::DEFAULT_TYPE ] ) ) { // every report saved before types existed is one
                $definitions = [ self::DEFAULT_TYPE => self::malware_cleanup() ] + $definitions;
            }
            $off = self::off();
            foreach ( $definitions as $key => $def ) {
                if ( ! is_array( $def ) || empty( $def['sections'] ) || sanitize_key( $key ) !== $key ) {
                    unset( $definitions[ $key ] );
                    continue;
                }
                $def = self::complete( $key, $def );
                if ( empty( $def['custom'] ) && in_array( $key, $off, true ) ) {
                    $def['hidden'] = true; // a custom type has its own on/off setting
                }
                $definitions[ $key ] = $def;
            }
            self::$definitions = $definitions;
        }
        return self::$definitions;
    }

    /** Ready-made types turned off. Until the upgrade has stored the list, a new install has them all off. */
    public static function off() {
        $off = get_option( self::OFF_OPTION, null );
        if ( ! is_array( $off ) ) {
            $off = (int) get_option( 'crg_db_version', 0 ) > 0 ? [] : self::BUILT_IN;
        }
        return $off;
    }

    /** The option crg_types_off: type keys, whatever writes it. */
    public static function sanitize_off( $value ) {
        $keys = array_map( 'sanitize_key', array_filter( (array) $value, 'is_string' ) );
        return array_slice( array_values( array_unique( array_filter( $keys, 'strlen' ) ) ), 0, 100 );
    }

    /** Turns a ready-made type on or off (Reports › Report types). */
    public static function set_on( $key, $on ) {
        $off = array_diff( self::off(), [ $key ] );
        if ( ! $on ) {
            $off[] = $key;
        }
        update_option( self::OFF_OPTION, array_values( $off ), false ); // not autoloaded: only wp-admin reads it
        self::flush();
    }

    /**
     * Upgrade step (DB_VERSION 8): an install that already had the plugin keeps every ready-made type on, as it has
     * always had them; a new install starts with them off and turns on the ones it needs. Runs once.
     */
    public static function choose_on_upgrade( $had_plugin ) {
        if ( ! is_array( get_option( self::OFF_OPTION, null ) ) ) {
            delete_option( self::OFF_OPTION ); // anything that isn't a list
            add_option( self::OFF_OPTION, $had_plugin ? [] : self::BUILT_IN, '', false );
            self::flush();
        }
    }

    /** A definition with its defaults filled in (labels made from the name, the generic template). */
    public static function complete( $key, array $def ) {
        $label = (string) ( $def['label'] ?? $key );
        $def += [
            /* translators: %s: report type, e.g. "Website optimization" */
            'add'         => sprintf( __( 'Add %s report', 'cleanup-report-generator' ), strtolower( $label ) ),
            'about'       => '',
            /* translators: %s: report type, e.g. "Website optimization" */
            'eyebrow'     => sprintf( __( '%s report', 'cleanup-report-generator' ), $label ),
            'title'       => '',
            'file'        => sanitize_title( $label ) . '-report',
            'suffix'      => strtolower( $label ),
            'prepared_by' => __( 'team', 'cleanup-report-generator' ),
            'settings'    => [],
            'template'    => 'generic',
        ];
        $def['label'] = $label;
        if ( '' === $def['title'] ) {
            $def['title'] = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $def['eyebrow'] ) : strtoupper( $def['eyebrow'] );
        }
        if ( ! in_array( $def['template'], self::TEMPLATES, true ) ) {
            $def['template'] = 'generic';
        }
        return $def;
    }

    /** @var array resolved definitions (sections built), by type */
    private static $resolved = [];

    /** One type's definition with its sections. A key that isn't registered gets unknown(). */
    public static function get( $type ) {
        $type = (string) $type;
        if ( ! isset( self::$resolved[ $type ] ) ) {
            $defs = self::definitions();
            if ( ! isset( $defs[ $type ] ) ) {
                return self::unknown( $type );
            }
            $def = $defs[ $type ];
            if ( is_callable( $def['sections'] ) ) {
                $def['sections'] = (array) call_user_func( $def['sections'] );
            }
            self::$resolved[ $type ] = $def;
        }
        return self::$resolved[ $type ];
    }

    /**
     * A type that isn't registered (any more). With the definition a report was saved with (a custom type's
     * snapshot), that one, so the report still opens and prints as it was; without one, only the client and website
     * section, so editing the report never writes another type's fields into it.
     */
    public static function unknown( $type, $snapshot = null ) {
        if ( is_array( $snapshot ) && ! empty( $snapshot['label'] ) ) {
            $def             = Cleanup_Report_Type_Builder::runtime( $type, $snapshot );
            $def['sections'] = Cleanup_Report_Type_Builder::sections( $type, $snapshot );
            unset( $def['on_save'] ); // the snapshot stays as it is
            return [ 'unknown' => true, 'hidden' => true ] + self::complete( $type, $def );
        }
        return self::complete( $type, [
            /* translators: %s: the key a report's type was saved under, e.g. site_migration */
            'label'    => sprintf( __( 'Unknown type (%s)', 'cleanup-report-generator' ), $type ),
            'unknown'  => true,
            'hidden'   => true,
            'icon'     => 'warning',
            'add'      => '',
            'eyebrow'  => __( 'Report', 'cleanup-report-generator' ),
            'suffix'   => __( 'report', 'cleanup-report-generator' ),
            'file'     => 'report',
            'sections' => [ 'site' => self::site_section() ],
        ] );
    }

    /** Forgets the definitions and counts built for this request (after report types are saved or turned on/off). */
    public static function flush() {
        self::$definitions = null;
        self::$resolved    = [];
        self::$counts      = null;
    }

    /** Types a new report can be added as: all but those turned off (Reports › Report types). */
    public static function addable() {
        return array_filter( self::definitions(), static function ( $def ) { return empty( $def['hidden'] ); } );
    }

    /** Types the Reports list and Settings show: those turned on, and those turned off that reports still use. */
    public static function listed() {
        return array_filter( self::definitions(), static function ( $def, $key ) { return empty( $def['hidden'] ) || self::count( $key ); }, ARRAY_FILTER_USE_BOTH );
    }

    public static function exists( $type ) {
        return isset( self::definitions()[ (string) $type ] );
    }

    /**
     * The report's type as saved, registered or not (see unknown()). A report with no type at all was saved before
     * types existed: it's a Malware cleanup report.
     */
    public static function of( $post_id ) {
        $type = sanitize_key( (string) get_post_meta( $post_id, self::META, true ) );
        return '' !== $type ? $type : self::DEFAULT_TYPE;
    }

    /** The definition a report is edited and drawn with: its type's, or for an unknown type the one it was saved with. */
    public static function for_report( $post_id ) {
        $type = self::of( $post_id );
        return self::exists( $type ) ? self::get( $type ) : self::unknown( $type, get_post_meta( $post_id, Cleanup_Report_Type_Builder::SNAPSHOT, true ) );
    }

    /** Can the report be previewed and printed? Not when its type is unknown and it has no saved definition. */
    public static function printable( $post_id ) {
        if ( self::exists( self::of( $post_id ) ) ) {
            return true;
        }
        $snapshot = get_post_meta( $post_id, Cleanup_Report_Type_Builder::SNAPSHOT, true );
        return is_array( $snapshot ) && ! empty( $snapshot['label'] );
    }

    /** @var array|null report counts by type for this request: type => [ 'live' => n, 'trash' => n ] */
    private static $counts = null;

    /**
     * How many reports there are of each type, in the Trash and out of it (auto-drafts don't count). One grouped query
     * per request, for the menu, the list's views and filter, Settings and Reports › Report types.
     */
    public static function counts() {
        if ( null === self::$counts ) {
            global $wpdb;
            $rows = $wpdb->get_results( $wpdb->prepare( "SELECT m.meta_value AS type, p.post_status AS status, COUNT(*) AS n FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = %s AND p.post_type = 'cleanup_report' AND p.post_status NOT IN ( 'auto-draft', 'inherit' ) GROUP BY m.meta_value, p.post_status", self::META ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one grouped count instead of a query per type; fresh on every admin load
            self::$counts = [];
            foreach ( (array) $rows as $row ) {
                $where = 'trash' === $row->status ? 'trash' : 'live';
                self::$counts[ $row->type ][ $where ] = ( self::$counts[ $row->type ][ $where ] ?? 0 ) + (int) $row->n;
            }
        }
        return self::$counts;
    }

    /** Reports of a type, not counting the Trash (or counting it). */
    public static function count( $type, $with_trash = false ) {
        $n = self::counts()[ (string) $type ] ?? [];
        return (int) ( $n['live'] ?? 0 ) + ( $with_trash ? (int) ( $n['trash'] ?? 0 ) : 0 );
    }

    /** A field of a type by its meta key, or null. */
    public static function field( $type, $key ) {
        foreach ( self::get( $type )['sections'] as $section ) {
            foreach ( $section['fields'] as $f ) {
                if ( ( $f['key'] ?? '' ) === $key ) {
                    return Cleanup_Report_Fields::defaults( $f );
                }
            }
        }
        return null;
    }

    /** "Member & website": the section every built-in type starts with (also for types added by the filter). */
    public static function site_section() {
        return [
            'title'   => __( 'Member & website', 'cleanup-report-generator' ),
            'columns' => 2,
            'fields'  => [
                [ 'key' => 'crg_client_name', 'label' => __( 'Prepared for', 'cleanup-report-generator' ), 'placeholder' => __( 'Member or business name', 'cleanup-report-generator' ), 'help' => __( 'Optional. The cover shows the website as its headline, with "Prepared for …" under it.', 'cleanup-report-generator' ) ],
                [ 'key' => 'crg_site_url', 'type' => 'url', 'url' => 'site', 'label' => __( 'Website URL', 'cleanup-report-generator' ), 'placeholder' => 'https://example.com', 'help' => __( 'Paste any page or wp-admin link; it\'s trimmed to the site address.', 'cleanup-report-generator' ) ],
                [ 'key' => 'crg_report_date', 'type' => 'date', 'label' => __( 'Report date', 'cleanup-report-generator' ), 'default' => wp_date( 'Y-m-d' ) ],
                [ 'key' => 'crg_request_link', 'type' => 'url', 'label' => __( 'Support request link', 'cleanup-report-generator' ), 'placeholder' => Cleanup_Report_Presets::get( 'request_placeholder' ), 'help' => __( 'The member\'s ticket or forum thread. The report ends with "Reply on your support request" linking here.', 'cleanup-report-generator' ) ],
                [ 'key' => 'crg_task_url', 'type' => 'url', 'label' => __( 'Task URL', 'cleanup-report-generator' ), 'placeholder' => 'https://…', 'help' => __( 'Internal: our task for this report. Not shown in the report.', 'cleanup-report-generator' ), 'class' => 'crg-col-right', 'report' => false ],
            ],
        ];
    }

    private static function malware_cleanup() {
        return [
            'label'       => __( 'Malware cleanup', 'cleanup-report-generator' ),
            'icon'        => 'shield',
            'add'         => __( 'Add cleanup report', 'cleanup-report-generator' ),
            'about'       => Cleanup_Report_Presets::is( 'wpmudev' )
                ? __( 'What was found and removed, how the site was verified, Defender settings and the member\'s next steps.', 'cleanup-report-generator' )
                : __( 'What was found and removed, how the site was verified, the security settings and the client\'s next steps.', 'cleanup-report-generator' ),
            'eyebrow'     => __( 'Malware cleanup report', 'cleanup-report-generator' ),
            'title'       => __( 'MALWARE CLEANUP REPORT', 'cleanup-report-generator' ),
            'file'        => 'cleanup-report',
            'suffix'      => __( 'malware cleanup', 'cleanup-report-generator' ),
            'prepared_by' => __( 'security team', 'cleanup-report-generator' ),
            'template'    => 'malware-cleanup',
            'settings'    => [ 'followups' => __( 'Follow-ups', 'cleanup-report-generator' ), 'defender' => __( 'Defender checklist', 'cleanup-report-generator' ), 'blocklists' => __( 'Blocklists', 'cleanup-report-generator' ) ],
            'sections'    => [ __CLASS__, 'malware_sections' ], // built when needed, not on every admin page
        ];
    }

    /** The malware cleanup report's sections (see definitions()). */
    public static function malware_sections() {
        $list_help = __( 'A bulleted list, one per item. Put the folder in brackets and a reason after a dash, e.g. <em>Slider Revolution (revslider) – no licence provided</em>.', 'cleanup-report-generator' );
        $questions = [];
        $notes     = [
            'hosting_type' => __( 'Note, e.g. the host or plan', 'cleanup-report-generator' ),
            'backups'      => __( 'Link to the backups, e.g. in the Hub', 'cleanup-report-generator' ),
            'ssh_accounts' => __( 'Which accounts, and what was done', 'cleanup-report-generator' ),
        ];
        foreach ( Cleanup_Report_Render::site_checks_config() as $key => $check ) {
            $questions[ $key ] = [ $check[0], $check[1], $check[2], $notes[ $key ] ?? __( 'Note', 'cleanup-report-generator' ) ];
        }
        return [
            'site'     => self::site_section(),
            'findings' => [
                'title'  => __( 'Findings', 'cleanup-report-generator' ),
                'fields' => [
                    [ 'key' => 'crg_screenshots', 'type' => 'screenshots', 'typed' => true, 'label' => __( 'Screenshots', 'cleanup-report-generator' ), 'help' => __( 'Scan results before and after the cleanup, plus any evidence. Shown in the report in this order; drag to reorder.', 'cleanup-report-generator' ), 'default_rows' => [
                        [ 'url' => '', 'title' => __( 'Security scan – before cleanup', 'cleanup-report-generator' ), 'type' => 'before' ],
                        [ 'url' => '', 'title' => __( 'Security scan – after cleanup', 'cleanup-report-generator' ), 'type' => 'after' ],
                    ] ],
                    [ 'key' => 'crg_key_finding', 'type' => 'key_finding' ],
                    [ 'key' => 'crg_infection_summary', 'type' => 'editor', 'rows' => 8, 'label' => __( 'Infection summary', 'cleanup-report-generator' ), 'help' => __( 'What was found and how the site was compromised.', 'cleanup-report-generator' ) ],
                    [ 'key' => 'crg_summary_counts', 'type' => 'severity_counts' ],
                    [ 'key' => 'crg_plugin_list', 'type' => 'plugin_check' ],
                    [ 'key' => 'crg_components', 'type' => 'components' ],
                    [ 'key' => 'crg_improvements', 'type' => 'editor', 'rows' => 5, 'label' => __( 'Suggested improvements & hardening', 'cleanup-report-generator' ), 'help' => __( 'Optional: changes that would make the site harder to attack (fewer plugins, replacing risky ones…).', 'cleanup-report-generator' ) ],
                ],
            ],
            'work'     => [
                'title'  => __( 'Work done & verification', 'cleanup-report-generator' ),
                'fields' => [
                    [ 'key' => 'crg_highlights', 'type' => 'editor', 'rows' => 6, 'label' => __( 'Work carried out', 'cleanup-report-generator' ), 'help' => __( 'What we did to clean the site. Shown under <strong>Actions taken</strong>, before the list of standard steps every cleanup includes.', 'cleanup-report-generator' ) ],
                    [ 'key' => 'crg_verification_summary', 'type' => 'editor', 'rows' => 6, 'label' => __( 'Verification', 'cleanup-report-generator' ), 'help' => __( 'How the site was checked after the cleanup, and anything still showing.', 'cleanup-report-generator' ), 'default_new' => static function () { return Cleanup_Report_Render::default_texts()['verification']; } ],
                    [ 'key' => 'crg_additional_sites', 'type' => 'textarea', 'rows' => 2, 'label' => __( 'Additional sites on this hosting account', 'cleanup-report-generator' ), 'placeholder' => __( 'No problem identified', 'cleanup-report-generator' ), 'help' => __( 'Other sites found and what was done about them, one per line. Leave empty for "No problem identified".', 'cleanup-report-generator' ) ],
                    [ 'key' => 'crg_site_checks', 'type' => 'questions', 'label' => __( 'Hosting & access', 'cleanup-report-generator' ), 'help' => __( 'Shown in the report\'s Verification table. Leave a question unanswered to leave it out.', 'cleanup-report-generator' ), 'questions' => $questions ],
                    [ 'key' => 'crg_delist_vendors', 'type' => 'blocklists' ],
                ],
            ],
            'defender' => [
                'title'  => __( 'Defender', 'cleanup-report-generator' ),
                'fields' => [ [ 'key' => 'crg_defender', 'type' => 'defender' ] ],
            ],
            'next'     => [
                'title'  => __( 'Member next steps', 'cleanup-report-generator' ),
                'fields' => [
                    [ 'key' => 'crg_pending_actions', 'type' => 'editor', 'legacy_text' => true, 'rows' => 5, 'label' => __( 'Pending actions', 'cleanup-report-generator' ), 'help' => __( 'Anything the member must still do, as a bulleted or numbered list. Leave empty if nothing is pending.', 'cleanup-report-generator' ) ],
                    [ 'key' => 'crg_followups', 'type' => 'followups' ],
                    [ 'key' => 'crg_reinstall_list', 'type' => 'editor', 'rows' => 4, 'label' => __( 'Reinstall: premium plugins & themes not provided', 'cleanup-report-generator' ), 'help' => $list_help ],
                    [ 'key' => 'crg_replace_list', 'type' => 'editor', 'rows' => 4, 'label' => __( 'Replace: plugins & themes no longer maintained', 'cleanup-report-generator' ), 'help' => $list_help ],
                ],
            ],
            'guidance' => [
                'title'  => __( 'Member guidance', 'cleanup-report-generator' ),
                'fields' => [
                    [ 'key' => 'crg_documentation_links', 'type' => 'textarea', 'rows' => 3, 'label' => __( 'Helpful links', 'cleanup-report-generator' ), 'placeholder' => __( 'Supported PHP versions: https://www.php.net/supported-versions.php', 'cleanup-report-generator' ), 'help' => __( 'One per line as <code>Title: https://…</code>. Links to the Defender docs, 2FA and Hide Login URL are always added.', 'cleanup-report-generator' ) ],
                    [ 'key' => 'crg_seo_note', 'type' => 'note', 'label' => __( 'Search engines (SEO)', 'cleanup-report-generator' ), 'help' => __( 'What to check in Google and how to clean up search results. Leave empty to use the standard text.', 'cleanup-report-generator' ), 'standard' => static function ( $post ) { return Cleanup_Report_Render::default_texts( self::host( $post ) )['seo']; } ],
                    [ 'key' => 'crg_protect_note', 'type' => 'note', 'label' => __( 'Staying protected', 'cleanup-report-generator' ), 'help' => __( 'How to keep the site safe from now on. Leave empty to use the standard text.', 'cleanup-report-generator' ), 'standard' => static function () { return Cleanup_Report_Render::default_texts()['protect']; } ],
                ],
            ],
        ];
    }

    private static function site_optimization() {
        return [
            'label'       => __( 'Website optimization', 'cleanup-report-generator' ),
            'icon'        => 'performance',
            'add'         => __( 'Add optimization report', 'cleanup-report-generator' ),
            'about'       => __( 'PageSpeed scores before and after, the pre- and post-work checklists, the optimization steps and the result against the targets.', 'cleanup-report-generator' ),
            'eyebrow'     => __( 'Website optimization report', 'cleanup-report-generator' ),
            'title'       => __( 'WEBSITE OPTIMIZATION REPORT', 'cleanup-report-generator' ),
            'file'        => 'optimization-report',
            'suffix'      => __( 'website optimization', 'cleanup-report-generator' ),
            'prepared_by' => __( 'experts team', 'cleanup-report-generator' ),
            'template'    => 'site-optimization',
            'settings'    => [ 'optimization' => __( 'Checklist & targets', 'cleanup-report-generator' ) ],
            'on_save'     => [ 'Cleanup_Report_Optimization', 'save_targets' ],
            'sections'    => [ __CLASS__, 'optimization_sections' ], // built when needed, not on every admin page
        ];
    }

    /** The site optimization report's sections (see definitions()). */
    public static function optimization_sections() {
        $shots_help = static function ( $phase ) {
            /* translators: %s: "pre-work" or "post-work" */
            return sprintf( __( 'What the %s checklist found worth showing, e.g. a layout shift. None: the report says "No relevant screenshots."', 'cleanup-report-generator' ), $phase );
        };
        $checklist  = [ 'type' => 'checklist', 'items' => [ 'Cleanup_Report_Optimization', 'check_items' ], 'settings' => 'optimization' ];
        return [
            'site'     => self::site_section(),
            'scores'   => [
                'title'  => __( 'Scores', 'cleanup-report-generator' ),
                'fields' => [
                    [ 'key' => 'crg_opt_scores', 'type' => 'scores' ],
                    [ 'key' => 'crg_opt_json_link', 'type' => 'url', 'label' => __( 'Lighthouse JSON (before)', 'cleanup-report-generator' ), 'placeholder' => 'https://drive.google.com/…', 'help' => __( 'Optional: a link to the shared JSON of the tests before the work. The report says it opens in the Lighthouse viewer.', 'cleanup-report-generator' ) ],
                ],
            ],
            'prework'  => [
                'title'  => __( 'Pre-work checklist', 'cleanup-report-generator' ),
                'fields' => [
                    [ 'key' => 'crg_opt_pre_checks', 'phase' => 'pre', 'note_from' => 'pre_note', 'help' => __( 'Test the site before changing anything.', 'cleanup-report-generator' ) ] + $checklist,
                    [ 'key' => 'crg_opt_pre_shots', 'type' => 'screenshots', 'label' => __( 'Relevant screenshots', 'cleanup-report-generator' ), 'help' => $shots_help( __( 'pre-work', 'cleanup-report-generator' ) ) ],
                ],
            ],
            'optwork'  => [
                'title'  => __( 'Optimization work', 'cleanup-report-generator' ),
                'fields' => [
                    [ 'key' => 'crg_opt_steps', 'type' => 'editor', 'rows' => 10, 'label' => __( 'Optimization steps', 'cleanup-report-generator' ), 'help' => __( 'What was changed, grouped by plugin or tool (e.g. Elementor, Hummingbird, Smush, Hosting). Shown after "For the site optimization, the following steps were taken:".', 'cleanup-report-generator' ) ],
                    [ 'key' => 'crg_opt_code', 'type' => 'code', 'rows' => 8, 'path_key' => 'crg_opt_code_path', 'path_placeholder' => 'wp-content/mu-plugins/site-tweaks.php', 'label' => __( 'Code added', 'cleanup-report-generator' ), 'help' => __( 'Optional: custom code added to the site, e.g. an mu-plugin. Shown in full at the end of the report, so it doesn\'t break up the steps.', 'cleanup-report-generator' ) ],
                    [ 'key' => 'crg_opt_summary', 'type' => 'editor', 'rows' => 5, 'label' => __( 'Optimization summary', 'cleanup-report-generator' ), 'help' => __( 'Shown under <strong>Optimization result</strong>, after whether the mobile and desktop targets were reached (worked out from the scores).', 'cleanup-report-generator' ) ],
                ],
            ],
            'postwork' => [
                'title'  => __( 'Post-work checklist', 'cleanup-report-generator' ),
                'fields' => [
                    [ 'key' => 'crg_opt_post_checks', 'phase' => 'post', 'note_from' => 'post_note', 'copy_from' => 'pre', 'copy_label' => __( 'Same answers as pre-work', 'cleanup-report-generator' ), 'help' => __( 'Test the site again once the work is done.', 'cleanup-report-generator' ) ] + $checklist,
                    [ 'key' => 'crg_opt_post_shots', 'type' => 'screenshots', 'label' => __( 'Relevant screenshots', 'cleanup-report-generator' ), 'help' => $shots_help( __( 'post-work', 'cleanup-report-generator' ) ) ],
                ],
            ],
            'optguide' => [
                'title'  => __( 'Member guidance', 'cleanup-report-generator' ),
                'fields' => [
                    [ 'key' => 'crg_opt_info', 'type' => 'note', 'label' => __( 'Helpful information about the performance', 'cleanup-report-generator' ), 'help' => __( 'How the score is measured and how to keep it. Leave empty to use the standard text.', 'cleanup-report-generator' ), 'standard' => static function () { return Cleanup_Report_Optimization::default_texts()['info']; } ],
                    [ 'key' => 'crg_opt_faq', 'type' => 'note', 'label' => __( 'Questions about the score', 'cleanup-report-generator' ), 'help' => __( 'Why mobile is lower, why the target is 80, why scores differ, Core Web Vitals. Leave empty to use the standard text.', 'cleanup-report-generator' ), 'standard' => static function () { return Cleanup_Report_Optimization::default_texts()['faq']; } ],
                ],
            ],
        ];
    }

    /** The report's website host (for standard texts). */
    private static function host( $post ) {
        return (string) ( wp_parse_url( (string) get_post_meta( $post->ID, 'crg_site_url', true ), PHP_URL_HOST ) ?: '' );
    }

    /* ================================================================== adding a report, Reports list */

    /** The edit-screen link that adds a report of this type. */
    public static function add_url( $type ) {
        return admin_url( 'post-new.php?post_type=cleanup_report&crg_type=' . $type );
    }

    /** The type a new report was asked for (?crg_type=…), or '' when none or unknown. */
    private static function requested() {
        $type = sanitize_key( wp_unslash( $_GET['crg_type'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- picks which form to show
        return self::exists( $type ) ? $type : '';
    }

    /**
     * Reports › Add … report, one per type, replace WordPress's "Add report". The chooser (one card per type) is a
     * hidden page: the list's "Add report" button and the admin bar's New › Report lead to it.
     */
    public static function menu() {
        $parent = 'edit.php?post_type=cleanup_report';
        remove_submenu_page( $parent, 'post-new.php?post_type=cleanup_report' );
        foreach ( self::addable() as $key => $type ) {
            // WordPress prints menu titles as they are: escape them (a custom type's name is the admin's text).
            add_submenu_page( $parent, esc_html( $type['add'] ), esc_html( $type['add'] ), 'edit_posts', 'post-new.php?post_type=cleanup_report&crg_type=' . $key );
        }
        $hook = add_submenu_page( $parent, __( 'Add report', 'cleanup-report-generator' ), __( 'Add report', 'cleanup-report-generator' ), 'edit_posts', self::CHOOSER, [ __CLASS__, 'chooser' ] );
        remove_submenu_page( $parent, self::CHOOSER ); // reachable by URL, not listed
        if ( $hook ) {
            add_action( 'load-' . $hook, static function () {
                if ( ! self::addable() && current_user_can( 'manage_options' ) ) { // nothing to pick yet: turn a type on first
                    wp_safe_redirect( Cleanup_Report_Type_Builder::url() );
                    exit;
                }
                // A page removed from the menu has no admin title; WordPress's header would pass null to strip_tags().
                global $title;
                $title = __( 'Add report', 'cleanup-report-generator' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the admin page title WordPress reads for this hidden page
            } );
        }
    }

    /** Reports › All reports on a site with no report and no type turned on: an administrator starts in Report types. */
    public static function empty_list() {
        if ( 'cleanup_report' !== sanitize_key( wp_unslash( $_GET['post_type'] ?? '' ) ) || self::addable() || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing
            return;
        }
        $counts = (array) wp_count_posts( 'cleanup_report' );
        unset( $counts['auto-draft'] );
        if ( ! array_sum( $counts ) ) {
            wp_safe_redirect( Cleanup_Report_Type_Builder::url() );
            exit;
        }
    }

    /** Reports list with no type turned on, and the edit screen of a report whose type no longer exists. */
    public static function notices() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'cleanup_report' !== $screen->post_type ) {
            return;
        }
        if ( 'edit' === $screen->base && ! self::addable() ) {
            $where = current_user_can( 'manage_options' )
                ? sprintf( '<a href="%s">%s</a>', esc_url( Cleanup_Report_Type_Builder::url() ), esc_html__( 'Turn one on in Report types.', 'cleanup-report-generator' ) )
                : esc_html__( 'An administrator can turn one on in Reports › Report types.', 'cleanup-report-generator' );
            echo '<div class="notice notice-info"><p>' . esc_html__( 'No report type is turned on, so new reports can\'t be added.', 'cleanup-report-generator' ) . ' ' . $where . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $where is escaped above
            return;
        }
        global $post;
        if ( 'post' !== $screen->base || ! $post || 'cleanup_report' !== $post->post_type || self::exists( self::of( $post->ID ) ) ) {
            return;
        }
        $message = self::printable( $post->ID )
            /* translators: %s: the key the report's type was saved under, e.g. site_migration */
            ? __( 'This report\'s type, "%s", no longer exists. The report keeps its values and opens and prints with the fields it was last saved with.', 'cleanup-report-generator' )
            /* translators: %s: the key the report's type was saved under, e.g. site_migration */
            : __( 'This report\'s type, "%s", no longer exists, so only its client and website are shown, and it can\'t be previewed or printed. Its other values are kept: they come back if the type is added again.', 'cleanup-report-generator' );
        echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( $message, self::of( $post->ID ) ) ) . '</p></div>';
    }

    /**
     * A new report becomes a report on its first save. If its type was turned off since the form was opened, refuse:
     * no new report of a type that's off (its existing reports are saved as usual).
     */
    public static function refuse_off_type( $data, $postarr ) {
        $id = (int) ( $postarr['ID'] ?? 0 );
        if ( 'cleanup_report' !== $data['post_type'] || ! $id || in_array( $data['post_status'], [ 'auto-draft', 'trash', 'inherit' ], true ) || 'auto-draft' !== get_post_status( $id ) ) {
            return $data;
        }
        $type = (string) get_post_meta( $id, self::META, true );
        if ( '' !== $type && ! isset( self::addable()[ $type ] ) ) {
            wp_die( esc_html__( 'This report type has been turned off, so a new report of it can\'t be saved. Add the report as another type.', 'cleanup-report-generator' ), esc_html__( 'Report type turned off', 'cleanup-report-generator' ), [ 'response' => 403, 'back_link' => true ] );
        }
        return $data;
    }

    /** Keeps the right "Add … report" item highlighted on the new report screen. */
    public static function submenu_file( $file ) {
        global $pagenow;
        $type   = self::requested();
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( 'post-new.php' === $pagenow && $type && $screen && 'cleanup_report' === $screen->post_type ) {
            return 'post-new.php?post_type=cleanup_report&crg_type=' . $type;
        }
        return $file;
    }

    /** The edit screen's heading names the report type ("Edit website optimization report"), so it's never in doubt. */
    public static function name_headings() {
        global $wp_post_types;
        $post_id = absint( $_GET['post'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which heading to show
        if ( $post_id && 'cleanup_report' !== get_post_type( $post_id ) ) {
            return;
        }
        $type = $post_id ? self::of( $post_id ) : self::requested();
        if ( ! $type || ! self::exists( $type ) || ! isset( $wp_post_types['cleanup_report'] ) ) { // an unknown type keeps "Edit report"
            return;
        }
        $label = strtolower( self::get( $type )['label'] );
        /* translators: %s: report type, e.g. "website optimization" */
        $wp_post_types['cleanup_report']->labels->add_new_item = sprintf( __( 'Add %s report', 'cleanup-report-generator' ), $label );
        /* translators: %s: report type, e.g. "website optimization" */
        $wp_post_types['cleanup_report']->labels->edit_item = sprintf( __( 'Edit %s report', 'cleanup-report-generator' ), $label );
    }

    /** A new report needs a type: without one, go to the chooser first. */
    public static function require_type() {
        $type = self::requested();
        if ( 'cleanup_report' !== sanitize_key( wp_unslash( $_GET['post_type'] ?? '' ) ) || ( $type && isset( self::addable()[ $type ] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing
            return;
        }
        wp_safe_redirect( admin_url( 'edit.php?post_type=cleanup_report&page=' . self::CHOOSER ) );
        exit;
    }

    /** The new report (WordPress's auto-draft) gets the type it was added as, before any box reads it. */
    public static function store_new_type( $post ) {
        $type = self::requested();
        if ( 'auto-draft' === $post->post_status && $type && isset( self::addable()[ $type ] ) && ! metadata_exists( 'post', $post->ID, self::META ) && current_user_can( 'edit_post', $post->ID ) ) {
            update_post_meta( $post->ID, self::META, $type );
        }
    }

    /**
     * Any report saved without a type (made by code, an import or WP-CLI rather than the edit screen) gets the default
     * one, so every report has its type stored and the type filters can match on the value alone. New reports from the
     * edit screen (auto-drafts) are left to store_new_type() and save().
     */
    public static function ensure_type( $post_id, $post ) {
        if ( 'cleanup_report' === $post->post_type && ! in_array( $post->post_status, [ 'auto-draft', 'inherit' ], true ) && ! metadata_exists( 'post', $post_id, self::META ) ) {
            add_post_meta( $post_id, self::META, self::DEFAULT_TYPE, true );
        }
    }

    /**
     * On save: a report that has no type yet takes the posted one (the form's hidden field) if it can be added, else
     * the default. A saved type is never changed, even when it no longer exists.
     */
    public static function save( $post_id, $posted ) {
        $posted = sanitize_key( (string) $posted );
        if ( ! metadata_exists( 'post', $post_id, self::META ) ) {
            update_post_meta( $post_id, self::META, isset( self::addable()[ $posted ] ) ? $posted : self::DEFAULT_TYPE );
        }
    }

    public static function chooser() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You are not allowed to add reports.', 'cleanup-report-generator' ), '', [ 'response' => 403 ] );
        }
        ?>
        <div class="wrap crg-chooser">
            <h1><?php esc_html_e( 'Add report', 'cleanup-report-generator' ); ?></h1>
            <?php if ( ! self::addable() ) : ?>
                <p class="crg-lead"><?php esc_html_e( 'No report type is turned on yet. An administrator can turn one on in Reports › Report types.', 'cleanup-report-generator' ); ?></p>
            <?php else : ?>
            <p class="crg-lead"><?php esc_html_e( 'Which kind of report? It can\'t be changed once the report is started.', 'cleanup-report-generator' ); ?></p>
            <?php endif; ?>
            <div class="crg-chooser-cards">
                <?php foreach ( self::addable() as $key => $type ) : ?>
                    <a class="crg-chooser-card crg-type-card-<?php echo esc_attr( $key ); ?>" href="<?php echo esc_url( self::add_url( $key ) ); ?>">
                        <span class="crg-chooser-icon dashicons dashicons-<?php echo esc_attr( $type['icon'] ?? 'media-document' ); ?>" aria-hidden="true"></span>
                        <strong class="crg-chooser-title"><?php echo esc_html( $type['label'] ); ?></strong>
                        <span class="crg-chooser-about"><?php echo esc_html( $type['about'] ); ?></span>
                        <span class="button button-primary"><?php echo esc_html( $type['add'] ); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /** The type as a small coloured label (Reports list, edit screen); a neutral "Unknown type (key)" for an unknown one. */
    public static function badge( $type ) {
        printf( '<span class="crg-type-badge crg-type-%s">%s</span>', esc_attr( self::exists( $type ) ? $type : 'unknown' ), esc_html( self::get( $type )['label'] ) );
    }

    /** Reports list: "All types" / one type. */
    public static function list_filter( $post_type ) {
        if ( 'cleanup_report' !== $post_type ) {
            return;
        }
        $current = sanitize_key( wp_unslash( $_GET['crg_type'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter
        ?>
        <label for="crg-type-filter" class="screen-reader-text"><?php esc_html_e( 'Filter by type', 'cleanup-report-generator' ); ?></label>
        <select name="crg_type" id="crg-type-filter">
            <option value=""><?php esc_html_e( 'All types', 'cleanup-report-generator' ); ?></option>
            <?php foreach ( self::listed() as $key => $type ) : ?>
                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>><?php echo esc_html( $type['label'] ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Reports list: one view per type with its count ("Malware cleanup (12) | Website optimization (3)"), from one
     * grouped count. Types turned off with no reports aren't listed; reports of an unknown type count under All only.
     */
    public static function list_views( $views ) {
        $current = self::requested();
        foreach ( self::listed() as $key => $def ) {
            $views[ 'crg_type_' . $key ] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%s)</span></a>',
                esc_url( add_query_arg( [ 'post_type' => 'cleanup_report', 'crg_type' => $key ], admin_url( 'edit.php' ) ) ),
                $current === $key ? ' class="current" aria-current="page"' : '',
                esc_html( $def['label'] ),
                esc_html( number_format_i18n( self::count( $key ) ) )
            );
        }
        return $views;
    }

    /** Meta query clause for reports of a type (the upgrade gives every older report its type, so a value match does). */
    private static function meta_match( $type ) {
        return [ 'key' => self::META, 'value' => $type ];
    }

    public static function filter_list( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() || 'cleanup_report' !== $query->get( 'post_type' ) ) {
            return;
        }
        $type = self::requested();
        if ( ! $type ) {
            return;
        }
        $query->set( 'meta_query', [ self::meta_match( $type ) ] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin list filter only
    }
}
