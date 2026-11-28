<?php
/**
 * The report layout (A4), used for the PDF (Dompdf) and the browser preview.
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
 * Available: $data (Cleanup_Report_Render::get_data()), $images (Cleanup_Report_PDF::image_box() results keyed by URL, plus 'logo').
 *
 * Included from inside Cleanup_Report_PDF::render_html(), so every variable here is local to that method, not global.
 */
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- see above: method scope, not global.
if ( ! defined( 'ABSPATH' ) ) { exit; }

$primary = sanitize_hex_color( $data['brand']['primary'] ) ?: '#0059FF';
$dark    = 'dark' === ( $data['brand']['theme'] ?? 'light' ); // Settings › Branding › Report theme
$glow_k  = Cleanup_Report_Render::cover_glow( $data['brand']['glow'] ?? 100 ) / 100; // Settings › Branding › Cover gradient: scales both glows
$glow_op = static function ( $base ) use ( $glow_k ) { return rtrim( rtrim( number_format( $base * $glow_k, 3, '.', '' ), '0' ), '.' ); };
// Status colours (Settings › Branding) and readable text on them.
$col  = ( $data['brand']['colors'] ?? [] ) + [ 'success' => '#067647', 'warning' => '#FDB022', 'high' => '#C4320A', 'danger' => '#D92D20', 'neutral' => '#667085' ];
$rgb  = static function ( $hex ) { $h = ltrim( (string) $hex, '#' ); $h = 3 === strlen( $h ) ? preg_replace( '/(.)/', '$1$1', $h ) : $h; return array_map( 'hexdec', str_split( substr( str_pad( $h, 6, '0' ), 0, 6 ), 2 ) ); };
$lum  = static function ( $hex ) use ( $rgb ) { $c = array_map( static function ( $v ) { $v /= 255; return $v <= 0.03928 ? $v / 12.92 : ( ( $v + 0.055 ) / 1.055 ) ** 2.4; }, $rgb( $hex ) ); return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2]; };
$on   = static function ( $hex ) use ( $lum ) { $l = $lum( $hex ); return ( 1.05 / ( $l + 0.05 ) ) >= ( ( $l + 0.05 ) / ( 0.0213 + 0.05 ) ) ? '#ffffff' : '#101828'; }; // white vs near-black: whichever contrasts more
$soft = static function ( $hex ) use ( $rgb, $on ) { $c = $rgb( $hex ); $t = '#ffffff' === $on( $hex ) ? 255 : 0; $m = array_map( static function ( $v ) use ( $t ) { return (int) round( $v + ( $t - $v ) * 0.82 ); }, $c ); return vsprintf( '#%02x%02x%02x', $m ); }; // small text on the colour
$mix  = static function ( $hex, $amount ) use ( $rgb ) { return vsprintf( '#%02x%02x%02x', array_map( static function ( $v ) use ( $amount ) { return (int) round( $v + ( 255 - $v ) * $amount ); }, $rgb( $hex ) ) ); };
$tint = $mix( $primary, 0.92 ); // light panels in the primary colour's hue
$edge = $mix( $primary, 0.80 );
$web     = ! empty( $web ); // browser preview (Cleanup_Report_PDF::render_html( $data, true )): assets by URL, pages shown as sheets
$asset   = static function ( $rel ) use ( $web ) { return $web ? CLEANUP_REPORT_URL . $rel : 'file://' . CLEANUP_REPORT_PATH . $rel; };

