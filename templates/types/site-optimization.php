<?php
/**
 * Website optimization report: works out what the cover, the summary page and the sections need. Sections and
 * wording follow the support team's optimization report template.
 *
 * Sets $sections (key => title, in order; drives numbering and the contents list) and $cover (eyebrow, chip, stats,
 * prepared_by, foot) for templates/parts/cover.php.
 *
 * Part of templates/report-pdf.php: included from inside Cleanup_Report_PDF::render_html(), so every variable here is
 * local to that method, not global.
 */
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- method scope, not global.
if ( ! defined( 'ABSPATH' ) ) { exit; }

$opt     = $data['opt'];
$avg     = $opt['averages'];
$targets = $opt['targets'];
$reached = $opt['reached'];
$fmt     = [ 'Cleanup_Report_Optimization', 'format_avg' ];
$devices = [ 'mobile' => 'Mobile', 'desktop' => 'Desktop' ];

// PageSpeed's own colour bands: 90–100 good, 50–89 needs improvement, 0–49 poor.
$band = static function ( $score ) {
    if ( null === $score ) {
        return '';
    }
    return $score >= 90 ? 'ok' : ( $score >= 50 ? 'rec' : 'todo' );
};
// Score change, e.g. "+28.5".
$gain = static function ( $device ) use ( $avg, $fmt ) {
    $before = $avg[ $device . '_before' ]['avg'];
    $after  = $avg[ $device . '_after' ]['avg'];
    if ( null === $before || null === $after ) {
        return '—';
    }
    $d = $after - $before;
    return ( $d > 0 ? '+' : ( $d < 0 ? '−' : '' ) ) . call_user_func( $fmt, abs( $d ) );
};

$measured     = array_filter( $reached, static function ( $r ) { return null !== $r; } );
$reached_n    = count( array_filter( $measured ) );
$missed       = array_keys( array_filter( $measured, static function ( $r ) { return false === $r; } ) );
$all_measured = count( $measured ) === count( $devices );

$pre_checks  = $opt['checks']['pre'];
$post_checks = $opt['checks']['post'];

$sections = array_filter( [
    'scores' => 'Performance scores',
    'pre'    => 'Pre-work checklist',
    'steps'  => $has( $opt['steps'] ) || '' !== trim( $opt['code'] ) ? 'Optimization steps' : null,
    'result' => 'Optimization result',
    'post'   => 'Post-work checklist',
    'info'   => 'Helpful information about the performance',
    'code'   => '' !== trim( $opt['code'] ) ? 'Code added to your site' : null,
] );
$num = static function ( $key ) use ( $sections ) { return sprintf( '%02d', array_search( $key, array_keys( $sections ), true ) + 1 ); };
$h2  = static function ( $key ) use ( $sections, $num ) {
    return '<h2><span class="n">' . $num( $key ) . '</span> ' . esc_html( $sections[ $key ] ) . '</h2>';
};

if ( ! $all_measured ) {
    $chip = [ 'warn', 'Scores after the work not entered yet' ];
} elseif ( $missed ) {
    $chip = [ 'warn', 'Optimized · ' . implode( ' and ', array_map( 'strtolower', array_intersect_key( $devices, array_flip( $missed ) ) ) ) . ' target not reached' ];
} else {
    $chip = [ 'ok', 'Optimized · targets reached' ];
}

$cover = [
    'eyebrow'     => $type_info['eyebrow'],
    'chip'        => $chip,
    'stats'       => [],
    'prepared_by' => $type_info['prepared_by'],
    'foot'        => 'Describes the website\'s performance at the time of the optimization.',
];
foreach ( $devices as $device => $label ) {
    $before            = $avg[ $device . '_before' ]['avg'];
    $cover['stats'][] = [
        esc_html( call_user_func( $fmt, $avg[ $device . '_after' ]['avg'] ) ),
        $label . ' average score' . ( null !== $before ? ', from ' . call_user_func( $fmt, $before ) : '' ),
    ];
}
$cover['stats'][] = [ (int) $reached_n . '<small> / ' . count( $devices ) . '</small>', 'Targets reached' ];
