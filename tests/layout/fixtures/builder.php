<?php
/**
 * Fixture "builder": a report type made in Reports › Report types (stored in crg_custom_types, as the editor saves
 * it), and an approved report of it. After the report is approved the type is edited (a field removed, a section
 * renamed), so the PDF shows the report as approved: from its snapshot.
 *
 * wp eval-file builder.php <fixture-image-dir>
 */
defined( 'WP_CLI' ) || exit;
require_once __DIR__ . '/_lib.php';

$key = 'layout_test_onboarding';
// As posted by the type editor: text lines for answers, questions, columns and items.
$posted = [
	'label'    => 'Website onboarding',
	'about'    => 'Test type made with the report type builder.',
	'enabled'  => '1',
	'sections' => [
		[ 'title' => 'Access', 'fields' => [
			[ 'type' => 'text', 'label' => 'Previous agency' ],
			[ 'type' => 'date', 'label' => 'Access received on' ],
			[ 'type' => 'select', 'label' => 'Hosting', 'answers' => "ours = Our hosting\nother = Another host", 'note' => '1' ],
			[ 'type' => 'questions', 'label' => 'Access', 'questions' => "admin = Administrator account created? | Administrator account\nsftp = SFTP access? | SFTP | yes = Yes; no = No; na = Not needed" ],
			[ 'type' => 'text', 'label' => 'Removed after approval' ],
		] ],
		[ 'title' => 'First review', 'fields' => [
			[ 'type' => 'checklist', 'label' => 'Health checks', 'items' => "Backups running | yes | Check the last backup's date\nPHP errors in the log | no\nCaching on | yes" ],
			[ 'type' => 'table', 'label' => 'Plugins to replace', 'columns' => "Plugin | Name\nReplace with\nWhy" ],
			[ 'type' => 'editor', 'label' => 'Summary' ],
			[ 'type' => 'note', 'label' => 'What happens next', 'standard' => '<p>We keep the site updated every week and send a short report each month.</p>' ],
		] ],
	],
];
$def   = Cleanup_Report_Type_Builder::sanitize_definition( $posted, $key );
$types = get_option( Cleanup_Report_Type_Builder::OPTION, [] );
// Start from a new type: a type that was edited keeps its retired keys and never gives them out again.
unset( $types[ $key ] );
update_option( Cleanup_Report_Type_Builder::OPTION, $types, false );
$types[ $key ] = $def;
update_option( Cleanup_Report_Type_Builder::OPTION, $types, false );
Cleanup_Report_Types::flush();

$f  = static function ( $id ) use ( $key ) { return Cleanup_Report_Type_Builder::meta_key( $key, $id ); };
$id = crg_fixture_report( 'Layout test – builder type' );
crg_fixture_save( $id, [
	'crg_report_type'                  => $key,
	'crg_client_name'                  => 'Northwind Bakery',
	'crg_site_url'                     => 'https://northwind-bakery.example.org',
	'crg_report_date'                  => '2026-11-27',
	$f( 'previous_agency' )            => 'Pixel & Co',
	$f( 'access_received_on' )         => '2026-11-25',
	$f( 'hosting' )                    => 'other',
	$f( 'hosting' ) . '_note'          => 'Moving to our hosting in December',
	'crg_t_' . $key . '_access_admin'  => 'yes',
	'crg_t_' . $key . '_access_sftp'   => 'na',
	$f( 'removed_after_approval' )     => 'Shown because the report was approved before this field was removed',
	$f( 'health_checks' )              => [
		'backups_running'       => [ 'key' => 'backups_running', 'label' => 'Backups running', 'good' => 'yes', 'hint' => '', 'status' => 'no', 'note' => 'No backup for 40 days: daily backups set up' ],
		'php_errors_in_the_log' => [ 'key' => 'php_errors_in_the_log', 'label' => 'PHP errors in the log', 'good' => 'no', 'hint' => '', 'status' => 'no', 'note' => '' ],
		'caching_on'            => [ 'key' => 'caching_on', 'label' => 'Caching on', 'good' => 'yes', 'hint' => '', 'status' => 'yes', 'note' => '' ],
	],
	$f( 'plugins_to_replace' )         => "Old Slider | Gutenberg cover block | Unmaintained since 2021\nContact Form X | WPForms Lite | Security issue",
	$f( 'summary' )                    => '<p>We took over the website from the previous agency, checked its health and set up backups.</p><ul><li>Backups now run every day</li><li>Two plugins will be replaced</li></ul>',
	'crg_review_status'                => 'approved',
] );
Cleanup_Report_Type_Builder::store_snapshot( $id );

// The type changes after approval: a field removed, a section renamed. The approved PDF must not change.
$def['sections'][0]['fields'] = array_slice( $def['sections'][0]['fields'], 0, 4 );
$def['sections'][1]['title']  = 'Renamed after approval';
$types[ $key ] = $def;
update_option( Cleanup_Report_Type_Builder::OPTION, $types, false );
