<?php
/**
 * Report types. Every report is a `cleanup_report` post; its type is the meta crg_report_type. Reports saved before
 * types existed have no such meta and are Malware cleanup reports.
 *
 * The type is picked when a report is added (Reports › Add cleanup report / Add optimization report, or the chooser
 * behind the list's "Add report" button) and doesn't change afterwards. It decides the edit screen's sections, the
 * fields that are saved, the report template and the PDF's title and file name. Branding, review, retention and the
 * plugin list are shared by all types.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Cleanup_Report_Types {

    const META         = 'crg_report_type';
    const DEFAULT_TYPE = 'malware_cleanup';
    const CHOOSER      = 'crg-add-report';

    /**
     * key => label (Reports list, chooser), add (menu), eyebrow (cover), title (running header), file (PDF file name
     * part), suffix (title suggestion), prepared_by (cover card), about (chooser), template (templates/types/<x>.php).
     */
    const TYPES = [
        'malware_cleanup'   => [
            'label'       => 'Malware cleanup',
            'add'         => 'Add cleanup report',
            'eyebrow'     => 'Malware cleanup report',
            'title'       => 'MALWARE CLEANUP REPORT',
            'file'        => 'cleanup-report',
            'suffix'      => 'malware cleanup',
            'prepared_by' => 'security team',
            'about'       => 'What was found and removed, how the site was verified, Defender settings and the member\'s next steps.',
            'template'    => 'malware-cleanup',
        ],
        'site_optimization' => [
            'label'       => 'Website optimization',
            'add'         => 'Add optimization report',
            'eyebrow'     => 'Website optimization report',
            'title'       => 'WEBSITE OPTIMIZATION REPORT',
            'file'        => 'optimization-report',
            'suffix'      => 'website optimization',
            'prepared_by' => 'experts team',
            'about'       => 'PageSpeed scores before and after, the pre- and post-work checklists, the optimization steps and the result against the targets.',
            'template'    => 'site-optimization',
        ],
    ];

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ], 9 ); // before Settings (priority 10), so the Add items come first
        add_action( 'load-post-new.php', [ __CLASS__, 'require_type' ] );
        add_action( 'add_meta_boxes_cleanup_report', [ __CLASS__, 'store_new_type' ], 1 );
        add_filter( 'submenu_file', [ __CLASS__, 'submenu_file' ] );
        add_action( 'restrict_manage_posts', [ __CLASS__, 'list_filter' ] );
        add_action( 'pre_get_posts', [ __CLASS__, 'filter_list' ] );
    }

    /** The report's type: its saved type, or Malware cleanup (every report saved before types existed). */
    public static function of( $post_id ) {
        $type = (string) get_post_meta( $post_id, self::META, true );
        return isset( self::TYPES[ $type ] ) ? $type : self::DEFAULT_TYPE;
    }

    /** One type's settings (the default type's for an unknown key). */
    public static function get( $type ) {
        return self::TYPES[ $type ] ?? self::TYPES[ self::DEFAULT_TYPE ];
    }

    public static function exists( $type ) {
        return isset( self::TYPES[ (string) $type ] );
    }

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
     * Reports › Add cleanup report / Add optimization report replace WordPress's "Add report". The chooser (two cards)
     * is a hidden page: the list's "Add report" button and the admin bar's New › Report lead to it.
     */
    public static function menu() {
        $parent = 'edit.php?post_type=cleanup_report';
        remove_submenu_page( $parent, 'post-new.php?post_type=cleanup_report' );
        foreach ( self::TYPES as $key => $type ) {
            add_submenu_page( $parent, $type['add'], $type['add'], 'edit_posts', 'post-new.php?post_type=cleanup_report&crg_type=' . $key );
        }
        add_submenu_page( $parent, 'Add report', 'Add report', 'edit_posts', self::CHOOSER, [ __CLASS__, 'chooser' ] );
        remove_submenu_page( $parent, self::CHOOSER ); // reachable by URL, not listed
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

    /** A new report needs a type: without one, go to the chooser first. */
    public static function require_type() {
        if ( 'cleanup_report' !== sanitize_key( wp_unslash( $_GET['post_type'] ?? '' ) ) || self::requested() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing
            return;
        }
        wp_safe_redirect( admin_url( 'edit.php?post_type=cleanup_report&page=' . self::CHOOSER ) );
        exit;
    }

    /** The new report (WordPress's auto-draft) gets the type it was added as, before any box reads it. */
    public static function store_new_type( $post ) {
        $type = self::requested();
        if ( 'auto-draft' === $post->post_status && $type && ! metadata_exists( 'post', $post->ID, self::META ) ) {
            update_post_meta( $post->ID, self::META, $type );
        }
    }

    /** On save: a report that has no type yet takes the posted one (the form's hidden field); a type never changes. */
    public static function save( $post_id, $posted ) {
        $posted = sanitize_key( (string) $posted );
        if ( ! metadata_exists( 'post', $post_id, self::META ) ) {
            update_post_meta( $post_id, self::META, self::exists( $posted ) ? $posted : self::DEFAULT_TYPE );
        }
    }

    public static function chooser() {
        ?>
        <div class="wrap crg-chooser">
            <h1>Add report</h1>
            <p class="crg-lead">Which kind of report? It can't be changed once the report is started.</p>
            <div class="crg-chooser-cards">
                <?php foreach ( self::TYPES as $key => $type ) : ?>
                    <a class="crg-chooser-card" href="<?php echo esc_url( self::add_url( $key ) ); ?>">
                        <?php self::badge( $key ); ?>
                        <strong><?php echo esc_html( $type['add'] ); ?></strong>
                        <span><?php echo esc_html( $type['about'] ); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /** The type as a small coloured label (Reports list, edit screen, chooser). */
    public static function badge( $type ) {
        printf( '<span class="crg-type-badge crg-type-%s">%s</span>', esc_attr( $type ), esc_html( self::get( $type )['label'] ) );
    }

    /** Reports list: "All types" / one type. */
    public static function list_filter( $post_type ) {
        if ( 'cleanup_report' !== $post_type ) {
            return;
        }
        $current = sanitize_key( wp_unslash( $_GET['crg_type'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter
        ?>
        <label for="crg-type-filter" class="screen-reader-text">Filter by type</label>
        <select name="crg_type" id="crg-type-filter">
            <option value="">All types</option>
            <?php foreach ( self::TYPES as $key => $type ) : ?>
                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>><?php echo esc_html( $type['label'] ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public static function filter_list( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() || 'cleanup_report' !== $query->get( 'post_type' ) ) {
            return;
        }
        $type = self::requested();
        if ( ! $type ) {
            return;
        }
        $match = [ 'key' => self::META, 'value' => $type ];
        if ( self::DEFAULT_TYPE === $type ) {
            $match = [ 'relation' => 'OR', $match, [ 'key' => self::META, 'compare' => 'NOT EXISTS' ] ]; // in case the upgrade hasn't run yet
        }
        $query->set( 'meta_query', [ $match ] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin list filter only
    }
}
