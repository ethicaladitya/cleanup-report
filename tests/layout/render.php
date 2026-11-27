<?php
/**
 * wp eval-file render.php <report_id> <out.pdf>
 *
 * Renders a report through the same Cleanup_Report_PDF::build() path as the "Export PDF" link, and writes the section
 * headings next to it (<out.pdf>.headings, one per line) for the layout checker.
 */
defined( 'WP_CLI' ) || exit;

[ $id, $out ] = [ (int) $args[0], $args[1] ];
$data  = Cleanup_Report_Render::get_data( $id );
$start = microtime( true );
$pdf   = Cleanup_Report_PDF::build( $data );
file_put_contents( $out, $pdf->output() );

// h2 titles (without the "01" number badge) and h3s, as they appear in the PDF text, with their level.
$html = Cleanup_Report_PDF::render_html( $data );
// In document order: the checker uses the next heading to tell whether a section continues on the next page.
preg_match_all( '#<h2>(?:<span class="n">\d+</span>)?(.*?)</h2>|<h3>(.*?)</h3>#s', $html, $m, PREG_SET_ORDER );
$headings = [];
foreach ( $m as $match ) {
	$level = '' !== $match[1] ? 2 : 3;
	$h     = trim( html_entity_decode( wp_strip_all_tags( 2 === $level ? $match[1] : ( $match[2] ?? '' ) ), ENT_QUOTES, 'UTF-8' ) );
	if ( '' !== $h ) {
		$headings[] = $level . "\t" . $h; // "2<tab>Title" (h2) or "3<tab>Title" (h3)
	}
}
file_put_contents( $out . '.headings', implode( "\n", $headings ) . "\n" );

printf(
	"pages=%d size=%dKB time=%.1fs memory=%dMB filename=%s\n",
	$pdf->getCanvas()->get_page_count(),
	filesize( $out ) / 1024,
	microtime( true ) - $start,
	memory_get_peak_usage() / 1048576,
	Cleanup_Report_PDF::filename( $data )
);