$total   = (int) $data['s_critical'] + (int) $data['s_medium'] + (int) $data['s_low'];
$host    = $data['site'] ? ( wp_parse_url( $data['site'], PHP_URL_HOST ) ?: $data['site'] ) : '';
if ( $host && false !== strpos( $host, 'xn--' ) && function_exists( 'idn_to_utf8' ) ) {
    $host = idn_to_utf8( $host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 ) ?: $host; // Readable form of punycode domains.
}
$date_ts = $data['date'] ? strtotime( $data['date'] ) : false;
$date    = date_i18n( 'j F Y', $date_ts ?: time() );
$client  = $data['client'] ?: ( $host ?: 'Your website' );
// Long names and domains are shortened with an ellipsis.
$clip         = static function ( $text, $max ) { return mb_strlen( $text ) > $max ? rtrim( mb_substr( $text, 0, $max - 1 ) ) . '…' : $text; };
// The cover headline is the website (a domain reads better than a first name); the name goes under it as "Prepared for".
$headline     = $clip( $host ?: $client, 110 );
$prepared_for = $host && $data['client'] ? $clip( $data['client'], 70 ) : '';
$foot_name    = $clip( $data['client'] ?: $host, 60 );
$len          = mb_strlen( $headline );
if ( $host ) {
    // A domain has no spaces to wrap at, so size it to fit one 166 mm line (Roboto Bold ≈ 0.58 em per character).
    $h1_size = (int) max( 18, min( 38, floor( 470 / ( max( 1, $len ) * 0.58 ) ) ) );
} else {
    $h1_size = $len <= 24 ? 38 : ( $len <= 40 ? 32 : ( $len <= 64 ? 26 : 21 ) );
}
$brand_short  = $clip( preg_replace( '/\s+Services$/i', '', (string) $data['brand']['name'] ) ?: 'WPMU DEV', 40 );
$glow         = $glow_k > 0 ? $asset( 'assets/cover-glow.png' ) : ''; // not loaded when the cover gradient is off
$has     = static function ( $html ) { return '' !== trim( wp_strip_all_tags( (string) $html ) ); };

$note_html  = [ 'Cleanup_Report_Render', 'note_html' ];    // SEO / Staying protected: editor HTML, or plain text from older reports

/**
 * <li> items of a WYSIWYG list → [ name, slug, note ]. The folder (slug) is in <code>…</code> (what the plugin check's
 * buttons add) or in brackets at the end of the name, e.g. "Slider Revolution (revslider) – no licence provided";
 * the note is whatever follows a dash. Edition words in brackets, like "(Pro)", stay part of the name.
 */
$list_rows = static function ( $html ) {
    $rows = [];
    if ( ! preg_match_all( '#<li[^>]*>(.*?)</li>#is', (string) $html, $m ) ) {
        $m[1] = array_filter( array_map( 'trim', preg_split( '/<br\s*\/?>|\n/', wp_strip_all_tags( (string) $html, false ) ) ) );
    }
    foreach ( $m[1] as $li ) {
        $slug = preg_match( '#<code>(.*?)</code>#i', $li, $c ) ? wp_strip_all_tags( $c[1] ) : '';
        $text = trim( wp_strip_all_tags( preg_replace( '#<code>.*?</code>#i', '', $li ) ) );
        $note = '';
        if ( preg_match( '/^(.*?)\s+[—–-]\s+(.+)$/u', $text, $p ) ) {
            [ , $text, $note ] = $p;
        }
        if ( '' === $slug && preg_match( '/^(.*?)\s*\(\s*([A-Za-z0-9][A-Za-z0-9._-]*)\s*\)\s*$/u', $text, $b )
            && ! in_array( strtolower( $b[2] ), [ 'pro', 'premium', 'lite', 'free', 'plus', 'legacy', 'beta', 'old', 'new' ], true ) ) {
            [ , $text, $slug ] = $b;
        }
        if ( '' !== $text ) {
            $rows[] = [ 'name' => html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 'slug' => $slug, 'note' => html_entity_decode( $note, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ];
        }
    }
    return $rows;
};

$shot_labels = [ 'before' => 'Before cleanup', 'after' => 'After cleanup', 'evidence' => 'Evidence', 'other' => 'Screenshot' ];

// Defender counts.
$def_applied = count( array_filter( $data['defender_checklist'], static function ( $r ) { return 'applied' === $r['status']; } ) );
$def_pending = count( array_filter( $data['defender_checklist'], static function ( $r ) { return 'pending' === $r['status']; } ) );
// Two parts, as in the team's template: what we configured, and what the member has to (their own settings, and any
// of ours marked for them).
$def_parts = [ 'agent' => [], 'member' => [] ];
foreach ( $data['defender_checklist'] as $r ) {
    $def_parts[ ( 'member' === ( $r['owner'] ?? 'agent' ) || 'pending' === $r['status'] ) ? 'member' : 'agent' ][] = $r;
}

// Recommended actions (follow-ups), wording from the report's saved checklist.
$follow = $data['followups'];
$follow_open = count( array_filter( $follow, static function ( $f ) { return in_array( $f['status'], [ 'required', 'recommended' ], true ); } ) );

$pending_html = Cleanup_Report_Render::note_html( $data['pending_actions'] ); // editor HTML, or plain lines from older reports
$has_next     = null; // set below once reinstall/replace are parsed
$reinstall    = $list_rows( $data['reinstall_list'] );
$replace      = $list_rows( $data['replace_list'] );
$delist       = array_values( $data['delist_vendors'] ); // blocklists the member asks to delist the site
$has_next     = $pending_html || $follow || $reinstall || $replace || $delist;

// "Items for you to action": everything the member has to do. Follow-ups and Defender settings marked for the
// member, each Pending actions line (list item, or paragraph when it isn't a list), each plugin/theme to
// replace or reinstall, and each blocklist to request delisting from.
$pending_count = substr_count( $pending_html, '<li>' ) ?: substr_count( $pending_html, '<p>' );
$todo_count    = $follow_open + $def_pending + $pending_count + count( $replace ) + count( $reinstall ) + count( $delist );

// Resources: report's own links, then standard ones not already listed.
$doc_links = [];
foreach ( preg_split( '/\r\n|\r|\n/', (string) $data['documentation_links'] ) as $line ) {
    $line = trim( $line );
    if ( '' === $line ) {
        continue;
    }
    $title = '';
    $url   = $line;
    if ( preg_match( '/^(.+?):\s+(https?:\/\/\S+)$/', $line, $m ) ) {
        [ , $title, $url ] = $m;
    }
    if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
        $doc_links[] = [ 'title' => $title ?: preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( $url ) ), 'url' => $url ];
    }
}
$link_key = static function ( $u ) { return untrailingslashit( preg_replace( '#^https?://(www\.)?#', '', strtolower( $u ) ) ); };
$listed   = array_map( $link_key, wp_list_pluck( $doc_links, 'url' ) );
foreach ( [
    'https://wpmudev.com/docs/wpmu-dev-plugins/defender/'                           => 'Defender documentation',
    'https://wpmudev.com/docs/wpmu-dev-plugins/defender/#two-factor-auth' => 'Setting up two-factor authentication',
    'https://wpmudev.com/docs/wpmu-dev-plugins/defender/#hide-login-url'  => 'Hiding the login URL',
] as $url => $title ) {
    if ( ! in_array( $link_key( $url ), $listed, true ) ) {
        $doc_links[] = [ 'title' => $title, 'url' => $url ];
    }
}

