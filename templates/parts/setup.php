<?php
/**
 * Shared setup: brand colours, colour helpers, the website, date and names used on the cover and pages.
 *
 * Part of templates/report-pdf.php: included from inside Cleanup_Report_PDF::render_html(), so every variable here is
 * local to that method, not global.
 */
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- method scope, not global.
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

$host    = $data['site'] ? ( wp_parse_url( $data['site'], PHP_URL_HOST ) ?: $data['site'] ) : '';
if ( $host && false !== strpos( $host, 'xn--' ) && function_exists( 'idn_to_utf8' ) ) {
    $host = idn_to_utf8( $host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 ) ?: $host; // Readable form of punycode domains.
}
$date_ts = $data['date'] ? strtotime( $data['date'] ) : false;
$date    = date_i18n( 'j F Y', $date_ts ?: time() );
$client  = $data['client'] ?: ( $host ?: __( 'Your website', 'cleanup-report-generator' ) );
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
