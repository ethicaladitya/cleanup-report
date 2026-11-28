<?php
/**
 * The report layout (A4), used for the PDF (Dompdf) and the browser preview, for every report type.
 *
 * Dompdf has no flexbox/grid and no emoji font: layout uses tables and blocks
 * only, and glyphs Roboto or DejaVu Sans can draw. The running header/footer and page numbers are
 * drawn by Cleanup_Report_PDF::build() on every page except the cover.
 *
 * Page-break rules (keep these when editing):
 *  - .keep never splits; a section heading is always inside a .keep with the
 *    first thing after it, so a heading can't be stranded at a page bottom.
 *  - Table rows never split; long tables flow and repeat their header row.
 *
 * Parts, in order:
 *  - parts/setup.php       colours and helpers, the website, date and names (shared)
 *  - types/<type>.php      what the report type needs: $sections, $cover and its own values
 *  - parts/head.php        stylesheet, and the browser preview's bar (shared)
 *  - parts/cover.php       the cover (shared; filled from $cover)
 *  - types/<type>-body.php the summary page and the numbered sections
 * <type> is the report type's template (Cleanup_Report_Types::TYPES): malware-cleanup or site-optimization.
 *
 * Available: $data (Cleanup_Report_Render::get_data()), $images (Cleanup_Report_PDF::image_box() results keyed by URL, plus 'logo').
 *
 * Included from inside Cleanup_Report_PDF::render_html(), so every variable here is local to that method, not global.
 */
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- see above: method scope, not global.
if ( ! defined( 'ABSPATH' ) ) { exit; }

$type_info = Cleanup_Report_Types::get( $data['type'] ?? '' ); // a fixed list: the template name never comes from input
$type_file = __DIR__ . '/types/' . $type_info['template'];

require __DIR__ . '/parts/setup.php';
require $type_file . '.php';
require __DIR__ . '/parts/head.php';
require __DIR__ . '/parts/cover.php';
?>

<?php if ( $web ) : ?><div class="paper"><?php endif; ?>
<?php require $type_file . '-body.php'; ?>

<?php if ( $web ) : ?></div><?php endif; ?>
<?php if ( ! empty( $data['web']['print'] ) ) : ?><script>window.addEventListener( 'load', function () { window.print(); } );</script><?php endif; ?>
</body>
</html>
