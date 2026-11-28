<?php
/**
 * Document head: fonts, the stylesheet (shared by every report type), and the browser preview's bar and watermark.
 *
 * Part of templates/report-pdf.php: included from inside Cleanup_Report_PDF::render_html(), so every variable here is
 * local to that method, not global.
 */
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- method scope, not global.
if ( ! defined( 'ABSPATH' ) ) { exit; }
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<?php if ( $web ) : ?><meta name="viewport" content="width=860"><?php endif; ?>
<title><?php echo esc_html( sprintf( /* translators: 1: report type, 2: website or client name */ __( '%1$s – %2$s', 'cleanup-report-generator' ), $type_info['eyebrow'], $host ?: $client ) ); ?></title>
<style>
<?php
// Roboto (wpmudev.com's typeface, SIL OFL 1.1, assets/fonts/OFL.txt). Characters Roboto lacks fall back to DejaVu Sans glyph by glyph.
foreach ( [ [ 'Regular', 'normal', 'normal' ], [ 'Bold', 'bold', 'normal' ], [ 'Italic', 'normal', 'italic' ], [ 'BoldItalic', 'bold', 'italic' ] ] as [ $file, $weight, $style ] ) {
    printf( "@font-face { font-family: 'Roboto'; src: url('%s') format('truetype'); font-weight: %s; font-style: %s; }\n", esc_attr( $asset( 'assets/fonts/Roboto-' . $file . '.ttf' ) ), esc_attr( $weight ), esc_attr( $style ) );
}
?>
@page { margin: 24mm 18mm 22mm 18mm; }
@page :first { margin: 0; }
* { box-sizing: border-box; }
body { font-family: 'Roboto', 'DejaVu Sans', sans-serif; font-size: 9pt; line-height: 1.55; color: #1f1f1f; margin: 0; }
p { margin: 0 0 6pt; }
/* Long unbroken strings (payloads, slugs, URLs) wrap anywhere, including inside table cells, so they never widen a column past the margin. */
p, li, td, th, div, h1, h2, h3, code, span, a { overflow-wrap: anywhere; word-wrap: break-word; }
ul, ol { margin: 0 0 6pt; padding-left: 13pt; }
li { margin-bottom: 3pt; }
a { color: <?php echo esc_attr( $primary ); ?>; text-decoration: none; }
code { font-family: 'DejaVu Sans Mono', monospace; font-size: 7.8pt; color: #1a1a1a; background: #f2f2f2; padding: 0 2pt; }
strong { color: #1a1a1a; }
table { border-collapse: collapse; width: 100%; }
.keep, tr, .shot, .callout, .tile, .kpi { page-break-inside: avoid; }
.head { page-break-inside: avoid; page-break-after: avoid; } /* heading + intro stays with the table after it */
.break { page-break-before: always; }

/* ---------- Cover (light: soft blue-white page, dark type, blue accent, white card; the dark theme overrides below) ---------- */
.cover { position: relative; height: 296.5mm; background: <?php echo esc_attr( $mix( $primary, 0.96 ) ); ?>; color: #101828; page-break-after: always; overflow: hidden; }
.cover .glow { position: absolute; left: 92mm; top: -78mm; width: 190mm; height: 190mm; opacity: <?php echo esc_attr( $glow_op( 0.35 ) ); ?>; }
.cover .glow2 { position: absolute; left: -70mm; top: 170mm; width: 150mm; height: 150mm; opacity: <?php echo esc_attr( $glow_op( 0.22 ) ); ?>; }
.cover .brand { position: absolute; left: 22mm; top: 20mm; width: 120mm; font-size: 12pt; font-weight: bold; color: #101828; }
.cover .brand img { max-height: 30pt; }
.cover .conf { position: absolute; right: 22mm; top: 21mm; font-size: 6.5pt; letter-spacing: 1.2pt; text-transform: uppercase; color: #667085; border: 0.6pt solid #d0d5dd; border-radius: 9pt; padding: 3.5pt 8pt; }
.cover .inner { position: absolute; left: 22mm; width: 166mm; top: 78mm; }
.cover .eyebrow { display: inline-block; font-size: 7.5pt; font-weight: bold; letter-spacing: 1.4pt; text-transform: uppercase; color: <?php echo esc_attr( $on( $primary ) ); ?>; background: <?php echo esc_attr( $primary ); ?>; border-radius: 10pt; padding: 5pt 11pt; }
.cover h1 { line-height: 1.12; margin: 14pt 0 6pt; color: #101828; font-weight: bold; }
.cover .site { font-size: 11pt; color: #475467; }
.cover .site strong { color: #101828; }
.cover .chip { display: inline-block; margin-top: 16pt; font-size: 7.5pt; font-weight: bold; letter-spacing: 0.6pt; text-transform: uppercase; border-radius: 10pt; padding: 5pt 11pt; }
.chip.ok   { background: <?php echo esc_attr( $col['success'] ); ?>; color: <?php echo esc_attr( $on( $col['success'] ) ); ?>; }
.chip.warn { background: <?php echo esc_attr( $col['warning'] ); ?>; color: <?php echo esc_attr( $on( $col['warning'] ) ); ?>; }
.cover .card { position: absolute; left: 22mm; top: 205mm; width: 153.3mm; /* 166mm minus padding: Dompdf ignores box-sizing on positioned boxes */ background: #ffffff; border: 0.6pt solid #e4e7ec; border-radius: 12pt; padding: 15pt 18pt 13pt; }
.cover .stats td { width: 33.33%; vertical-align: top; border-left: 0.6pt solid #e4e7ec; padding: 0 0 0 12pt; }
.cover .stats td:first-child { border-left: 0; padding-left: 0; }
.cover .stats .n { font-size: 25pt; font-weight: bold; color: #101828; line-height: 1.05; }
.cover .stats .n small { font-size: 12pt; color: #98a2b3; font-weight: normal; }
.cover .stats .l { font-size: 7pt; color: #667085; margin-top: 3pt; }
.cover .meta { margin-top: 12pt; border-top: 0.6pt solid #e4e7ec; }
.cover .meta td { padding: 9pt 12pt 0 0; vertical-align: top; }
.cover .meta .k { font-size: 6.3pt; letter-spacing: 1pt; text-transform: uppercase; color: #98a2b3; margin-bottom: 1.5pt; }
.cover .meta .v { font-size: 8.8pt; font-weight: bold; color: #101828; }
.cover .foot { position: absolute; left: 22mm; width: 166mm; top: 276mm; font-size: 6.5pt; color: #667085; }
.cover .foot .dot { color: <?php echo esc_attr( $primary ); ?>; }

/* Pills and tags: capitals only, so a normal line height leaves the text sitting low; line-height 1 + even padding centres it. */
.cover .eyebrow, .cover .chip, .cover .conf, .pill, .badge { line-height: 1; }

/* ---------- Headings ---------- */
h1.page { font-size: 20pt; color: #1a1a1a; margin: 0 0 4pt; }
.lede { font-size: 10pt; color: #4d4d4d; margin-bottom: 14pt; }
h2 { font-size: 14pt; color: #1a1a1a; margin: 0 0 9pt; padding: 0 0 5pt; border-bottom: 1pt solid #d0d5dd; }
h2 .n { font-size: 9.5pt; color: <?php echo esc_attr( $on( $primary ) ); ?>; background: <?php echo esc_attr( $primary ); ?>; padding: 1pt 4pt; margin-right: 6pt; }
h3 { font-size: 10pt; color: #1a1a1a; margin: 12pt 0 5pt; }
.sec { margin-top: 20pt; }
.sec.first { margin-top: 0; }
.sub { color: #4d4d4d; margin-bottom: 9pt; }

/* ---------- At a glance ---------- */
.callout { border-radius: 5pt; padding: 10pt 13pt; margin-bottom: 14pt; font-size: 9.5pt; }
.callout.ok   { background: <?php echo esc_attr( $col['success'] ); ?>; color: <?php echo esc_attr( $on( $col['success'] ) ); ?>; }
.callout.warn { background: <?php echo esc_attr( $col['warning'] ); ?>; color: <?php echo esc_attr( $on( $col['warning'] ) ); ?>; }
.callout strong { color: inherit; }
.tiles, .kpis { table-layout: fixed; }
.tiles td { width: 25%; padding: 0 2.25pt; vertical-align: top; } /* every cell loses 4.5pt to gaps, so the tiles come out equal */
.tiles td:first-child { padding: 0 4.5pt 0 0; } .tiles td:last-child { padding: 0 0 0 4.5pt; }
.tile { border-radius: 5pt; padding: 10pt 8pt 9pt; text-align: center; }
.tile .n { font-size: 22pt; font-weight: bold; line-height: 1.05; }
.tile .l { font-size: 7pt; text-transform: uppercase; letter-spacing: 0.8pt; font-weight: bold; margin-top: 2pt; }
.tile .d { font-size: 7pt; }
.tile.critical { background: <?php echo esc_attr( $col['danger'] ); ?>; } .tile.critical .n, .tile.critical .l { color: <?php echo esc_attr( $on( $col['danger'] ) ); ?>; } .tile.critical .d { color: <?php echo esc_attr( $soft( $col['danger'] ) ); ?>; }
.tile.medium   { background: <?php echo esc_attr( $col['warning'] ); ?>; } .tile.medium .n, .tile.medium .l { color: <?php echo esc_attr( $on( $col['warning'] ) ); ?>; } .tile.medium .d { color: <?php echo esc_attr( $soft( $col['warning'] ) ); ?>; }
.tile.low      { background: <?php echo esc_attr( $col['success'] ); ?>; } .tile.low .n, .tile.low .l { color: <?php echo esc_attr( $on( $col['success'] ) ); ?>; } .tile.low .d { color: <?php echo esc_attr( $soft( $col['success'] ) ); ?>; }
.tile.total    { background: <?php echo esc_attr( $tint ); ?>; } .tile.total .n, .tile.total .l { color: <?php echo esc_attr( $primary ); ?>; } .tile.total .d { color: #475467; }
.kpis { margin-top: 10pt; }
.kpis td { width: 33.33%; padding: 0 2.25pt; } .kpis td:first-child { padding: 0 4.5pt 0 0; } .kpis td:last-child { padding: 0 0 0 4.5pt; }
.kpi { background: <?php echo esc_attr( $primary ); ?>; border-radius: 5pt; padding: 8pt 10pt; }
.kpi .n { font-size: 13pt; font-weight: bold; color: <?php echo esc_attr( $on( $primary ) ); ?>; }
.kpi .l { font-size: 7.5pt; color: <?php echo esc_attr( $soft( $primary ) ); ?>; }
.finding { margin-top: 14pt; background: <?php echo esc_attr( $tint ); ?>; border-left: 3pt solid <?php echo esc_attr( $primary ); ?>; padding: 9pt 12pt; font-size: 9.5pt; color: #1f2937; }
.finding strong { color: #101828; }
.toc { margin-top: 16pt; }
.toc td { padding: 4.5pt 0; border-bottom: 0.5pt solid #dddddd; font-size: 9.5pt; color: #1a1a1a; }
.toc .n { width: 22pt; color: <?php echo esc_attr( $primary ); ?>; font-weight: bold; }

/* ---------- Tables, pills ---------- */
.grid { margin: 2pt 0 8pt; table-layout: fixed; } /* column widths come from the header, never from a long word in a cell */
.grid th { background: #f2f4f7; color: #344054; border-bottom: 1.2pt solid <?php echo esc_attr( $primary ); ?>; font-size: 6.8pt; text-transform: uppercase; letter-spacing: 0.7pt; text-align: left; padding: 5.5pt 7pt; }
.grid td { padding: 6pt 7pt; border-bottom: 0.5pt solid #dddddd; vertical-align: top; }
.grid td.name { font-weight: bold; color: #1a1a1a; }
.pill { display: inline-block; border-radius: 7pt; padding: 2.5pt 6pt; font-size: 6.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5pt; white-space: nowrap; }
.pill.ok   { background: <?php echo esc_attr( $col['success'] ); ?>; color: <?php echo esc_attr( $on( $col['success'] ) ); ?>; }
.pill.todo { background: <?php echo esc_attr( $col['danger'] ); ?>; color: <?php echo esc_attr( $on( $col['danger'] ) ); ?>; }
.pill.rec  { background: <?php echo esc_attr( $col['warning'] ); ?>; color: <?php echo esc_attr( $on( $col['warning'] ) ); ?>; }
.pill.na   { background: <?php echo esc_attr( $col['neutral'] ); ?>; color: <?php echo esc_attr( $on( $col['neutral'] ) ); ?>; }
.pill.critical { background: <?php echo esc_attr( $col['danger'] ); ?>; color: <?php echo esc_attr( $on( $col['danger'] ) ); ?>; }
.pill.high     { background: <?php echo esc_attr( $col['high'] ); ?>; color: <?php echo esc_attr( $on( $col['high'] ) ); ?>; }
.pill.medium   { background: <?php echo esc_attr( $col['warning'] ); ?>; color: <?php echo esc_attr( $on( $col['warning'] ) ); ?>; }
.pill.low      { background: <?php echo esc_attr( $primary ); ?>; color: <?php echo esc_attr( $on( $primary ) ); ?>; }
.muted { color: #6b6b6b; }
.go { margin-top: 2pt; font-size: 7.5pt; font-weight: bold; }

/* ---------- Boxes ---------- */
.box { border: 0.7pt solid #d9d9d9; border-top: 3pt solid <?php echo esc_attr( $primary ); ?>; padding: 10pt 12pt; margin-bottom: 9pt; } /* square: Dompdf draws a thick top border badly on rounded corners */
.box.soft  { background: <?php echo esc_attr( $mix( $primary, 0.95 ) ); ?>; border: 0.7pt solid <?php echo esc_attr( $edge ); ?>; border-radius: 5pt; color: #1f2937; }
.box.soft h3, .box.soft strong { color: #101828; }
.box.soft.muted { color: #667085; }
.box.amber { border: 1.2pt solid <?php echo esc_attr( $col['warning'] ); ?>; }
.box h3 { margin-top: 0; }
.box.cap  { background: <?php echo esc_attr( $col['warning'] ); ?>; border-bottom: 0; border-radius: 5pt 5pt 0 0; margin-bottom: 0; padding: 7pt 12pt; }
.box.cap h3 { margin-bottom: 0; color: <?php echo esc_attr( $on( $col['warning'] ) ); ?>; }
.box.body { border-top: 0; border-radius: 0 0 5pt 5pt; padding-top: 8pt; }
.two td { width: 50%; vertical-align: top; }
.two td:first-child { padding-right: 5pt; } .two td:last-child { padding-left: 5pt; }

/* ---------- Screenshots ---------- */
.shot { margin-bottom: 14pt; }
.shot .cap { margin-bottom: 5pt; }
.shot .cap .t { font-weight: bold; color: #1a1a1a; }
.shot img { border: 0.7pt solid #cccccc; }
.badge { display: inline-block; border-radius: 3pt; padding: 2pt 5pt; font-size: 6.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.6pt; margin-right: 5pt; }
.badge.before { background: <?php echo esc_attr( $col['danger'] ); ?>; color: <?php echo esc_attr( $on( $col['danger'] ) ); ?>; }
.badge.after  { background: <?php echo esc_attr( $col['success'] ); ?>; color: <?php echo esc_attr( $on( $col['success'] ) ); ?>; }
.badge.evidence, .badge.other { background: <?php echo esc_attr( $primary ); ?>; color: <?php echo esc_attr( $on( $primary ) ); ?>; }

/* ---------- Closing ---------- */
.closing { margin-top: 18pt; background: <?php echo esc_attr( $tint ); ?>; border: 0.7pt solid <?php echo esc_attr( $edge ); ?>; color: #344054; border-radius: 6pt; padding: 14pt 16pt; }
.closing .t { font-size: 11pt; font-weight: bold; color: #101828; margin-bottom: 3pt; }
.closing a { color: <?php echo esc_attr( $primary ); ?>; }
/* ---------- Website optimization: score table, code appendix ---------- */
.grid.scores td, .grid.scores th { text-align: center; }
.grid.scores td.name, .grid.scores th:first-child { text-align: left; }
.grid tr.avg td { background: <?php echo esc_attr( $tint ); ?>; border-bottom: 0; }
pre.code { font-family: 'DejaVu Sans Mono', monospace; font-size: 6.6pt; line-height: 1.45; color: #1a1a1a; background: #f6f7f9; border: 0.6pt solid #e4e7ec; border-radius: 4pt; padding: 8pt 10pt; margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; word-wrap: break-word; }
<?php if ( $dark ) : ?>
/* ---------- Dark theme (Settings › Branding › Report theme): near-black cover and panels, like wpmudev.com ---------- */
.cover { background: #0e0e0e; color: #fff; }
.cover .glow { opacity: <?php echo esc_attr( $glow_op( 1 ) ); ?>; } .cover .glow2 { opacity: <?php echo esc_attr( $glow_op( 0.55 ) ); ?>; }
.cover .brand, .cover h1, .cover .site strong, .cover .stats .n, .cover .meta .v { color: #fff; }
.cover .conf { color: #aaaaaa; border-color: #3a3a3a; }
.cover .site, .cover .stats .l { color: #aaaaaa; }
.cover .card { background: #1a1a1a; border-color: #2c2c2c; }
.cover .stats td, .cover .meta { border-color: #2c2c2c; }
.cover .stats .n small, .cover .meta .k { color: #808080; }
.cover .foot { color: #707070; }
.tile.total { background: #1a1a1a; } .tile.total .n, .tile.total .l { color: #fff; } .tile.total .d { color: #aaaaaa; }
.finding { background: #0e0e0e; color: #e6e6e6; } .finding strong { color: #fff; }
.grid th { background: #1a1a1a; color: #fff; border-bottom: 0; }
.box.soft { background: #1a1a1a; border: 0; color: #e6e6e6; } .box.soft h3, .box.soft strong { color: #fff; } .box.soft.muted { color: #aaaaaa; }
.closing { background: #0e0e0e; border: 0; color: #cccccc; } .closing .t { color: #fff; } .closing a { color: #8fb3ff; }
h2 { border-bottom-color: #1a1a1a; }
.grid tr.avg td { background: #1a1a1a; color: #fff; } .grid tr.avg td strong { color: #fff; }
<?php endif; ?>
<?php if ( $web ) : ?>
/* ---------- Browser preview only: pages as sheets on a dark desk, with a bar to download the PDF ---------- */
html { background: #e9edf3; }
body { padding: 72px 0 48px; }
.webbar { position: fixed; top: 0; left: 0; right: 0; z-index: 10; height: 52px; display: flex; align-items: center; gap: 10px; padding: 0 20px; background: #ffffff; border-bottom: 1px solid #e4e7ec; font-size: 13px; }
.webbar .t { flex: 1; color: #475467; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.webbar .t strong { color: #101828; }
.webbar a { color: #101828; padding: 7px 14px; border: 1px solid #d0d5dd; border-radius: 6px; font-weight: bold; }
.webbar a.primary { color: #fff; }
.webbar a.primary { background: <?php echo esc_attr( $primary ); ?>; border-color: <?php echo esc_attr( $primary ); ?>; }
.webbar .nf { padding: 3px 9px; border-radius: 999px; background: #fee4e2; color: #b42318; font-weight: bold; font-size: 12px; }
/* Not approved yet: "NOT FINAL" across the screen (and every printed page). */
.notfinal { position: fixed; top: 50%; left: 50%; z-index: 5; transform: translate(-50%, -50%) rotate(-35deg); font-size: 120px; font-weight: bold; letter-spacing: 8px; white-space: nowrap; color: rgba(217, 45, 32, 0.11); pointer-events: none; user-select: none; }
.cover, .paper { width: 210mm; margin: 0 auto 14mm; box-shadow: 0 4px 20px rgba(16, 24, 40, 0.12); }
.cover { height: 297mm; }
.cover .card { width: 166mm; } /* browsers apply box-sizing to positioned boxes; Dompdf doesn't */
.paper { background: #fff; padding: 20mm 18mm 22mm; }
.break { height: 14mm; margin: 20mm -18mm; background: #e9edf3; }
@media print {
    .webbar { display: none; }
    html { background: #fff; }
    body { padding: 0; }
    .cover, .paper { width: auto; margin: 0; box-shadow: none; }
    .paper { padding: 0; }
    .break { height: 0; margin: 0; background: none; box-shadow: none; }
}
<?php endif; ?>
</style>
</head>
<body>
<?php if ( $web ) : ?>
<div class="webbar">
    <?php if ( ! empty( $data['web']['fallback'] ) ) : ?>
    <div class="t"><?php echo wp_kses( sprintf( /* translators: %s: why the PDF couldn't be made */ __( '<strong>Print view</strong> · %s. Use your browser\'s Save as PDF.', 'cleanup-report-generator' ), esc_html( $data['web']['fallback'] ) ), [ 'strong' => [] ] ); ?></div>
    <?php else : ?>
    <div class="t"><?php
    echo wp_kses(
        empty( $data['final'] )
            /* translators: %s: website or client name */
            ? sprintf( __( '<strong>Preview</strong> · %s · not approved yet, so this isn\'t the final report', 'cleanup-report-generator' ), esc_html( $host ?: $client ) )
            /* translators: %s: website or client name */
            : sprintf( __( '<strong>Preview</strong> · %s · this is how the PDF reads', 'cleanup-report-generator' ), esc_html( $host ?: $client ) ),
        [ 'strong' => [] ]
    );
    ?></div>
    <?php endif; ?>
    <?php if ( empty( $data['final'] ) ) : ?><span class="nf"><?php echo esc_html( $data['watermark'] ); ?></span><?php endif; ?>
    <?php if ( ! empty( $data['web']['edit'] ) ) : ?><a href="<?php echo esc_url( $data['web']['edit'] ); ?>"><?php echo esc_html( $data['web']['edit_label'] ?? __( 'Edit report', 'cleanup-report-generator' ) ); ?></a><?php endif; ?>
    <?php if ( ! empty( $data['web']['pdf'] ) ) : ?><a class="primary" href="<?php echo esc_url( $data['web']['pdf'] ); ?>"><?php esc_html_e( 'Download PDF', 'cleanup-report-generator' ); ?></a><?php endif; ?>
</div>
<?php if ( empty( $data['final'] ) ) : ?><div class="notfinal" aria-hidden="true" style="font-size:<?php echo (int) min( 120, 1300 / max( 1, mb_strlen( $data['watermark'] ) * 0.72 ) ); ?>px"><?php echo esc_html( $data['watermark'] ); ?></div><?php endif; ?>
<?php endif; ?>
