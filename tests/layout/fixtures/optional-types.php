<?php
/**
 * Fixture "optional-types": ready-made types turned off, and a report whose type no longer exists.
 *
 * Checks first (stops the run if one fails; crg_types_off is put back as it was):
 *  - a ready-made type that's off but has reports: no "Add" item, still in the list's views and filter, its reports
 *    still print as that type;
 *  - a ready-made type (here one added by code) that's off with no reports: shown nowhere;
 *  - a report of an unknown type: listed as "Unknown type (…)", never drawn as a Malware cleanup report, and not
 *    printable until it has the definition it was saved with.
 * Then renders that report from its saved definition (a custom type deleted after the report was written): the
 * generic layout, its own fields, nothing of a Malware cleanup report.
 *
 * wp eval-file optional-types.php <fixture-image-dir>
 */
defined( 'WP_CLI' ) || exit;
require_once __DIR__ . '/_lib.php';

$check = static function ( $cond, $what ) {
	if ( ! $cond ) {
		WP_CLI::error( 'optional-types: ' . $what );
	}
};
$off_before = get_option( Cleanup_Report_Types::OFF_OPTION, null );
$restore    = static function () use ( $off_before ) {
	if ( null === $off_before ) {
		delete_option( Cleanup_Report_Types::OFF_OPTION );
	} else {
		update_option( Cleanup_Report_Types::OFF_OPTION, $off_before, false );
	}
	Cleanup_Report_Types::flush();
};

// A type added by code, with no reports: counts as ready-made.
add_filter( 'cleanup_report_types', static function ( $types ) {
	$types['layout_test_code'] = [ 'label' => 'Layout test code type', 'sections' => [ 'site' => Cleanup_Report_Types::site_section() ] ];
	return $types;
} );

$sample = get_posts( [ 'post_type' => 'cleanup_report', 'title' => 'Sample – website optimization report', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ] );
update_option( Cleanup_Report_Types::OFF_OPTION, [ 'site_optimization', 'layout_test_code' ], false );
Cleanup_Report_Types::flush();
$addable = Cleanup_Report_Types::addable();
$listed  = Cleanup_Report_Types::listed();
$views   = Cleanup_Report_Types::list_views( [] );
$check( ! isset( $addable['site_optimization'] ) && ! isset( $addable['layout_test_code'] ), 'types turned off are still addable' );
if ( Cleanup_Report_Types::count( 'site_optimization' ) ) {
	$check( isset( $listed['site_optimization'], $views['crg_type_site_optimization'] ), 'off with reports: dropped from the list views' );
}
$check( ! isset( $listed['layout_test_code'] ) && ! isset( $views['crg_type_layout_test_code'] ), 'off without reports: still listed' );
if ( $sample ) {
	$check( 'site-optimization' === Cleanup_Report_Render::get_data( $sample[0] )['type_info']['template'], 'off with reports: its report no longer prints as its type' );
}
$restore();

// A report of a type that no longer exists: first without the definition it was saved with, then with it.
$key  = 'layout_test_retired';
$id   = crg_fixture_report( 'Layout test – unknown type' );
$meta = [
	'crg_report_type'                       => $key,
	'crg_client_name'                       => 'Fabrikam Garden Supplies',
	'crg_site_url'                          => 'https://fabrikam-garden.example.org',
	'crg_report_date'                       => '2026-11-28',
	'crg_review_status'                     => 'approved',
	'crg_infection_summary'                 => '<p>A Malware cleanup field: never shown in this report.</p>',
	'crg_t_' . $key . '_visit_date'         => '2026-11-20',
	'crg_t_' . $key . '_findings'           => '<p>The client asked for a yearly check of their shop. The type it was written with was deleted afterwards; the report keeps printing with the fields it was saved with.</p><ul><li>Theme and plugins up to date</li><li>Backups restored on a test copy</li></ul>',
	'crg_t_' . $key . '_follow_up'          => "Renew the SSL certificate | January | Hosting\nReview user accounts | March | Client",
];
foreach ( $meta as $k => $v ) {
	update_post_meta( $id, $k, $v );
}
Cleanup_Report_Types::flush();
$badge = ( static function () use ( $key ) { ob_start(); Cleanup_Report_Types::badge( $key ); return ob_get_clean(); } )();
$check( $key === Cleanup_Report_Types::of( $id ) && false !== strpos( $badge, 'Unknown type (' . $key . ')' ), 'unknown type: not listed as "Unknown type"' );
$check( ! Cleanup_Report_Types::printable( $id ), 'unknown type without its definition: printable' );
$check( [ 'site' ] === array_keys( Cleanup_Report_Types::for_report( $id )['sections'] ), 'unknown type without its definition: edit screen shows more than the client section' );

$snapshot = Cleanup_Report_Type_Builder::sanitize_definition( [
	'label'    => 'Yearly check',
	'about'    => 'A custom type that has since been deleted.',
	'sections' => [
		[ 'title' => 'The check', 'fields' => [
			[ 'type' => 'date', 'label' => 'Visit date' ],
			[ 'type' => 'editor', 'label' => 'Findings' ],
			[ 'type' => 'table', 'label' => 'Follow up', 'columns' => "Task\nWhen\nWho" ],
		] ],
	],
], $key );
update_post_meta( $id, Cleanup_Report_Type_Builder::SNAPSHOT, wp_slash( [ '_frozen' => true ] + $snapshot ) );
$check( Cleanup_Report_Types::printable( $id ), 'unknown type with its definition: not printable' );
$data = Cleanup_Report_Render::get_data( $id );
$check( 'generic' === $data['type_info']['template'] && 'Yearly check' === $data['type_info']['label'], 'unknown type with its definition: not drawn from it' );
$html = Cleanup_Report_PDF::render_html( $data );
$check( false !== strpos( $html, 'Backups restored on a test copy' ) && false === strpos( $html, 'never shown in this report' ), 'unknown type with its definition: wrong fields drawn' );
wp_update_post( [ 'ID' => $id ] );
echo $id;