$defaults        = Cleanup_Report_Render::default_texts( $host );
$seo_default     = $defaults['seo'];
$protect_default = $defaults['protect'];
$no_problem      = 'No problem identified';

// Sections present, in order (drives numbering and the contents list).
$sections = array_filter( [
    'evidence'     => $data['screenshots'] ? 'Malware visual identification' : null,
    'infection'    => $has( $data['infection_summary'] ) ? 'Infection summary' : null,
    'components'   => ( $data['components'] || $has( $data['improvements'] ) ) ? 'Vulnerable components & hardening' : null,
    'actions'      => 'Actions taken',
    'verification' => 'Verification',
    'defender'     => $data['defender_checklist'] ? 'Defender security configuration' : null,
    'next'         => 'Your next steps',
    'important'    => 'It is important to know',
] );
// Tables this short move as one unit with their heading (no lone rows on a new page); longer ones flow.
$short = static function ( array $rows ) { return count( $rows ) <= 6 ? 'keep' : 'flow'; };
$num = static function ( $key ) use ( $sections ) { return sprintf( '%02d', array_search( $key, array_keys( $sections ), true ) + 1 ); };
$h2  = static function ( $key ) use ( $sections, $num ) {
    return '<h2><span class="n">' . $num( $key ) . '</span> ' . esc_html( $sections[ $key ] ) . '</h2>';
};

