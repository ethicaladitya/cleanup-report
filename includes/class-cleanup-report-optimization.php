<?php
/**
 * Website optimization reports: the work checklist (answered before and after the work), the score targets, the
 * PageSpeed scores and the standard member texts. Content follows the support team's optimization report template.
 *
 * Settings (Reports › Settings › Website optimization): the option crg_opt_checks (missing = the defaults below) and
 * crg_opt_target_mobile / crg_opt_target_desktop. A report stores its answers as snapshots (crg_opt_pre_checks,
 * crg_opt_post_checks) that keep each test's wording, and the targets it was saved with (crg_opt_targets), so it reads
 * the same after the settings change.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Cleanup_Report_Optimization {

    const CHECKS_OPTION   = 'crg_opt_checks';
    const STATUSES        = [ 'yes' => 'Yes', 'no' => 'No', 'na' => 'N/A' ];
    const TARGETS         = [ 'mobile' => 80, 'desktop' => 95 ]; // average PageSpeed score aimed for (the template's)
    const PHASES          = [ 'pre' => 'crg_opt_pre_checks', 'post' => 'crg_opt_post_checks' ];
    const SHOTS           = [ 'pre' => 'crg_opt_pre_shots', 'post' => 'crg_opt_post_shots' ];
    const LIGHTHOUSE      = 'https://googlechrome.github.io/lighthouse/viewer/';
    const PLAYLIST        = 'https://www.youtube.com/watch?v=x2WSkG6-x74&list=PLV1BcivFAIdu9nxH0DU-W7LO3pFKrmbhl';

    /* ------------------------------------------------------------------ work checklist */

    /**
     * The template's tests. good: the answer that means all is well ('yes' or 'no'; '' = just information), which
     * colours the answer in the report. pre_note / post_note: the report's note when the report has none of its own.
     */
    public static function default_checks() {
        $c = static function ( $key, $label, $good, $hint = '', $pre = '', $post = '' ) {
            return [ 'key' => $key, 'label' => $label, 'good' => $good, 'hint' => $hint, 'pre_note' => $pre, 'post_note' => $post, 'enabled' => true ];
        };
        return [
            $c( 'menu', 'Is Mobile and Desktop Menu working?', 'yes' ),
            $c( 'console_errors', 'Is there any browser console error?', 'no' ),
            $c( 'responsive', 'Are there any responsive issues?', 'no', 'Switch to mobile view and test the selected pages.' ),
            $c( 'layout', 'Is there any visible layout issue?', 'no' ),
            $c( 'cls', 'Is there any visible CLS?', 'no', 'Layout shift: content moving while the page loads.' ),
            $c( 'product_variation', 'Can you select product variation?', 'yes', 'Shops only; N/A otherwise.' ),
            $c( 'add_to_cart', 'Can you add products to cart?', 'yes', 'Shops only; N/A otherwise.' ),
            $c( 'checkout', 'Are checkout functionalities working? (checkout page, information filling, shipping and payment options)', 'yes', 'No purchase is made in this step. Shops only; N/A otherwise.' ),
            $c( 'backup', 'Backup completed?', 'yes', '', 'Under the Hub › Click the site › Hosting › Backups with the comment "pre-optimization backup".', 'Under the Hub › Click the site › Hosting › Backups with the comment "post-optimization backup".' ),
            $c( 'cloudflare', 'Is the site using Cloudflare?', '' ),
            $c( 'other_issues', 'Any other issues noticed?', 'no' ),
            $c( 'initial_response', 'Any functionality highlighted in the initial response?', '', 'Something the member asked us to check in their request.' ),
        ];
    }

    public static function check_items() {
        $items = get_option( self::CHECKS_OPTION, null );
        return is_array( $items ) ? $items : self::default_checks();
    }

    public static function sanitize_checks( $input ) {
        return Cleanup_Report_Checklists::sanitize_items( $input, [
            'label'     => 'sanitize_text_field',
            'hint'      => 'sanitize_text_field',
            'pre_note'  => 'sanitize_textarea_field',
            'post_note' => 'sanitize_textarea_field',
        ], static function ( $raw ) {
            return [ 'good' => self::good( $raw['good'] ?? '' ) ];
        } );
    }

    private static function good( $value ) {
        return in_array( $value, [ 'yes', 'no' ], true ) ? $value : '';
    }

    /** A report's answers for one phase ('pre' or 'post'), in order: key => [ key, label, good, pre_note, post_note, status, note ]. */
    public static function report_checks( $post_id, $phase ) {
        $saved = get_post_meta( $post_id, self::PHASES[ $phase ] ?? '', true );
        return is_array( $saved ) ? $saved : [];
    }

    /**
     * Saves one phase's answers from the edit form. Wording comes from settings (or, for a test no longer in settings,
     * from the report's own snapshot), never from the request.
     *
     * @param array $posted $_POST['crg_opt_pre_checks'] or […post…] (unslashed, sanitised): key => [ status, note ]
     */
    public static function save_checks( $post_id, $phase, array $posted ) {
        $defs = Cleanup_Report_Checklists::keyed( self::check_items() );
        $old  = self::report_checks( $post_id, $phase );
        $rows = [];
        foreach ( $posted as $key => $answer ) {
            $key    = sanitize_key( $key );
            $status = sanitize_key( $answer['status'] ?? '' );
            $def    = $defs[ $key ] ?? $old[ $key ] ?? null;
            if ( $def && isset( self::STATUSES[ $status ] ) ) {
                $rows[ $key ] = [
                    'key'       => $key,
                    'label'     => $def['label'],
                    'good'      => self::good( $def['good'] ?? '' ),
                    'pre_note'  => $def['pre_note'] ?? '',
                    'post_note' => $def['post_note'] ?? '',
                    'status'    => $status,
                    'note'      => sanitize_text_field( $answer['note'] ?? '' ),
                ];
            }
        }
        update_post_meta( $post_id, self::PHASES[ $phase ], wp_slash( $rows ) );
    }

    /**
     * Answers as the report shows them: label, answer (Yes / No / N/A), tone (ok, bad, info, na) and note. A Yes or
     * No is ok when it's the test's good answer, bad when it isn't, and info for tests without one.
     */
    public static function checks_for_output( array $rows, $phase, $site_url ) {
        $out = [];
        foreach ( $rows as $row ) {
            $status = $row['status'];
            $good   = $row['good'] ?? '';
            $tone   = 'na' === $status ? 'na' : ( '' === $good ? 'info' : ( $good === $status ? 'ok' : 'bad' ) );
            $note   = '' !== (string) $row['note'] ? $row['note'] : (string) ( $row[ $phase . '_note' ] ?? '' );
            $out[]  = [
                'label'  => $row['label'],
                'answer' => self::STATUSES[ $status ] ?? $status,
                'tone'   => $tone,
                'note'   => Cleanup_Report_Checklists::fill( $note, $site_url ),
            ];
        }
        return $out;
    }

    /* ------------------------------------------------------------------ targets and scores */

    /** The targets in Settings: [ mobile, desktop ]. */
    public static function targets() {
        $out = [];
        foreach ( self::TARGETS as $device => $default ) {
            $out[ $device ] = self::score( get_option( 'crg_opt_target_' . $device, $default ) ) ?? $default;
        }
        return $out;
    }

    /** The targets a report was saved with (reports not saved since use today's). */
    public static function report_targets( $post_id ) {
        $saved = get_post_meta( $post_id, 'crg_opt_targets', true );
        return is_array( $saved ) ? array_map( 'intval', $saved + self::targets() ) : self::targets();
    }

    /** A score typed in: a whole number 0–100, or null when it isn't one. */
    public static function score( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value || ! is_numeric( $value ) ) {
            return null;
        }
        return max( 0, min( 100, (int) round( (float) $value ) ) );
    }

    /** For the settings: a target score 0–100. */
    public static function sanitize_target( $value ) {
        return self::score( $value ) ?? 0;
    }

    /** The score columns, in the order of a text line. */
    const COLUMNS = [ 'mobile_before', 'desktop_before', 'mobile_after', 'desktop_after' ];

    /**
     * Pages and scores, one per line: "URL | mobile before | desktop before | mobile after | desktop after" →
     * [ [ url, mobile_before, … ], … ] with null for a score not entered. Lines without a URL are dropped.
     */
    public static function parse_scores( $text ) {
        $rows = [];
        foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
            $cols = array_map( 'trim', explode( '|', $line ) );
            if ( '' === $cols[0] ) {
                continue;
            }
            $row = [ 'url' => $cols[0] ];
            foreach ( self::COLUMNS as $i => $col ) {
                $row[ $col ] = self::score( $cols[ $i + 1 ] ?? '' );
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Averages as the template works them out: the sum of the pages' scores divided by the number of pages scored.
     * column => [ sum, count, avg ] (avg null when no page has a score).
     */
    public static function averages( array $rows ) {
        $out = [];
        foreach ( self::COLUMNS as $col ) {
            $scores      = array_filter( wp_list_pluck( $rows, $col ), 'is_int' );
            $out[ $col ] = [ 'sum' => array_sum( $scores ), 'count' => count( $scores ), 'avg' => $scores ? array_sum( $scores ) / count( $scores ) : null ];
        }
        return $out;
    }

    /** 67.25, 85, 95.75: up to two decimals, as in the template. */
    public static function format_avg( $avg ) {
        return null === $avg ? '—' : rtrim( rtrim( number_format( (float) $avg, 2, '.', '' ), '0' ), '.' );
    }

    /** Was the target reached after the work? true / false, or null without after-scores. */
    public static function reached( array $averages, array $targets, $device ) {
        $avg = $averages[ $device . '_after' ]['avg'];
        return null === $avg ? null : $avg >= ( $targets[ $device ] ?? self::TARGETS[ $device ] );
    }

    /** Typed code (the "Code added" field): kept as typed, since it's shown escaped; only invalid UTF-8 and NUL bytes go. */
    public static function sanitize_code( $code ) {
        $code = str_replace( [ "\r\n", "\r", "\0" ], [ "\n", "\n", '' ], (string) $code );
        return wp_check_invalid_utf8( rtrim( $code ), true );
    }

    /* ------------------------------------------------------------------ member texts */

    /** Standard texts used when a report leaves them empty (also shown under the editors). From the team's template. */
    public static function default_texts() {
        return [
            'info' => '<p>WPMU DEV services will focus on increasing your website score. For that, we use different pages and generate the average score using a simple formula:</p>'
                . '<ol><li>Run the test multiple times;</li><li>Remove the top and lowest score;</li><li>Sum the average result for the tested pages.</li></ol>'
                . '<p>The above is described in our <a href="' . esc_url( self::PLAYLIST ) . '">optimization playlist</a>. It gives you a more accurate result, since the performance score only takes into account the page tested, not the full site.</p>'
                . '<p>To maintain a good score on your website, it is important to:</p>'
                . '<ul><li>Avoid duplicate plugins for the same task;</li><li>Keep your pages light, with the relevant information only: it reduces the number of elements the browser needs to handle;</li><li>Wait for the cache to be generated before running the tests: a lower score is expected if the cache is not being hit;</li><li>Avoid animations above the fold (what is visible when the page loads).</li></ul>',
            'faq'  => '<p><strong>Why is the mobile score lower?</strong></p>'
                . '<p>Mobiles have fewer hardware and software resources, which reflects directly on the browser\'s performance. Any metric used in performance tests affects the score more drastically.</p>'
                . '<p><strong>Why target 80 for mobile?</strong></p>'
                . '<p>Google considers anything 90+ a good score, but it also explains that a good score doesn\'t mean a good user experience: Google uses the 75th percentile as the goal for a page to work well for the majority of users.</p>'
                . '<p><strong>Why is my score different from the ones in this report?</strong></p>'
                . '<p>There are many reasons for a score to change, but the most common are:</p>'
                . '<ul><li><strong>Location:</strong> Google uses your location to decide where the page is tested from. There are 3 networks (Asia, North America and Europe); it doesn\'t specify the countries. We use a connection to the location closest to your server, so testing from a different location can give a different result. Cloudflare or another CDN can help make results more stable.</li>'
                . '<li><strong>Big changes in the website:</strong> the optimization is done on the website as it is. Changing the layout or the settings we defined can affect the score.</li></ul>'
                . '<p><strong>Core Web Vitals say "Failed"</strong></p>'
                . '<p>Core Web Vitals can say "Failed" even after optimization and a good PageSpeed Insights (PSI) score. PSI simulates the user experience, while Core Web Vitals rely on real user data: it can take 28+ days for them to update, and a good PSI score doesn\'t guarantee passing Core Web Vitals because real-world conditions differ.</p>'
                . '<p>Two key factors are server location (is it close to your audience?) and traffic (is there enough data for Google to assess?). If these are addressed and Core Web Vitals still fail, further investigation is needed: a deeper look at real user performance data and field data issues, and possibly structural changes to the website.</p>',
        ];
    }
}
