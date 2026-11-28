<?php
/**
 * Fixture "optimization-minimal": a Website optimization report with nothing filled in. Catches empty tables,
 * averages with no scores ("—") and sections with nothing under them.
 *
 * wp eval-file optimization-minimal.php <fixture-image-dir>
 */
defined( 'WP_CLI' ) || exit;
require_once __DIR__ . '/_lib.php';

crg_fixture_save( crg_fixture_report( 'Layout test – optimization minimal' ), [ 'crg_report_type' => 'site_optimization', 'crg_review_status' => 'approved' ] );