// The summary page's key finding: the report's own line (older reports: the summary's first sentence).
$key_finding = (string) $data['key_finding'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<?php if ( $web ) : ?><meta name="viewport" content="width=860"><?php endif; ?>
<title><?php echo esc_html( 'Malware Cleanup Report – ' . ( $host ?: $client ) ); ?></title>
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
    <div class="t"><strong>Print view</strong> · <?php echo esc_html( $data['web']['fallback'] ); ?>. Use your browser's Save as PDF.</div>
    <?php else : ?>
    <div class="t"><strong>Preview</strong> · <?php echo esc_html( $host ?: $client ); ?> · <?php echo empty( $data['final'] ) ? 'not approved yet, so this isn\'t the final report' : 'this is how the PDF reads'; ?></div>
    <?php endif; ?>
    <?php if ( empty( $data['final'] ) ) : ?><span class="nf"><?php echo esc_html( $data['watermark'] ); ?></span><?php endif; ?>
    <?php if ( ! empty( $data['web']['edit'] ) ) : ?><a href="<?php echo esc_url( $data['web']['edit'] ); ?>">Edit report</a><?php endif; ?>
    <?php if ( ! empty( $data['web']['pdf'] ) ) : ?><a class="primary" href="<?php echo esc_url( $data['web']['pdf'] ); ?>">Download PDF</a><?php endif; ?>
</div>
<?php if ( empty( $data['final'] ) ) : ?><div class="notfinal" aria-hidden="true" style="font-size:<?php echo (int) min( 120, 1300 / max( 1, mb_strlen( $data['watermark'] ) * 0.72 ) ); ?>px"><?php echo esc_html( $data['watermark'] ); ?></div><?php endif; ?>
<?php endif; ?>

<!-- ======================= COVER ======================= -->
<div class="cover">
    <?php if ( $glow_k > 0 ) : ?>
    <img class="glow" src="<?php echo esc_attr( $glow ); ?>" alt="">
    <img class="glow2" src="<?php echo esc_attr( $glow ); ?>" alt="">
    <?php endif; ?>
    <div class="brand">
        <?php if ( ! empty( $images['logo'] ) ) : ?>
            <img src="<?php echo esc_attr( $images['logo']['src'] ); ?>" alt="<?php echo esc_attr( $data['brand']['name'] ); ?>">
        <?php else : ?>
            <?php echo esc_html( $brand_short ); ?>
        <?php endif; ?>
    </div>
    <div class="conf">Confidential</div>

    <div class="inner">
        <div class="eyebrow">Malware cleanup report</div>
        <h1 style="font-size:<?php echo (int) $h1_size; ?>pt"><?php echo esc_html( $headline ); ?></h1>
        <?php if ( $prepared_for ) : ?><div class="site">Prepared for <strong><?php echo esc_html( $prepared_for ); ?></strong></div><?php endif; ?>
        <div class="chip <?php echo $todo_count ? 'warn' : 'ok'; ?>"><?php echo $todo_count ? 'Cleaned · ' . (int) $todo_count . ' ' . ( 1 === $todo_count ? 'item' : 'items' ) . ' for you to action' : 'Cleaned · no action needed'; ?></div>
    </div>

    <div class="card">
        <table class="stats"><tr>
            <td><div class="n"><?php echo (int) $total; ?></div><div class="l">Issues found and fixed</div></td>
            <td><div class="n"><?php if ( $data['defender_checklist'] ) : ?><?php echo (int) $def_applied; ?><small> / <?php echo count( $data['defender_checklist'] ); ?></small><?php else : ?>—<?php endif; ?></div><div class="l">Defender settings applied</div></td>
            <td><div class="n"><?php echo (int) $todo_count; ?></div><div class="l">Items for you to action</div></td>
        </tr></table>
        <table class="meta"><tr>
            <td style="width:28%"><div class="k">Completed</div><div class="v"><?php echo esc_html( $date ); ?></div></td>
            <td style="width:22%"><div class="k">Report ID</div><div class="v">#<?php echo (int) $data['post_id']; ?></div></td>
            <td style="width:50%"><div class="k">Prepared by</div><div class="v"><?php echo esc_html( $brand_short ); ?> security team</div></td>
        </tr></table>
    </div>

    <div class="foot"><span class="dot">●</span>&nbsp; Confidential<?php echo $foot_name ? ' — prepared for ' . esc_html( $foot_name ) : ''; ?>. Describes the security state of the website at the time of cleanup.</div>
</div>

<?php if ( $web ) : ?><div class="paper"><?php endif; ?>
<!-- ======================= AT A GLANCE ======================= -->
<h1 class="page">At a glance</h1>
<div class="lede">A summary of the cleanup of <?php echo esc_html( $host ?: $client ); ?> on <?php echo esc_html( $date ); ?>.</div>

<?php if ( $todo_count ) : ?>
    <div class="callout warn"><strong>Your site has been cleaned.</strong> <?php echo (int) $todo_count; ?> <?php echo 1 === $todo_count ? 'item needs' : 'items need'; ?> your attention to keep it secure — see <strong>Your next steps</strong>.</div>
<?php else : ?>
    <div class="callout ok"><strong>Your site has been cleaned.</strong> No further action is needed from you.</div>
<?php endif; ?>

<table class="tiles"><tr>
    <td><div class="tile critical"><div class="n"><?php echo (int) $data['s_critical']; ?></div><div class="l">Critical</div><div class="d">Malware &amp; backdoors</div></div></td>
    <td><div class="tile medium"><div class="n"><?php echo (int) $data['s_medium']; ?></div><div class="l">Medium</div><div class="d">Vulnerable components</div></div></td>
    <td><div class="tile low"><div class="n"><?php echo (int) $data['s_low']; ?></div><div class="l">Low</div><div class="d">Hardening</div></div></td>
    <td><div class="tile total"><div class="n"><?php echo (int) $total; ?></div><div class="l">Total</div><div class="d">Issues found</div></div></td>
</tr></table>

<table class="kpis"><tr>
    <td><div class="kpi"><div class="n"><?php echo $data['defender_checklist'] ? (int) $def_applied . ' of ' . count( $data['defender_checklist'] ) : '—'; ?></div><div class="l">Defender settings applied</div></div></td>
    <td><div class="kpi"><div class="n"><?php echo (int) $todo_count; ?></div><div class="l">Items for you to action</div></div></td>
    <td><div class="kpi"><div class="n"><?php echo count( $data['components'] ); ?></div><div class="l">Vulnerable components flagged</div></div></td>
</tr></table>

<?php if ( $key_finding ) : ?>
    <div class="finding"><strong>Key finding:</strong> <?php echo esc_html( $key_finding ); ?></div>
<?php endif; ?>

<table class="toc">
    <?php foreach ( $sections as $key => $title ) : ?>
        <tr><td class="n"><?php echo esc_html( $num( $key ) ); ?></td><td><?php echo esc_html( $title ); ?></td></tr>
    <?php endforeach; ?>
</table>

<div class="break"></div>

<?php $first = ' first'; ?>

<?php if ( isset( $sections['evidence'] ) ) : ?>
<!-- ======================= VISUAL IDENTIFICATION ======================= -->
<div class="sec<?php echo esc_attr( $first ); $first = ''; ?>">
<?php foreach ( array_values( $data['screenshots'] ) as $i => $shot ) :
    $img  = $images[ $shot['url'] ] ?? null;
    $type = isset( $shot_labels[ $shot['type'] ] ) ? $shot['type'] : 'other'; ?>
    <div class="keep">
        <?php if ( 0 === $i ) : ?><?php echo wp_kses_post( $h2( 'evidence' ) ); ?><div class="sub">Scan results before and after the cleanup.</div><?php endif; ?>
        <div class="shot">
            <div class="cap"><span class="badge <?php echo esc_attr( $type ); ?>"><?php echo esc_html( $shot_labels[ $type ] ); ?></span><span class="t"><?php echo esc_html( $shot['title'] ?: 'Security scan' ); ?></span></div>
            <?php if ( $img ) : ?>
                <img src="<?php echo esc_attr( $img['src'] ); ?>" width="<?php echo (int) $img['w']; ?>"<?php echo $img['h'] ? ' height="' . (int) $img['h'] . '"' : ''; ?> alt="">
            <?php else : ?>
                <div class="box soft muted">Screenshot unavailable.</div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ( isset( $sections['infection'] ) ) : ?>
<!-- ======================= INFECTION SUMMARY ======================= -->
<div class="sec<?php echo esc_attr( $first ); $first = ''; ?>">
    <div class="head"><?php echo wp_kses_post( $h2( 'infection' ) ); ?></div>
    <?php echo wp_kses_post( $data['infection_summary'] ); ?>
</div>
<?php endif; ?>

<?php if ( isset( $sections['components'] ) ) : ?>
<!-- ======================= COMPONENTS ======================= -->
<div class="sec<?php echo esc_attr( $first ); $first = ''; ?>">
    <div class="head"><?php echo wp_kses_post( $h2( 'components' ) ); ?>
    <?php if ( $data['components'] ) : ?><div class="sub">Plugins and themes that exposed the site to attack or are out of date.</div><?php endif; ?></div>
    <?php if ( $data['components'] ) : ?>
    <table class="grid">
        <thead><tr><th style="width:27%">Component</th><th style="width:13%">Installed</th><th>Issue</th><th style="width:14%">Severity</th></tr></thead>
        <tbody>
        <?php foreach ( $data['components'] as $c ) : ?>
            <tr><td class="name"><?php echo esc_html( $c['name'] ); ?></td><td><?php echo esc_html( $c['version'] ?: '—' ); ?></td><td><?php echo esc_html( $c['issue'] ); ?></td><td><span class="pill <?php echo esc_attr( $c['severity'] ); ?>"><?php echo esc_html( ucfirst( $c['severity'] ) ); ?></span></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php if ( $has( $data['improvements'] ) ) : ?>
        <div class="keep"><h3>Suggested improvements &amp; hardening</h3><?php echo wp_kses_post( $data['improvements'] ); ?></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ======================= ACTIONS TAKEN ======================= -->
<div class="sec<?php echo esc_attr( $first ); $first = ''; ?>">
    <div class="head"><?php echo wp_kses_post( $h2( 'actions' ) ); ?></div>
    <?php if ( $has( $data['highlights'] ) ) : ?>
        <?php echo wp_kses_post( $data['highlights'] ); ?>
    <?php endif; ?>
    <div class="box soft keep" style="margin-top:6pt">
        <h3>Every <?php echo esc_html( $brand_short ); ?> cleanup includes</h3>
        <table class="two"><tr><td>
            <ul>
                <li>A backup, whenever possible</li>
                <li>An evaluation of the infection</li>
                <li>A manual check by our expert for known malware</li>
                <li>External tools to find potential backdoors and malware</li>
            </ul>
        </td><td>
            <ul>
                <li>Cleanup by reinstalling WordPress, plugins and theme</li>
                <li>Defender configured to keep protecting your site</li>
                <li>A quality review (Q&amp;A) before this report is shared</li>
            </ul>
        </td></tr></table>
    </div>
</div>

<!-- ======================= VERIFICATION ======================= -->
<div class="sec">
    <div class="keep">
        <?php echo wp_kses_post( $h2( 'verification' ) ); ?>
        <?php if ( $has( $data['verification_summary'] ) ) : ?><?php echo wp_kses_post( $data['verification_summary'] ); ?><?php endif; ?>
        <table class="grid" style="margin-top:4pt">
            <thead><tr><th style="width:38%">Check</th><th>Result</th></tr></thead>
            <tbody>
                <tr><td class="name">Additional sites on the hosting account</td><td><?php echo $data['additional_sites'] ? nl2br( esc_html( $data['additional_sites'] ) ) : esc_html( $no_problem ); ?><div class="muted" style="font-size:7.5pt">Sharing one hosting account between sites can re-infect a cleaned site.</div></td></tr>
                <tr><td class="name">Security blocklists (delist request)</td><td><?php
                    if ( $delist ) {
                        echo esc_html( sprintf( 'Listed by %s: %s. Please request delisting (see Your next steps).', 1 === count( $delist ) ? '1 security vendor' : count( $delist ) . ' security vendors', implode( ', ', wp_list_pluck( $delist, 'label' ) ) ) );
                    }
                    if ( $data['delist'] ) {
                        echo ( $delist ? '<br>' : '' ) . nl2br( esc_html( $data['delist'] ) );
                    }
                    if ( ! $delist && ! $data['delist'] ) {
                        echo esc_html( $no_problem );
                    }
                    ?><div class="muted" style="font-size:7.5pt">Some security providers may list an infected site as suspicious; a delist request removes the warning.</div></td></tr>
                <?php foreach ( $data['site_checks'] as $c ) : ?>
                    <tr><td class="name"><?php echo esc_html( $c['label'] ); ?></td><td><?php echo esc_html( $c['answer'] ); ?><?php if ( '' !== $c['note'] ) : ?><?php echo $c['answer'] ? '<br>' : ''; ?><span class="muted"><?php echo wp_kses_post( make_clickable( esc_html( $c['note'] ) ) ); ?></span><?php endif; ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ( isset( $sections['defender'] ) ) : ?>
<!-- ======================= DEFENDER ======================= -->
<div class="sec">
    <div class="head"><?php echo wp_kses_post( $h2( 'defender' ) ); ?>
    <div class="sub">We configured WPMU DEV Defender to keep protecting your site. Some settings need your attention: please apply the ones listed under <strong>To be done by you</strong> as soon as possible.</div></div>
    <?php
    foreach ( [
        'agent'  => [ 'Configured by our team', '' ],
        'member' => [ 'To be done by you', 'Configuration must be done by you: these settings need your own choices or accounts, or couldn\'t be completed on our side.' ],
    ] as $part => [ $part_title, $part_sub ] ) :
        $rows = $def_parts[ $part ];
        if ( ! $rows ) {
            continue;
        }
        ?>
        <div class="<?php echo count( $rows ) <= 6 ? 'keep' : 'flow'; ?>"><div class="head"><h3><?php echo esc_html( $part_title ); ?></h3><?php if ( $part_sub ) : ?><div class="sub"><?php echo esc_html( $part_sub ); ?></div><?php endif; ?></div>
        <table class="grid">
            <thead><tr><th style="width:36%">Setting</th><th style="width:15%">Status</th><th>Notes</th></tr></thead>
            <tbody>
            <?php foreach ( $rows as $row ) :
                [ $cls, $label ] = [ 'applied' => [ 'ok', 'Applied' ], 'in_progress' => [ 'rec', 'Pending' ], 'pending' => [ 'todo', 'Your action' ] ][ $row['status'] ] ?? [ 'na', 'N/A' ]; ?>
                <tr>
                    <td class="name"><?php echo esc_html( $row['setting'] ); ?></td>
                    <td><span class="pill <?php echo esc_attr( $cls ); ?>"><?php echo esc_html( $label ); ?></span></td>
                    <td><?php echo esc_html( $row['notes'] ); ?><?php if ( ! empty( $row['link'] ) || ! empty( $row['docs'] ) ) : ?><div class="go"><?php if ( ! empty( $row['link'] ) ) : ?><a href="<?php echo esc_url( $row['link'] ); ?>">Visit to configure ›</a><?php endif; ?><?php if ( ! empty( $row['link'] ) && ! empty( $row['docs'] ) ) : ?> &nbsp;·&nbsp; <?php endif; ?><?php if ( ! empty( $row['docs'] ) ) : ?><a href="<?php echo esc_url( $row['docs'] ); ?>">Public doc ›</a><?php endif; ?></div><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endforeach; ?>
    <?php if ( $data['site'] ) : ?>
        <p class="muted" style="font-size:8pt">Each "Visit to configure" link opens that setting in Defender on <?php echo esc_html( $host ); ?> (log in first). "Public doc" opens its section of the <a href="https://wpmudev.com/docs/wpmu-dev-plugins/defender/">Defender documentation</a>.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ======================= NEXT STEPS ======================= -->
<div class="sec">
    <div class="head"><?php echo wp_kses_post( $h2( 'next' ) ); ?>
    <div class="sub"><?php echo $has_next ? "Some actions could not be completed on our side because of access limits, content familiarity, or premium plugin and theme files that weren't provided before the cleanup. Please complete these to keep your site secure." : 'There is nothing you need to do. We will let you know if anything changes.'; ?></div>
    <?php if ( $pending_html ) : ?><div class="box amber cap"><h3>Pending actions</h3></div><?php endif; ?></div>

    <?php if ( $pending_html ) : /* The box title sits in .head so it can't be left alone at a page bottom; the list flows. */ ?>
        <div class="box amber body"><?php echo wp_kses_post( $pending_html ); ?></div>
    <?php endif; ?>

    <?php if ( $follow ) : ?>
        <div class="<?php echo count( $follow ) <= 10 ? 'keep' : 'flow'; /* rows carry links, so keep the usual 8 together */ ?>"><div class="head"><h3>Recommended actions</h3></div>
        <table class="grid">
            <thead><tr><th style="width:32%">Action</th><th style="width:17%">Status</th><th>Notes</th></tr></thead>
            <tbody>
            <?php foreach ( $follow as $f ) :
                [ $cls, $txt ] = [ 'required' => [ 'todo', 'Required' ], 'recommended' => [ 'rec', 'Recommended' ], 'in_place' => [ 'na', 'In place' ] ][ $f['status'] ] ?? [ 'ok', 'Done' ]; ?>
                <tr><td class="name"><?php echo esc_html( $f['label'] ); ?></td><td><span class="pill <?php echo esc_attr( $cls ); ?>"><?php echo esc_html( $txt ); ?></span></td><td><?php echo esc_html( $f['note'] ); ?><?php if ( ! empty( $f['link'] ) && 'in_place' !== $f['status'] ) : ?><div class="go"><a href="<?php echo esc_url( $f['link'] ); ?>"><?php echo 'done' === $f['status'] ? 'View on your site ›' : 'Open on your site ›'; ?></a></div><?php endif; ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>

    <?php if ( $reinstall ) : ?>
        <div class="<?php echo esc_attr( $short( $reinstall ) ); ?>"><div class="head"><h3>Reinstall these plugins &amp; themes</h3>
        <div class="sub">Premium items whose files weren't provided before cleanup. Reinstall a fresh copy from the vendor.</div></div>
        <table class="grid">
            <thead><tr><th style="width:30%">Plugin / theme</th><th style="width:25%">Folder (slug)</th><th>Why</th></tr></thead>
            <tbody><?php foreach ( $reinstall as $r ) : ?><tr><td class="name"><?php echo esc_html( $r['name'] ); ?></td><td><?php echo $r['slug'] ? '<code>' . esc_html( $r['slug'] ) . '</code>' : '—'; ?></td><td><?php echo esc_html( $r['note'] ? ucfirst( $r['note'] ) : 'Files weren\'t provided.' ); ?></td></tr><?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>

    <?php if ( $replace ) : ?>
        <div class="<?php echo esc_attr( $short( $replace ) ); ?>"><div class="head"><h3>Replace these plugins &amp; themes</h3>
        <div class="sub">No longer receiving security updates. Replacing them prevents future issues.</div></div>
        <table class="grid">
            <thead><tr><th style="width:30%">Plugin / theme</th><th style="width:25%">Folder (slug)</th><th>Why</th></tr></thead>
            <tbody><?php foreach ( $replace as $r ) : ?><tr><td class="name"><?php echo esc_html( $r['name'] ); ?></td><td><?php echo $r['slug'] ? '<code>' . esc_html( $r['slug'] ) . '</code>' : '—'; ?></td><td><?php echo esc_html( $r['note'] ? ucfirst( $r['note'] ) : 'No longer maintained.' ); ?></td></tr><?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>

    <?php if ( $delist ) : ?>
        <div class="<?php echo esc_attr( $short( $delist ) ); ?>"><div class="head"><h3>Request delisting from these blocklists</h3>
        <div class="sub">These security providers still list <?php echo esc_html( $host ?: 'your site' ); ?> as suspicious. The malware is removed, so ask each one to review the site and remove the listing.</div></div>
        <table class="grid">
            <thead><tr><th style="width:30%">Blocklist</th><th>Where to request delisting</th></tr></thead>
            <tbody><?php foreach ( $delist as $v ) : ?><tr><td class="name"><?php echo esc_html( $v['label'] ); ?></td><td><?php
                $ways = [];
                if ( ! empty( $v['url'] ) ) {
                    $ways[] = '<a href="' . esc_url( $v['url'] ) . '">' . esc_html( $clip( preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( $v['url'] ) ), 70 ) ) . '</a>';
                }
                if ( ! empty( $v['email'] ) ) {
                    $ways[] = '<a href="mailto:' . esc_attr( $v['email'] ) . '">' . esc_html( $v['email'] ) . '</a>';
                }
                echo $ways ? wp_kses_post( implode( '<br>', $ways ) ) : esc_html( 'Contact the vendor\'s support.' );
                if ( ! empty( $v['note'] ) ) {
                    echo '<div class="muted" style="font-size:7.5pt">' . esc_html( $v['note'] ) . '</div>';
                }
                ?></td></tr><?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</div>

