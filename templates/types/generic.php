<?php
/**
 * Generic report: any report type without a template of its own (Cleanup_Report_Types::definitions(), 'template'
 * left out). Its sections come from Cleanup_Report_Render::generic_sections(), in the definition's order.
 *
 * Sets $sections (key => title; drives numbering and the contents list) and $cover for templates/parts/cover.php.
 *
 * Part of templates/report-pdf.php: included from inside Cleanup_Report_PDF::render_html(), so every variable here is
 * local to that method, not global.
 */
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- method scope, not global.
if ( ! defined( 'ABSPATH' ) ) { exit; }

$generic  = $data['generic'];
$sections = [];
foreach ( $generic as $i => $section ) {
    $sections[ 's' . $i ] = $section['title'];
}
$num = static function ( $key ) use ( $sections ) { return sprintf( '%02d', array_search( $key, array_keys( $sections ), true ) + 1 ); };
$h2  = static function ( $key ) use ( $sections, $num ) {
    return '<h2><span class="n">' . $num( $key ) . '</span> ' . esc_html( $sections[ $key ] ) . '</h2>';
};

// Cover figures: what the report holds.
$checked = 0;
$ok      = 0;
$shots_n = 0;
foreach ( $generic as $section ) {
    foreach ( $section['blocks'] as $block ) {
        if ( 'checks' === $block['kind'] ) {
            $checked += count( $block['rows'] );
            $ok      += count( array_filter( $block['rows'], static function ( $r ) { return 'ok' === $r['tone']; } ) );
        } elseif ( 'shots' === $block['kind'] ) {
            $shots_n += count( $block['shots'] );
        }
    }
}
$cover = [
    'eyebrow'     => $type_info['eyebrow'],
    'chip'        => [ 'ok', __( 'Completed', 'cleanup-report-generator' ) ],
    'stats'       => [
        [ (string) count( $sections ), __( 'Sections', 'cleanup-report-generator' ) ],
        [ $checked ? (int) $ok . '<small> / ' . (int) $checked . '</small>' : '—', __( 'Checks passed', 'cleanup-report-generator' ) ],
        [ (string) (int) $shots_n, __( 'Screenshots', 'cleanup-report-generator' ) ],
    ],
    'prepared_by' => $type_info['prepared_by'],
    /* translators: %s: report type, e.g. "Website migration" */
    'foot'        => sprintf( __( 'Describes the website at the time of the %s.', 'cleanup-report-generator' ), strtolower( $type_info['label'] ) ),
];
