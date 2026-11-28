<?php
/**
 * A test-only report type, "Website migration", defined with the handover_report_types filter and nothing else: it
 * uses every generic field type and the generic template. Loaded by the "generic" fixture and by render.php, so the
 * type exists while the report is seeded and while it's rendered. Never part of the plugin.
 */
defined( 'WP_CLI' ) || exit;

add_filter( 'handover_report_types', static function ( $types ) {
	$types['site_migration'] = [
		'label'    => 'Website migration',
		'about'    => 'Test type for the layout check.',
		'sections' => [
			'site' => Cleanup_Report_Types::site_section(),
			'move' => [
				'title'  => 'Migration',
				'fields' => [
					[ 'key' => 'crg_mig_from', 'label' => 'Moved from' ],
					[ 'key' => 'crg_mig_date', 'type' => 'date', 'label' => 'Moved on' ],
					[ 'key' => 'crg_mig_downtime', 'type' => 'number', 'label' => 'Downtime (minutes)' ],
					[ 'key' => 'crg_mig_dns', 'type' => 'select', 'label' => 'DNS', 'answers' => [ 'us' => 'Changed by us', 'member' => 'Changed by the member' ], 'note' => true ],
					[ 'key' => 'crg_mig_checks', 'type' => 'questions', 'label' => 'Access', 'questions' => [
						'mig_ssl'   => [ 'SSL certificate issued?', 'SSL certificate', [ 'yes' => 'Yes', 'no' => 'No' ] ],
						'mig_email' => [ 'Email moved too?', 'Email', [ 'yes' => 'Yes', 'no' => 'No', 'na' => 'Not hosted with us' ] ],
					] ],
					[ 'key' => 'crg_mig_summary', 'type' => 'editor', 'label' => 'What we did' ],
				],
			],
			'checks' => [
				'title'  => 'After the move',
				'fields' => [
					[ 'key' => 'crg_mig_tests', 'type' => 'checklist', 'label' => 'Tests', 'items' => static function () {
						return [
							[ 'key' => 'pages', 'label' => 'Do all pages load?', 'good' => 'yes', 'enabled' => true ],
							[ 'key' => 'forms', 'label' => 'Do the forms send email?', 'good' => 'yes', 'enabled' => true ],
							[ 'key' => 'errors', 'label' => 'Any PHP errors in the log?', 'good' => 'no', 'enabled' => true ],
						];
					} ],
					[ 'key' => 'crg_mig_redirects', 'type' => 'table', 'label' => 'Redirects added', 'columns' => [ 'from' => [ 'From', '/old-page/' ], 'to' => [ 'To', '/new-page/' ], 'why' => [ 'Why', '' ] ] ],
					[ 'key' => 'crg_mig_shots', 'type' => 'screenshots', 'label' => 'Screenshots' ],
					[ 'key' => 'crg_mig_config', 'type' => 'code', 'label' => 'Configuration changed', 'path_key' => 'crg_mig_config_path' ],
					[ 'key' => 'crg_mig_notes', 'type' => 'textarea', 'label' => 'Notes for the member' ],
					[ 'key' => 'crg_mig_next', 'type' => 'note', 'label' => 'What happens next', 'standard' => static function () { return '<p>Keep the old hosting for a week, then cancel it.</p>'; } ],
				],
			],
		],
	];
	return $types;
} );