<!-- ======================= IMPORTANT TO KNOW ======================= -->
<div class="sec">
    <div class="keep"><?php echo wp_kses_post( $h2( 'important' ) ); ?>
    <table class="two"><tr>
        <td><div class="box"><h3>Search engines (SEO)</h3><?php echo wp_kses_post( $note_html( $data['seo_note'] ?: $seo_default ) ); ?></div></td>
        <td><div class="box"><h3>Staying protected</h3><?php echo wp_kses_post( $note_html( $data['protect_note'] ?: $protect_default ) ); ?></div></td>
    </tr></table></div>

    <div class="keep">
        <h3>Resources</h3>
        <ul>
            <?php foreach ( $doc_links as $l ) : ?>
                <li><a href="<?php echo esc_url( $l['url'] ); ?>"><?php echo esc_html( $l['title'] ); ?></a></li>
            <?php endforeach; ?>
        </ul>
        <div class="closing">
            <div class="t">Thank you for trusting <?php echo esc_html( $data['brand']['name'] ); ?>.</div>
            <?php if ( $data['request_link'] && filter_var( $data['request_link'], FILTER_VALIDATE_URL ) ) : ?>
                Questions about this report? Reply on your <a href="<?php echo esc_url( $data['request_link'] ); ?>">support request</a> and our security team will help.
            <?php else : ?>
                Questions about this report? Contact our support team and we'll be happy to help.
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ( $web ) : ?></div><?php endif; ?>
<?php if ( ! empty( $data['web']['print'] ) ) : ?><script>window.addEventListener( 'load', function () { window.print(); } );</script><?php endif; ?>
</body>
</html>
