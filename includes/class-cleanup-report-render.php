<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Cleanup_Report_Render {

    public static function get_data( $post_id ) {
        $m = array_map(function($a){ return $a[0]; }, get_post_meta($post_id));
        $brand = [
            'name' => get_option( 'crg_brand_name', '' ) ?: Cleanup_Report_Presets::get( 'brand_name' ),
            // A dark logo on the light cover, a white one on the dark cover; with no matching logo the cover shows the name.
            'logo' => 'dark' === get_option( 'crg_report_theme', 'light' ) ? get_option( 'crg_brand_logo', '' ) : get_option( 'crg_brand_logo_light', '' ),
            'colors' => self::colors(),
            'primary' => get_option('crg_brand_primary','#0059FF'),
            'footer'  => self::footer(),
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

        $type = Cleanup_Report_Types::of( $post_id );
        return [
            'post_id' => $post_id,
            'type'    => $type,
            'type_info' => Cleanup_Report_Types::get( $type ),
            'opt'     => 'site_optimization' === $type ? self::optimization( $post_id, $m ) : [],
            'generic' => 'generic' === Cleanup_Report_Types::get( $type )['template'] ? self::generic_sections( $post_id, Cleanup_Report_Types::get( $type ), $m ) : [],
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
            'key_finding' => self::key_finding( $post_id ),
            'site_checks' => self::site_checks( $m ),
            'delist_vendors' => Cleanup_Report_Checklists::report_blocklists( $post_id ),
            'seo_note' => $m['crg_seo_note'] ?? '',
            'protect_note' => $m['crg_protect_note'] ?? '',
            'brand' => $brand,
        ];
    }

    /**
     * A report type without a template of its own: its sections as blocks the generic template draws in order
     * (templates/types/generic-body.php). Each section: [ title, blocks ]; each block: [ kind, … ]:
     *  - facts: rows of [ label, value ] (short fields, answers, questions)
     *  - html: a label and HTML (rich text, notes, plain text)
     *  - table: a label, column labels and rows
     *  - checks: a label and checklist rows (label, answer, tone, note)
     *  - shots: a label and screenshots · code: a label, a file and the code
     * Empty fields are left out, and so is a section with nothing in it. The first section (the member and website,
     * shown on the cover) and fields marked 'report' => false are never listed. Bespoke field types need a template
     * of their own and are skipped here.
     */
    public static function generic_sections( $post_id, array $def, array $m ) {
        $out   = [];
        $first = true;
        $site  = $m['crg_site_url'] ?? '';
        foreach ( $def['sections'] as $key => $section ) {
            if ( $first ) {
                $first = false;
                continue;
            }
            $blocks = [];
            $facts  = [];
            $flush  = static function () use ( &$facts, &$blocks ) {
                if ( $facts ) {
                    $blocks[] = [ 'kind' => 'facts', 'rows' => $facts ];
                    $facts    = [];
                }
            };
            foreach ( $section['fields'] as $f ) {
                $f     = Cleanup_Report_Fields::defaults( $f );
                $value = $m[ $f['key'] ] ?? '';
                if ( ! $f['report'] ) {
                    continue;
                }
                switch ( $f['type'] ) {
                    case 'text':
                    case 'url':
                    case 'email':
                    case 'number':
                        if ( '' !== trim( (string) $value ) ) {
                            $facts[] = [ $f['label'], (string) $value ];
                        }
                        break;
                    case 'date':
                        if ( '' !== $value && strtotime( $value ) ) {
                            $facts[] = [ $f['label'], date_i18n( get_option( 'date_format' ), strtotime( $value ) ) ];
                        }
                        break;
                    case 'select':
                        $answer = $f['answers'][ $value ] ?? '';
                        $note   = trim( (string) ( $m[ $f['key'] . '_note' ] ?? '' ) );
                        if ( '' !== $answer || '' !== $note ) {
                            $facts[] = [ $f['label'], trim( $answer . ( '' !== $note ? ' – ' . $note : '' ), ' –' ) ];
                        }
                        break;
                    case 'questions':
                        foreach ( $f['questions'] as $q_key => $q ) {
                            $answer = $q[2][ $m[ 'crg_' . $q_key ] ?? '' ] ?? '';
                            $note   = trim( (string) ( $m[ 'crg_' . $q_key . '_note' ] ?? '' ) );
                            if ( '' !== $answer || '' !== $note ) {
                                $facts[] = [ $q[1], trim( $answer . ( '' !== $note ? ' – ' . $note : '' ), ' –' ) ];
                            }
                        }
                        break;
                    default:
                        $flush();
                        $block = self::generic_block( $post_id, $f, $value, $site );
                        if ( $block ) {
                            $blocks[] = $block;
                        }
                }
            }
            $flush();
            if ( $blocks ) {
                $out[] = [ 'title' => $section['title'], 'blocks' => $blocks ];
            }
        }
        return $out;
    }

    /** One block of the generic report for a field drawn on its own (see generic_sections()), or null when empty. */
    private static function generic_block( $post_id, array $f, $value, $site ) {
        switch ( $f['type'] ) {
            case 'textarea':
                return '' !== trim( (string) $value ) ? [ 'kind' => 'html', 'label' => $f['label'], 'html' => wpautop( esc_html( $value ) ) ] : null;
            case 'editor':
                return '' !== trim( wp_strip_all_tags( (string) $value ) ) ? [ 'kind' => 'html', 'label' => $f['label'], 'html' => self::note_html( $value ) ] : null;
            case 'note':
                $text = '' !== trim( wp_strip_all_tags( (string) $value ) ) ? $value : ( is_callable( $f['standard'] ?? null ) ? (string) call_user_func( $f['standard'], get_post( $post_id ) ) : '' );
                return '' !== trim( $text ) ? [ 'kind' => 'html', 'label' => $f['label'], 'html' => self::note_html( $text ) ] : null;
            case 'table':
                $cols = $f['columns'] ?? [];
                $rows = [];
                foreach ( preg_split( '/\r\n|\r|\n/', (string) $value ) as $line ) {
                    $cells = array_map( 'trim', explode( '|', $line ) );
                    if ( '' !== implode( '', $cells ) ) {
                        $rows[] = array_slice( array_pad( $cells, count( $cols ), '' ), 0, max( 1, count( $cols ) ) );
                    }
                }
                return $rows ? [ 'kind' => 'table', 'label' => $f['label'], 'columns' => wp_list_pluck( $cols, 0 ), 'rows' => $rows ] : null;
            case 'checklist':
                $rows = Cleanup_Report_Fields::checklist_for_output( Cleanup_Report_Fields::report_checklist( $post_id, $f ), $f, $site );
                return $rows ? [ 'kind' => 'checks', 'label' => $f['label'], 'rows' => $rows ] : null;
            case 'screenshots':
                $shots = get_post_meta( $post_id, $f['key'], true );
                $shots = array_values( array_filter( is_array( $shots ) ? $shots : [], static function ( $s ) { return is_array( $s ) && ! empty( $s['url'] ); } ) );
                return $shots ? [ 'kind' => 'shots', 'label' => $f['label'], 'shots' => $shots ] : null;
            case 'code':
                $code = (string) get_post_meta( $post_id, $f['key'], true );
                return '' !== trim( $code ) ? [ 'kind' => 'code', 'label' => $f['label'], 'file' => ! empty( $f['path_key'] ) ? (string) get_post_meta( $post_id, $f['path_key'], true ) : '', 'code' => $code ] : null;
        }
        return null;
    }

    /** A Website optimization report's own data (see templates/types/site-optimization.php). */
    private static function optimization( $post_id, array $m ) {
        $site    = $m['crg_site_url'] ?? '';
        $pages   = Cleanup_Report_Optimization::parse_scores( $m['crg_opt_scores'] ?? '' );
        $targets = Cleanup_Report_Optimization::report_targets( $post_id );
        $avg     = Cleanup_Report_Optimization::averages( $pages );
        $shots   = [];
        $checks  = [];
        foreach ( [ 'pre', 'post' ] as $phase ) {
            $list            = get_post_meta( $post_id, 'crg_opt_' . $phase . '_shots', true );
            $shots[ $phase ] = array_values( array_filter( is_array( $list ) ? $list : [], static function ( $s ) { return is_array( $s ) && ! empty( $s['url'] ); } ) );
            $field           = Cleanup_Report_Types::field( 'site_optimization', 'crg_opt_' . $phase . '_checks' );
            $checks[ $phase ] = $field ? Cleanup_Report_Fields::checklist_for_output( Cleanup_Report_Fields::report_checklist( $post_id, $field ), $field, $site ) : [];
        }
        return [
            'pages'     => $pages,
            'averages'  => $avg,
            'targets'   => $targets,
            'reached'   => [
                'mobile'  => Cleanup_Report_Optimization::reached( $avg, $targets, 'mobile' ),
                'desktop' => Cleanup_Report_Optimization::reached( $avg, $targets, 'desktop' ),
            ],
            'json_link' => $m['crg_opt_json_link'] ?? '',
            'checks'    => $checks,
            'shots'     => $shots,
            'steps'     => wpautop( $m['crg_opt_steps'] ?? '' ),
            'summary'   => wpautop( $m['crg_opt_summary'] ?? '' ),
            'code_path' => $m['crg_opt_code_path'] ?? '',
            'code'      => (string) get_post_meta( $post_id, 'crg_opt_code', true ),
            'info'      => $m['crg_opt_info'] ?? '',
            'faq'       => $m['crg_opt_faq'] ?? '',
        ];
    }

    /**
     * Hosting & access checks from the team's report template (Work done & verification on the edit screen; the
     * report's Verification table): key => [ question on the edit screen, label in the report, answers ].
     * Stored as crg_<key> (the answer) and crg_<key>_note. The Hub check is part of the WPMU DEV preset.
     */
    public static function site_checks_config() {
        $yes_no = [ 'yes' => __( 'Yes', 'cleanup-report-generator' ), 'no' => __( 'No', 'cleanup-report-generator' ) ];
        $checks = [
            'hosting_type' => [ __( 'Hosting Type', 'cleanup-report-generator' ), __( 'Hosting type', 'cleanup-report-generator' ), Cleanup_Report_Presets::get( 'hosting_answers' ) ],
            'backups'      => [ __( 'Are backups created before and after the cleanup?', 'cleanup-report-generator' ), __( 'Backups before and after the cleanup', 'cleanup-report-generator' ), [ 'both' => __( 'Yes, before and after', 'cleanup-report-generator' ), 'before' => __( 'Before the cleanup only', 'cleanup-report-generator' ), 'after' => __( 'After the cleanup only', 'cleanup-report-generator' ), 'none' => __( 'No', 'cleanup-report-generator' ) ] ],
            'ssh_accounts' => [ __( 'Any sFTP/SSH accounts present?', 'cleanup-report-generator' ), __( 'sFTP/SSH accounts', 'cleanup-report-generator' ), $yes_no ],
        ];
        if ( Cleanup_Report_Presets::is( 'wpmudev' ) ) {
            $checks['hub_member'] = [ __( 'HUB team member', 'cleanup-report-generator' ), __( 'Hub team member', 'cleanup-report-generator' ), $yes_no ];
        }
        return $checks;
    }

    /** The answered site checks, in order: [ label, answer, note ]. */
    private static function site_checks( array $m ) {
        $out = [];
        foreach ( self::site_checks_config() as $key => [ , $label, $answers ] ) {
            $answer = $answers[ $m[ 'crg_' . $key ] ?? '' ] ?? '';
            $note   = trim( (string) ( $m[ 'crg_' . $key . '_note' ] ?? '' ) );
            if ( '' !== $answer || '' !== $note ) {
                $out[] = [ 'label' => $label, 'answer' => $answer, 'note' => $note ];
            }
        }
        return $out;
    }

    /**
     * The summary page's key finding: the report's own line (Findings › Key finding). Reports saved before that
     * field existed use the first sentence of the infection summary, as they did then.
     */
    public static function key_finding( $post_id ) {
        if ( metadata_exists( 'post', $post_id, 'crg_key_finding' ) ) {
            return trim( (string) get_post_meta( $post_id, 'crg_key_finding', true ) );
        }
        return self::auto_key_finding( (string) get_post_meta( $post_id, 'crg_infection_summary', true ) );
    }

    /** First sentence(s) of a summary, without code, URLs or long tokens (payloads), at most about 280 characters. */
    public static function auto_key_finding( $html ) {
        $plain = preg_replace( '#<code>.*?</code>#is', '', (string) $html );
        $plain = trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $plain ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
        $plain = trim( preg_replace( [ '#https?://\S+#', '/\S{40,}/u', '/\s+([,.;:])/' ], [ '', '', '$1' ], $plain ) );
        if ( '' === $plain ) {
            return '';
        }
        return preg_match( '/^(.{60,280}?[.!?])(\s|$)/u', $plain, $m ) ? $m[1] : wp_trim_words( $plain, 40 );
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

    /** Translated names for Settings › Branding: status colour key => [ label, what it's used for ], preset key => label. */
    public static function color_labels() {
        return [
            'success' => [ __( 'Success', 'cleanup-report-generator' ), __( 'Done, Applied, Low', 'cleanup-report-generator' ) ],
            'warning' => [ __( 'Warning', 'cleanup-report-generator' ), __( 'Recommended, Medium, Pending actions box', 'cleanup-report-generator' ) ],
            'high'    => [ __( 'High', 'cleanup-report-generator' ), __( 'High severity', 'cleanup-report-generator' ) ],
            'danger'  => [ __( 'Danger', 'cleanup-report-generator' ), __( 'Required, Pending, Critical', 'cleanup-report-generator' ) ],
            'neutral' => [ __( 'Neutral', 'cleanup-report-generator' ), __( 'In place', 'cleanup-report-generator' ) ],
        ];
    }

    public static function preset_labels() {
        return [ 'subtle' => __( 'Subtle', 'cleanup-report-generator' ), 'balanced' => __( 'Balanced', 'cleanup-report-generator' ), 'bold' => __( 'Bold', 'cleanup-report-generator' ) ];
    }

    /** Footer text (Settings › Branding) as saved, or the preset's; {year} becomes the current year when a report is made. */
    public static function footer_default() {
        return Cleanup_Report_Presets::get( 'footer' );
    }

    public static function footer() {
        return str_replace( '{year}', wp_date( 'Y' ), (string) get_option( 'crg_footer_text', self::footer_default() ) );
    }

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
        $li = static function ( array $items ) { return '<li>' . implode( '</li><li>', $items ) . '</li>'; };
        return [
            // Both follow the team's report template ("It is important to know"). Plain text: "HEADING:" lines and "•" lists.
            /* translators: %s: the website's domain, e.g. example.com. Keep the line breaks, the "•" bullets and the CAPITALS: headings. */
            'seo'     => sprintf( __( "IMPORTANT: Because of the malware, your site's SEO may be affected and Google may show unwanted content for it.\n\nRECOMMENDED ACTIONS:\n• Search Google for site:%s and look for unexpected content, such as pharmaceutical results\n• If you find any, resubmit your sitemap in Google Search Console and Bing Webmaster Tools\n• Request a review in Search Console if Google shows a security warning\n\nNOTE: It takes time for search engines to update their results.", 'cleanup-report-generator' ), $host ?: 'yourdomain.com' ),
            'protect' => Cleanup_Report_Presets::is( 'wpmudev' )
                /* translators: keep the "•" bullets and line breaks. */
                ? __( "• Configure and force two-factor authentication with WPMU DEV Defender\n• Keep your plugins and theme updated with WPMU DEV Automate\n• Keep safe backups with the Snapshot plugin (not needed for sites hosted with WPMU DEV)\n• If you use shared hosting, move other sites off this account (for example to WPMU DEV hosting) to avoid cross-site infection\n• Use WPMU DEV uptime monitoring: a spike in response time is sometimes the first sign of an attack", 'cleanup-report-generator' )
                /* translators: keep the "•" bullets and line breaks. */
                : __( "• Turn on two-factor authentication for every administrator\n• Keep WordPress, your plugins and your theme updated\n• Keep regular backups stored away from the server\n• If you use shared hosting, move other sites off this account to avoid cross-site infection\n• Use uptime monitoring: a spike in response time is sometimes the first sign of an attack", 'cleanup-report-generator' ),
            'verification' => '<p>' . __( 'We performed a full malware check on the website:', 'cleanup-report-generator' ) . '</p><ul>' . $li( [
                __( 'WordPress core, plugin and theme integrity', 'cleanup-report-generator' ),
                __( 'Database scan for malware signatures', 'cleanup-report-generator' ),
                __( 'Hidden PHP files inside the uploads folder', 'cleanup-report-generator' ),
                __( 'Front-end scan with external malware scanners such as VirusTotal', 'cleanup-report-generator' ),
                __( 'Security audit of plugins and theme', 'cleanup-report-generator' ),
                __( 'Manual check of the theme\'s functions.php, header.php and footer.php', 'cleanup-report-generator' ),
                __( 'Audit of administrator accounts, including application passwords', 'cleanup-report-generator' ),
            ] ) . '</ul>',
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
