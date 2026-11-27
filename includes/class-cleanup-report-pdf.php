<?php
/**
 * PDF export.
 *
 * Renders the report template with Dompdf (bundled via Composer). If Dompdf is
 * missing or fails, falls back to the browser print view so the user can
 * "Save as PDF" instead.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Cleanup_Report_PDF {

    /**
     * Stream the report as a PDF download. Never returns.
     */
    public static function generate_pdf( $post_id ) {
        $data = Cleanup_Report_Render::get_data( $post_id );

        if ( ! self::is_available() ) {
            self::output_print_view( $data );
        }

        try {
            $dompdf = self::build( $data );
        } catch ( \Throwable $e ) {
            error_log( 'Cleanup Report Generator: PDF generation failed for report ' . $post_id . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- record why the PDF fell back to print view
            self::output_print_view( $data );
        }

        $dompdf->stream( self::filename( $data ), [ 'Attachment' => true ] );
        exit;
    }

    /**
     * Render the report into a Dompdf instance (also used by tests/WP-CLI).
     *
     * @return \CleanupReportGenerator\Vendor\Dompdf\Dompdf
     */
    public static function build( array $data ) {
        wp_raise_memory_limit( 'admin' ); // full-size screenshots are decoded in memory
        $html = self::localize_uploads( self::render_html( $data ), $data );

        $uploads = wp_get_upload_dir();

        $options = new \CleanupReportGenerator\Vendor\Dompdf\Options();
        $options->set( 'defaultFont', 'DejaVu Sans' );
        $options->set( 'isHtml5ParserEnabled', true );
        $options->set( 'isFontSubsettingEnabled', true );
        $options->set( 'isPhpEnabled', false );
        $options->set( 'isJavascriptEnabled', false );
        $options->set( 'isRemoteEnabled', true );
        $options->set( 'allowedRemoteHosts', self::allowed_hosts( $data ) );
        $options->set( 'chroot', array_filter( [ CLEANUP_REPORT_PATH, $uploads['basedir'] ?? '' ] ) );
        $options->set( 'tempDir', get_temp_dir() );
        $options->set( 'fontCache', get_temp_dir() );
        $options->set( 'dpi', 96 );

        $dompdf = new \CleanupReportGenerator\Vendor\Dompdf\Dompdf( $options );
        $dompdf->getOptions()->setHttpContext( stream_context_create( [
            // No redirects: an allowed host could otherwise bounce the request somewhere else.
            'http' => [ 'timeout' => 15, 'follow_location' => 0, 'user_agent' => 'Cleanup Report Generator/' . CLEANUP_REPORT_VERSION ],
        ] ) );
        $dompdf->loadHtml( $html, 'UTF-8' );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->addInfo( 'Title', ( $data['title'] ?: 'Cleanup Report' ) );
        $dompdf->addInfo( 'Author', $data['brand']['name'] ?? '' );
        $dompdf->render();

        self::draw_running_header_footer( $dompdf, $data );

        return $dompdf;
    }

    /**
     * Running header and footer on every page except the cover, drawn on the
     * canvas so they sit exactly in the page margins (isPhpEnabled stays off).
     */
    private static function draw_running_header_footer( \CleanupReportGenerator\Vendor\Dompdf\Dompdf $dompdf, array $data ) {
        $canvas  = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        // Roboto is registered by the template's @font-face rules during render; DejaVu Sans if it couldn't load.
        $regular = $metrics->getFont( 'Roboto' ) ?: $metrics->getFont( 'DejaVu Sans' );
        $bold    = $metrics->getFont( 'Roboto', 'bold' ) ?: $metrics->getFont( 'DejaVu Sans', 'bold' );
        $w       = $canvas->get_width();
        $h       = $canvas->get_height();
        $x0      = 18 * 72 / 25.4;          // 18 mm side margins, in points.
        $x1      = $w - $x0;
        $grey    = [ 0.50, 0.55, 0.62 ];
        $rule    = [ 0.84, 0.87, 0.91 ];
        $hex     = ltrim( sanitize_hex_color( $data['brand']['primary'] ) ?: '#0059FF', '#' );
        $primary = array_map( static function ( $c ) { return hexdec( $c ) / 255; }, str_split( $hex, 2 ) );

        $host   = $data['site'] ? ( wp_parse_url( $data['site'], PHP_URL_HOST ) ?: '' ) : '';
        $client = $host ?: $data['client']; // running header: the website, like the cover
        $right  = 'Report #' . (int) $data['post_id'] . ( $host && $data['client'] ? '  ·  Prepared for ' . $data['client'] : '' );
        $footer = (string) $data['brand']['footer'];
        $title  = 'MALWARE CLEANUP REPORT';
        $draft  = empty( $data['final'] ); // not approved yet: the watermark across every page, cover included
        $mark   = (string) ( $data['watermark'] ?? 'NOT FINAL' );

        $canvas->page_script( static function ( $page, $count, $c ) use ( $metrics, $regular, $bold, $w, $h, $x0, $x1, $grey, $rule, $primary, $client, $right, $footer, $title, $draft, $mark ) {
            if ( $draft ) {
                // Diagonal, bottom-left to top-right, centred on the page; faint so the report stays readable.
                // 92pt, or smaller for long text: at 35° the page width is the limit (keep 10% clear each side).
                $a    = deg2rad( 35 );
                $size = min( 92, 92 * 0.8 * $w / cos( $a ) / max( 1, $metrics->getTextWidth( $mark, $bold, 92 ) + 2 * mb_strlen( $mark ) ) );
                $half = ( $metrics->getTextWidth( $mark, $bold, $size ) + 2 * mb_strlen( $mark ) ) / 2;
                $c->set_opacity( 0.11 );
                $c->text( $w / 2 - $half * cos( $a ) + $size * 0.3 * sin( $a ), $h / 2 + $half * sin( $a ) - $size * 0.75 * cos( $a ), $mark, $bold, $size, [ 0.85, 0.18, 0.13 ], 0, 2, -35 );
                $c->set_opacity( 1 );
            }
            if ( 1 === $page ) {
                return; // Cover.
            }
            $fit = static function ( $text, $font, $size, $max ) use ( $metrics ) {
                while ( $text !== '' && $metrics->getTextWidth( $text, $font, $size ) > $max ) {
                    $text = rtrim( mb_substr( $text, 0, -2 ) ) . '…';
                    if ( mb_strlen( $text ) <= 2 ) {
                        break;
                    }
                }
                return $text;
            };
            // Header: title left, client right, hairline under.
            $c->text( $x0, 34, $title, $bold, 6.5, $primary, 0, 0.9 );
            $cl = $fit( $client, $regular, 7, ( $x1 - $x0 ) / 2 );
            $c->text( $x1 - $metrics->getTextWidth( $cl, $regular, 7 ), 33.5, $cl, $regular, 7, $grey );
            $c->line( $x0, 47, $x1, 47, $rule, 0.6 );
            // Footer: hairline, brand footer left, page centre, report ID right.
            $y = $h - 40;
            $c->line( $x0, $y - 8, $x1, $y - 8, $rule, 0.6 );
            $c->text( $x0, $y, $fit( $footer, $regular, 6.8, ( $x1 - $x0 ) * 0.36 ), $regular, 6.8, $grey );
            $pg = 'Page ' . $page . ' of ' . $count;
            $c->text( ( $w - $metrics->getTextWidth( $pg, $regular, 6.8 ) ) / 2, $y, $pg, $regular, 6.8, $grey );
            $r = $fit( $right, $regular, 6.8, ( $x1 - $x0 ) * 0.36 );
            $c->text( $x1 - $metrics->getTextWidth( $r, $regular, 6.8 ), $y, $r, $regular, 6.8, $grey );
        } );
    }

    /**
     * HTML for the PDF (templates/report-pdf.php), with images pre-sized to fit the page.
     *
     * @param bool $web For the browser preview: same layout, but fonts and images load by URL and the pages are
     *                  shown as sheets (see the $web block in the template).
     */
    public static function render_html( array $data, $web = false ) {
        $images = [];
        if ( ! empty( $data['brand']['logo'] ) ) {
            $logo           = self::trimmed_logo( $data['brand']['logo'] ); // empty borders cut off, so it lines up with the text
            $images['logo'] = self::image_box( $logo, 180, 40, $data );
            if ( $web && $images['logo'] ) {
                $images['logo']['src'] = $logo;
            }
        }
        foreach ( (array) $data['screenshots'] as $shot ) {
            $images[ $shot['url'] ] = self::image_box( $shot['url'], self::CONTENT_W, self::SHOT_MAX_H, $data );
            if ( $web && $images[ $shot['url'] ] ) {
                $images[ $shot['url'] ]['src'] = $shot['url'];
            }
        }
        $images = array_filter( $images );

        ob_start();
        include CLEANUP_REPORT_PATH . 'templates/report-pdf.php';
        return ob_get_clean();
    }

    /** Printable width of A4 at 96 dpi with 18 mm side margins, and max screenshot height (~105 mm). */
    const CONTENT_W  = 656;
    const SHOT_MAX_H = 400;

    /**
     * Resolve an image to something Dompdf can load and the size to draw it at,
     * scaled down (never up) to fit $max_w × $max_h. Returns null if unusable
     * (missing file, or a remote host that isn't allowed).
     */
    public static function image_box( $url, $max_w, $max_h, array $data ) {
        if ( ! $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return null;
        }
        $path = self::upload_path( $url );
        if ( ! $path && ( self::upload_url_prefix( $url ) || ! self::is_allowed_remote( $url, $data ) ) ) {
            return null; // Points into uploads but the file is gone, or a host we won't fetch from.
        }
        $size = $path ? @getimagesize( $path ) : false;
        if ( ! $size && ( $id = attachment_url_to_postid( $url ) ) ) {
            $meta = wp_get_attachment_metadata( $id );
            $size = ! empty( $meta['width'] ) ? [ $meta['width'], $meta['height'] ] : false;
        }
        $src = $path ? 'file://' . $path : $url;
        if ( ! $size || ! $size[0] || ! $size[1] ) {
            return [ 'src' => $src, 'w' => $max_w, 'h' => 0 ];
        }
        $scale = min( 1, $max_w / $size[0], $max_h / $size[1] );
        return [ 'src' => $src, 'w' => (int) round( $size[0] * $scale ), 'h' => (int) round( $size[1] * $scale ) ];
    }

    /**
     * Local file path for a URL inside this site's uploads folder, or null.
     */
    /**
     * A logo in the media library with its empty (transparent or white) border cut off: logos are often exported
     * with padding, which would push them out of line with the cover text and shrink them. The cropped copy is made
     * once and kept in uploads/crg-cache/. Returns the original URL when there's nothing to trim or it can't be done.
     */
    public static function trimmed_logo( $url ) {
        $path = self::upload_path( $url );
        $size = $path ? @getimagesize( $path ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- unreadable image: keep the original
        if ( ! $size || $size[0] * $size[1] > 4000000 || ! in_array( $size[2], [ IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF ], true ) || ! function_exists( 'imagecreatefrompng' ) ) {
            return $url;
        }
        $uploads = wp_get_upload_dir();
        $name    = 'logo-' . md5( $path . '|' . filemtime( $path ) ) . '.png';
        $file    = $uploads['basedir'] . '/crg-cache/' . $name;
        $out     = $uploads['baseurl'] . '/crg-cache/' . $name;
        if ( is_file( $file ) ) {
            return $out;
        }
        $skip = 'crg_logo_' . md5( $name ); // set when this logo has nothing to trim, so it isn't scanned again
        if ( get_transient( $skip ) ) {
            return $url;
        }
        $img = [ IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_GIF => 'imagecreatefromgif' ][ $size[2] ]( $path );
        if ( ! $img ) {
            return $url;
        }
        [ $w, $h ] = [ imagesx( $img ), imagesy( $img ) ];
        [ $x0, $y0, $x1, $y1 ] = [ $w, $h, -1, -1 ];
        // A transparent corner means the border is transparency (white logos stay intact); otherwise it's white.
        $clear = imagecolorsforindex( $img, imagecolorat( $img, 0, 0 ) )['alpha'] >= 120;
        for ( $y = 0; $y < $h; $y++ ) {
            for ( $x = 0; $x < $w; $x++ ) {
                $c = imagecolorsforindex( $img, imagecolorat( $img, $x, $y ) );
                if ( $clear ? $c['alpha'] < 120 : ( $c['red'] < 245 || $c['green'] < 245 || $c['blue'] < 245 ) ) {
                    $x0 = min( $x0, $x );
                    $x1 = max( $x1, $x );
                    $y0 = min( $y0, $y );
                    $y1 = max( $y1, $y );
                }
            }
        }
        if ( PHP_VERSION_ID < 80000 ) {
            imagedestroy( $img ); // a no-op since PHP 8.0 (deprecated in 8.5): the image is freed when $img goes out of scope
        }
        if ( $x1 < 0 || ( 0 === $x0 && 0 === $y0 && $w - 1 === $x1 && $h - 1 === $y1 ) ) {
            set_transient( $skip, 1, MONTH_IN_SECONDS );
            return $url; // blank, or no border to trim
        }
        $editor = wp_get_image_editor( $path );
        if ( is_wp_error( $editor ) || is_wp_error( $editor->crop( $x0, $y0, $x1 - $x0 + 1, $y1 - $y0 + 1 ) ) ) {
            set_transient( $skip, 1, DAY_IN_SECONDS );
            return $url;
        }
        wp_mkdir_p( dirname( $file ) );
        return is_wp_error( $editor->save( $file, 'image/png' ) ) ? $url : $out;
    }

    private static function upload_path( $url ) {
        $rel = self::upload_url_prefix( $url );
        if ( null === $rel ) {
            return null;
        }
        $uploads = wp_get_upload_dir();
        $path    = wp_normalize_path( realpath( $uploads['basedir'] . '/' . $rel ) ?: '' );
        $root    = wp_normalize_path( realpath( $uploads['basedir'] ) ?: '' );
        $type    = wp_check_filetype( $path );
        if ( $path && $root && 0 === strpos( $path, $root . '/' ) && is_file( $path )
            && $type['type'] && 0 === strpos( $type['type'], 'image/' ) ) {
            return $path;
        }
        return null;
    }

    /**
     * If $url is under this site's uploads URL, the path relative to it; else null.
     */
    private static function upload_url_prefix( $url ) {
        $uploads = wp_get_upload_dir();
        if ( empty( $uploads['baseurl'] ) ) {
            return null;
        }
        $url = strtok( html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), '?#' );
        foreach ( [ 'http', 'https' ] as $scheme ) {
            $base = set_url_scheme( $uploads['baseurl'], $scheme ) . '/';
            if ( 0 === strpos( set_url_scheme( $url, $scheme ), $base ) ) {
                return rawurldecode( substr( set_url_scheme( $url, $scheme ), strlen( $base ) ) );
            }
        }
        return null;
    }

    public static function is_available() {
        return class_exists( '\CleanupReportGenerator\Vendor\Dompdf\Dompdf' );
    }

    public static function filename( array $data ) {
        // Plain ASCII slug: quotes, dashes or CJK in a download name break some browsers and mail clients.
        $name = wp_strip_all_tags( preg_replace( '/^www\./', '', (string) wp_parse_url( $data['site'], PHP_URL_HOST ) ) ?: $data['client'] );
        $slug = trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( remove_accents( $name ) ) ), '-' );
        $slug = substr( $slug, 0, 60 ) ?: 'report-' . (int) $data['post_id'];
        $ts   = $data['date'] ? strtotime( $data['date'] ) : false;
        return $slug . '-cleanup-report-' . gmdate( 'Y-m-d', $ts ?: time() ) . '.pdf';
    }

    /**
     * Point images in this site's uploads folder at the file on disk, so Dompdf
     * reads them directly instead of making an HTTP request back to the site
     * (slow, and blocked on sites behind basic auth or a firewall).
     */
    private static function localize_uploads( $html, array $data ) {
        // Images pasted into report content: use the local file if it's in uploads,
        // keep it if it's on an allowed host, otherwise drop the tag rather than
        // print a broken-image box.
        return preg_replace_callback(
            '#<img\b[^>]*\bsrc=(["\'])([^"\']*)\1[^>]*>#i',
            function ( $m ) use ( $data ) {
                $src = html_entity_decode( $m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
                if ( 0 === strpos( $src, 'file://' . wp_normalize_path( CLEANUP_REPORT_PATH ) ) || 0 === strpos( $src, 'file://' . wp_normalize_path( wp_get_upload_dir()['basedir'] ) ) ) {
                    return $m[0]; // Already resolved by render_html().
                }
                if ( $path = self::upload_path( $src ) ) {
                    return str_replace( $m[2], esc_attr( 'file://' . $path ), $m[0] );
                }
                return self::is_allowed_remote( $src, $data ) && ! self::upload_url_prefix( $src ) ? $m[0] : '';
            },
            $html
        );
    }

    /**
     * Hosts Dompdf may fetch images from: this site and the brand logo's host
     * (set by an admin). Report authors can't widen this, so a pasted URL like
     * http://169.254.169.254/ can't make the server call internal addresses.
     * Extend with the `cleanup_report_pdf_allowed_hosts` filter.
     */
    private static function allowed_hosts( array $data ) {
        $urls  = [ home_url(), site_url(), $data['brand']['logo'] ?? '' ];
        $hosts = [];
        foreach ( $urls as $url ) {
            $host = $url ? wp_parse_url( $url, PHP_URL_HOST ) : '';
            if ( $host ) {
                $hosts[] = strtolower( $host );
            }
        }
        $hosts = (array) apply_filters( 'cleanup_report_pdf_allowed_hosts', $hosts, $data );
        return array_values( array_unique( array_map( 'strtolower', array_filter( $hosts, 'is_string' ) ) ) );
    }

    /**
     * Whether a remote image URL is on an allowed host (local uploads are always fine).
     */
    private static function is_allowed_remote( $url, array $data ) {
        $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        return $host && in_array( $host, self::allowed_hosts( $data ), true )
            && in_array( wp_parse_url( $url, PHP_URL_SCHEME ), [ 'http', 'https' ], true );
    }

    /**
     * Fallback when Dompdf is unavailable: the browser preview, which opens the print dialog so the user can
     * save it as a PDF.
     */
    private static function output_print_view( array $data ) {
        status_header( 200 );
        nocache_headers();
        header( 'Content-Type: text/html; charset=utf-8' );
        $data['web'] = [ 'print' => true ];
        echo self::render_html( $data, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the template escapes its output
        exit;
    }

    /**
     * Warn on the Reports list screen if PDF export can't run.
     */
    public static function show_library_notice() {
        global $pagenow;
        if ( 'edit.php' !== $pagenow || 'cleanup_report' !== sanitize_key( wp_unslash( $_GET['post_type'] ?? '' ) ) || self::is_available() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only decides whether to show a notice
            return;
        }
        echo '<div class="notice notice-warning"><p><strong>PDF export unavailable:</strong> the Dompdf library is missing, so "Export PDF" opens the print view instead. Run <code>composer install --no-dev</code> in the plugin folder to enable it.</p></div>';
    }
}
