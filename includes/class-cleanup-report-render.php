<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Cleanup_Report_Render {

    public static function get_data( $post_id ) {
        $m = array_map(function($a){ return $a[0]; }, get_post_meta($post_id));
        $brand = [
            'name' => get_option('crg_brand_name','WPMU DEV Services'),
            // A dark logo on the light cover, a white one on the dark cover; with no matching logo the cover shows the name.
            'logo' => 'dark' === get_option( 'crg_report_theme', 'light' ) ? get_option( 'crg_brand_logo', '' ) : get_option( 'crg_brand_logo_light', '' ),
            'colors' => self::colors(),
            'primary' => get_option('crg_brand_primary','#0059FF'),
            'footer'  => get_option('crg_footer_text','© ' . wp_date('Y') . ' WPMU DEV Services'),
            'theme'   => 'dark' === get_option( 'crg_report_theme', 'light' ) ? 'dark' : 'light',
            'glow'    => self::cover_glow( get_option( 'crg_cover_glow', 100 ) ),
        ];
        
        // Handle screenshots (new format)
        $screenshots = get_post_meta($post_id, 'crg_screenshots', true);
        if (!is_array($screenshots)) {
            // Migrate old format if exists
            $screenshots = [];
            if (!empty($m['crg_ss_before'])) {
                $screenshots[] = [
                    'url' => $m['crg_ss_before'],
                    'title' => 'Security Scan - Before Cleaning',
                    'type' => 'before'
                ];
            }
            if (!empty($m['crg_ss_after'])) {
                $screenshots[] = [
                    'url' => $m['crg_ss_after'],
                    'title' => 'Security Scan - After Cleaning',
                    'type' => 'after'
                ];
            }
        }
        
        // Filter out empty screenshots
        $screenshots = array_filter($screenshots, function($screenshot) {
            return !empty($screenshot['url']);
        });
        
        // Parse actions
        $actions_raw = $m['crg_actions'] ?? '';
        $lines = preg_split('/\r\n|\r|\n/', $actions_raw);
        $actions = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line) continue;
            if (preg_match('/^\[(CRITICAL|RECOMMENDED|OPTIONAL)\]\s*(.*)$/i', $line, $matches)) {
                $actions[] = ['level'=>strtoupper($matches[1]), 'text'=>$matches[2]];
            } else {
                $actions[] = ['level'=>'OPTIONAL', 'text'=>preg_replace('/^\[(.*?)\]\s*/','',$line)];
            }
        }
        
        // Vulnerable / outdated components: one per line, "Name | Installed version | Issue | Severity".
        $components = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) ($m['crg_components'] ?? '')) as $line) {
            $cols = array_map('trim', explode('|', $line));
            if ($cols[0] === '') continue;
            $components[] = [
                'name'     => $cols[0],
                'version'  => $cols[1] ?? '',
                'issue'    => $cols[2] ?? '',
                'severity' => self::severity( $cols[3] ?? '' ) ?: 'medium',
            ];
        }

        return [
            'post_id' => $post_id,
            'final'   => Cleanup_Report_Admin::is_final( $post_id ), // approved or completed; otherwise watermarked
            'watermark' => Cleanup_Report_Admin::watermark(),
            'components' => $components,
            'improvements' => wpautop($m['crg_improvements'] ?? ''),
            'additional_sites' => $m['crg_additional_sites'] ?? '',
            'delist' => $m['crg_delist'] ?? '',
            'title' => get_the_title($post_id),
            'client' => $m['crg_client_name'] ?? '',
            'site' => $m['crg_site_url'] ?? '',
            'date' => $m['crg_report_date'] ?? '',
            'request_link' => $m['crg_request_link'] ?? '',
            // Legacy screenshot support
            'ss_before' => $m['crg_ss_before'] ?? '',
            'ss_after' => $m['crg_ss_after'] ?? '',
            // New multiple screenshots support
            'screenshots' => $screenshots,
            // WYSIWYG content (apply wpautop for proper formatting)
            'infection_summary' => wpautop($m['crg_infection_summary'] ?? ''),
            's_critical' => max(0, intval($m['crg_summary_critical'] ?? 0)),
            's_medium' => max(0, intval($m['crg_summary_medium'] ?? 0)),
            's_low' => max(0, intval($m['crg_summary_low'] ?? 0)),
            'highlights' => wpautop($m['crg_highlights'] ?? ''),
            'actions' => $actions,
            'reinstall_list' => wpautop($m['crg_reinstall_list'] ?? ''),
            'replace_list' => wpautop($m['crg_replace_list'] ?? ''),
            // Follow-ups: [ label, status (done | required | recommended), note ], wording as saved with the report.
            'followups' => Cleanup_Report_Checklists::followups_for_output( Cleanup_Report_Checklists::report_followups( $post_id ), $m['crg_site_url'] ?? '' ),
            'verification_summary' => wpautop($m['crg_verification_summary'] ?? ''),
            'defender_checklist' => Cleanup_Report_Checklists::defender_for_output( Cleanup_Report_Checklists::report_defender( $post_id ), $m['crg_site_url'] ?? '' ),
            'documentation_links' => $m['crg_documentation_links'] ?? '',
            'pending_actions' => $m['crg_pending_actions'] ?? '',
            'seo_note' => $m['crg_seo_note'] ?? '',
            'protect_note' => $m['crg_protect_note'] ?? '',
            'brand' => $brand,
        ];
    }

    const SEVERITIES = [ 'critical', 'high', 'medium', 'low' ];

    /** Status colours (Settings › Branding): key => [ label, what it's used for, default ]. */
    const COLORS = [
        'success' => [ 'Success', 'Done, Applied, Low', '#067647' ],
        'warning' => [ 'Warning', 'Recommended, Medium, Pending actions box', '#FDB022' ],
        'high'    => [ 'High', 'High severity', '#C4320A' ],
        'danger'  => [ 'Danger', 'Required, Pending, Critical', '#D92D20' ],
        'neutral' => [ 'Neutral', 'In place', '#667085' ],
    ];

    /**
     * Colour presets (Settings › Branding): one click fills the primary colour, the status colours and the cover gradient;
     * nothing changes until the settings are saved. Bold is the plugin's original look (the defaults above).
     */
    const PRESETS = [
        'subtle'   => [ 'Subtle', [ 'primary' => '#3366CC', 'success' => '#ABEFC6', 'warning' => '#FEDF89', 'high' => '#F9DBAF', 'danger' => '#FECDCA', 'neutral' => '#EAECF0', 'glow' => 60 ] ],
        'balanced' => [ 'Balanced', [ 'primary' => '#2F64D6', 'success' => '#47CD89', 'warning' => '#FDC94B', 'high' => '#F79A5C', 'danger' => '#F97066', 'neutral' => '#D0D5DD', 'glow' => 80 ] ],
        'bold'     => [ 'Bold', [ 'primary' => '#0059FF', 'success' => '#067647', 'warning' => '#FDB022', 'high' => '#C4320A', 'danger' => '#D92D20', 'neutral' => '#667085', 'glow' => 100 ] ],
    ];

    /** Cover gradient strength (Settings › Branding) as a whole percentage, 0–100; 100 when unset or not a number. */
    public static function cover_glow( $value ) {
        return is_numeric( $value ) ? max( 0, min( 100, (int) round( (float) $value ) ) ) : 100;
    }

    /** The status colours in use: key => hex. */
    public static function colors() {
        $out = [];
        foreach ( self::COLORS as $key => $c ) {
            $out[ $key ] = sanitize_hex_color( get_option( 'crg_color_' . $key, '' ) ) ?: $c[2];
        }
        return $out;
    }

    /**
     * A typed severity → critical | high | medium | low, accepting common spellings ("Crit", "moderate", "minor").
     * '' when it isn't recognised. Mirrors severity() in admin.js.
     */
    public static function severity( $text ) {
        $map = [
            'critical' => [ 'critical', 'crit', 'severe', 'urgent' ],
            'high'     => [ 'high', 'hi' ],
            'medium'   => [ 'medium', 'med', 'mid', 'moderate' ],
            'low'      => [ 'low', 'lo', 'minor', 'info', 'informational' ],
        ];
        $text = strtolower( trim( (string) $text ) );
        foreach ( $map as $level => $words ) {
            if ( in_array( $text, $words, true ) ) {
                return $level;
            }
        }
        return '';
    }

    /**
     * Plain-text note → paragraphs, bullet/numbered lists ("•", "-", "1.") and bold "HEADINGS:".
     */
    public static function text_to_html( $text ) {
        $text = trim( wp_strip_all_tags( (string) $text ) );
        if ( '' === $text ) {
            return '';
        }
        $out = '';
        foreach ( preg_split( '/\n\s*\n/', str_replace( "\r", '', $text ) ) as $para ) {
            $items    = [];
            $numbered = false;
            $flush    = static function () use ( &$items, &$numbered, &$out ) {
                if ( $items ) {
                    $tag  = $numbered ? 'ol' : 'ul';
                    $out .= "<$tag>" . implode( '', array_map( static function ( $i ) { return '<li>' . esc_html( $i ) . '</li>'; }, $items ) ) . "</$tag>";
                }
                $items    = [];
                $numbered = false;
            };
            foreach ( explode( "\n", $para ) as $line ) {
                $line = trim( $line );
                if ( '' === $line ) {
                    continue;
                }
                if ( preg_match( '/^(?:[•\-\*]|(\d+)[.)])\s*(.+)$/u', $line, $m ) ) {
                    $items[]  = $m[2];
                    $numbered = $numbered || '' !== $m[1];
                    continue;
                }
                $flush();
                if ( preg_match( '/^([A-Z][A-Z \/&\-]+:)\s*(.*)$/', $line, $m ) ) {
                    $out .= '<p><strong>' . esc_html( ucfirst( strtolower( rtrim( $m[1], ':' ) ) ) ) . ':</strong> ' . esc_html( $m[2] ) . '</p>';
                } else {
                    $out .= '<p>' . esc_html( $line ) . '</p>';
                }
            }
            $flush();
        }
        return $out;
    }

    /**
     * A note that may be editor HTML (2.1+) or plain text (older reports, the standard texts) → HTML.
     */
    public static function note_html( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) {
            return '';
        }
        return preg_match( '/<(p|ul|ol|li|a|strong|em|br|h[1-6])\b/i', $value ) ? wpautop( $value ) : self::text_to_html( $value );
    }

    /**
     * Client-guidance texts used when a report leaves them empty (also shown as placeholders on the edit screen).
     */
    public static function default_texts( $host = '' ) {
        return [
            'seo'     => "IMPORTANT: Because the site was infected, search results may show spam pages or warnings for a while.\n\nRECOMMENDED ACTIONS:\n• Search Google for site:" . ( $host ?: 'yourdomain.com' ) . " and look for pages you don't recognise\n• Resubmit your sitemap in Google Search Console and Bing Webmaster Tools\n• Request a review in Search Console if Google shows a security warning\n\nTIMELINE: Search engines usually update their results within 1–4 weeks.",
            'protect' => "• Turn on two-factor authentication for every administrator (Defender)\n• Keep WordPress core, plugins and theme up to date (Automate)\n• Keep the scheduled malware scan enabled and act on its alerts\n• Use strong, unique passwords and remove accounts you no longer need\n• Keep regular off-site backups",
            'verification' => '<p>We performed a full malware check on the website:</p><ul><li>WordPress core, plugin and theme integrity</li><li>Database scan for malware signatures</li><li>Hidden PHP files inside the uploads folder</li><li>Front-end scan with external malware scanners such as VirusTotal</li><li>Security audit of plugins and theme</li><li>Manual check of the theme\'s functions.php, header.php and footer.php</li><li>Audit of administrator accounts, including application passwords</li></ul>',
        ];
    }

    /**
     * The in-browser preview: the PDF's own template, shown as pages, with a bar to download the PDF or go back.
     */
    public static function render_report_page( $post_id ) {
        $data        = self::get_data( $post_id );
        $data['web'] = [
            'pdf'  => Cleanup_Report_Admin::can_download( $post_id ) ? wp_nonce_url( admin_url( 'admin-post.php?action=crg_export_pdf&post=' . $post_id ), 'crg_pdf_' . $post_id ) : '',
            'edit' => get_edit_post_link( $post_id, 'raw' ),
        ];
        status_header( 200 );
        nocache_headers();
        header( 'Content-Type: text/html; charset=utf-8' );
        echo Cleanup_Report_PDF::render_html( $data, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the template escapes its output
    }
}
