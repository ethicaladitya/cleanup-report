<?php
/**
 * Fixture "generic": a report of a type that exists only as a definition (see _types.php), drawn by the generic
 * template. Every generic field type is filled in, with a long table and long code.
 *
 * wp eval-file generic.php <fixture-image-dir>
 */
defined( 'WP_CLI' ) || exit;
require_once __DIR__ . '/_lib.php';
require_once __DIR__ . '/_types.php';

$id  = crg_fixture_report( 'Layout test – generic type' );
$img = $args[0] ?? __DIR__;

$redirects = [];
for ( $i = 1; $i <= 14; $i++ ) {
	$redirects[] = "/old-section/page-$i/ | /new-section/page-$i/ | Renamed section";
}
$redirects[] = '/a-very-long-old-address-with-no-spaces-at-all-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/ | /short/ | Long URL test';

crg_fixture_save( $id, [
	'crg_report_type'     => 'site_migration',
	'crg_client_name'     => 'Riverside Dental',
	'crg_site_url'        => 'https://riversidedental.example.net',
	'crg_report_date'     => '2026-11-20',
	'crg_mig_from'        => 'Old shared host',
	'crg_mig_date'        => '2026-11-19',
	'crg_mig_downtime'    => '4',
	'crg_mig_dns'         => 'member',
	'crg_mig_dns_note'    => 'They changed the A record on 19 November',
	'crg_mig_ssl'         => 'yes',
	'crg_mig_email'       => 'na',
	'crg_mig_email_note'  => 'Email stays with Google Workspace',
	'crg_mig_summary'     => '<p>We copied the files and database, updated the URLs and tested every page.</p><ul><li>PHP 8.3</li><li>Object cache on</li></ul>',
	'crg_mig_tests'       => [
		'pages'  => [ 'key' => 'pages', 'label' => 'Do all pages load?', 'good' => 'yes', 'status' => 'yes', 'note' => '' ],
		'forms'  => [ 'key' => 'forms', 'label' => 'Do the forms send email?', 'good' => 'yes', 'status' => 'no', 'note' => 'SMTP needs the member\'s password' ],
		'errors' => [ 'key' => 'errors', 'label' => 'Any PHP errors in the log?', 'good' => 'no', 'status' => 'na', 'note' => '' ],
	],
	'crg_mig_redirects'   => implode( "\n", $redirects ),
	'crg_mig_shots'       => [ [ 'url' => crg_fixture_image( 'optimization-cls-before', $img ), 'title' => 'Home page after the move', 'type' => 'other' ] ],
	'crg_mig_config_path' => 'wp-config.php',
	'crg_mig_config'      => wp_slash( "define( 'WP_CACHE', true );\ndefine( 'WP_REDIS_HOST', '127.0.0.1' );\n// " . str_repeat( 'x', 160 ) ),
	'crg_mig_notes'       => "Old hosting can be cancelled after 27 November.\nKeep the backup we sent.",
	'crg_review_status'   => 'approved',
] );
