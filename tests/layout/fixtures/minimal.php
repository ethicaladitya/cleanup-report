<?php
/**
 * Fixture "minimal": a report with nothing filled in. Catches empty headings, "0 of 0" style figures and
 * intros with nothing under them.
 *
 * wp eval-file minimal.php <fixture-image-dir>
 */
defined( 'WP_CLI' ) || exit;
require_once __DIR__ . '/_lib.php';

crg_fixture_save( crg_fixture_report( 'Layout test – minimal' ), [ 'crg_review_status' => 'approved' ] ); // the final report; unapproved ones carry a "NOT FINAL" mark
