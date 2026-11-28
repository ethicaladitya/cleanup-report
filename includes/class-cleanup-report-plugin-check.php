<?php
/**
 * "Check plugins & themes" on the report form: is each plugin/theme on the member's site still maintained, and
 * does the installed version have known vulnerabilities?
 *
 * Input: the output of `wp plugin list` / `wp theme list` from the member's site (CSV or the default table).
 * Sources, both free and keyless:
 *  - WordPress.org plugin/theme API: latest version, last release, "Tested up to", closed (removed) status.
 *  - WPVulnerability.net: known vulnerabilities for free and many premium plugins/themes.
 * Every item checked is added to the shared plugin list (Cleanup_Report_Plugin_Registry, Reports › Settings › Plugin list), where
 * WordPress.org items are kept up to date automatically and staff mark premium/custom ones once. Only plugin/theme
 * folder names are sent to these services.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Cleanup_Report_Plugin_Check {

    const STALE_DAYS     = 730;  // no release for 2 years = not maintained
    const TESTED_BEHIND  = 3;    // "Tested up to" 3+ major WordPress releases behind = not maintained
    const CACHE_TTL      = 43200; // 12 hours
    const FAIL_TTL       = 600;
    const MAX_ITEMS      = 150;
    const SEQUENTIAL_BUDGET = 20; // seconds of one-by-one requests per check (when the site shapes HTTP requests)

    const COMMAND = 'wp plugin list --fields=name,title,version,update_version,status --format=csv; echo THEMES; wp theme list --fields=name,title,version,update_version,status --format=csv';

    public static function init() {
        add_action( 'wp_ajax_crg_check_plugins', [ __CLASS__, 'ajax' ] );
    }

    /* ------------------------------------------------------------------ input */

    /**
     * Pasted `wp plugin list` / `wp theme list` output (CSV or table; a line "THEMES" switches to themes) →
     * [ type, slug, title, version, update_version ]. Must-use plugins and drop-ins are skipped.
     */
    public static function parse( $text ) {
        $items = [];
        $type  = 'plugin';
        $cols  = null;
        foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
            $line = trim( $line );
            if ( '' === $line || preg_match( '/^\+[-+]+\+$/', $line ) ) {
                continue;
            }
            if ( preg_match( '/^#*\s*themes?:?$/i', $line ) ) {
                $type = 'theme';
                $cols = null;
                continue;
            }
            if ( preg_match( '/^#*\s*plugins?:?$/i', $line ) ) {
                $type = 'plugin';
                $cols = null;
                continue;
            }
            $cells = false !== strpos( $line, '|' )
                ? array_map( 'trim', explode( '|', trim( $line, '|' ) ) )
                : array_map( 'trim', str_getcsv( $line, ',', '"', '\\' ) );
            if ( 1 === count( $cells ) ) {
                $cells = preg_split( '/\s+/', $line ); // "slug 1.2.3"
            }
            if ( 'name' === strtolower( $cells[0] ) ) {
                $cols = array_flip( array_map( 'strtolower', $cells ) );
                continue;
            }
            $col = static function ( $name, $fallback ) use ( $cells, $cols ) {
                $i = $cols[ $name ] ?? $fallback;
                return null === $i ? '' : trim( (string) ( $cells[ $i ] ?? '' ) );
            };
            $slug = preg_replace( '/[^A-Za-z0-9_.\-]/', '', $col( 'name', 0 ) );
            $stat = strtolower( $col( 'status', null ) );
            if ( '' === $slug || in_array( $stat, [ 'must-use', 'dropin' ], true ) || preg_match( '/\.php$/', $slug ) ) {
                continue;
            }
            $items[ $type . ':' . $slug ] = [
                'type'    => $type,
                'slug'    => $slug,
                'title'   => $col( 'title', null ),
                'version' => $col( 'version', $cols ? null : 1 ),
                'update'  => $col( 'update_version', null ),
            ];
        }
        return array_slice( array_values( $items ), 0, self::MAX_ITEMS );
    }

    /* ------------------------------------------------------------------ lookups */

    public static function org_url( $type, $slug ) {
        $fields = [ 'sections' => 0, 'description' => 0, 'versions' => 0, 'reviews' => 0, 'screenshots' => 0, 'banners' => 0, 'icons' => 0, 'contributors' => 0, 'tags' => 0, 'ratings' => 0, 'donate_link' => 0 ];
        return 'https://api.wordpress.org/' . $type . 's/info/1.2/?' . http_build_query( [
            'action'  => $type . '_information',
            'request' => [ 'slug' => strtolower( $slug ), 'fields' => $fields ],
        ] );
    }

    public static function vuln_url( $type, $slug ) {
        return 'https://www.wpvulnerability.net/' . $type . '/' . rawurlencode( strtolower( $slug ) ) . '/';
    }

    /**
     * GET several JSON URLs at once (cached). Returns url => decoded array, or null when the service failed.
     * WordPress.org answers 404 with a JSON body for closed and unknown slugs; that body is kept.
     */
    public static function fetch( array $urls ) {
        $out  = [];
        $todo = [];
        foreach ( array_unique( $urls ) as $url ) {
            $hit = get_transient( 'crg_pc_' . md5( $url ) );
            if ( false === $hit ) {
                $todo[] = $url;
            } else {
                $out[ $url ] = isset( $hit['_fail'] ) ? null : $hit;
            }
        }
        // A plain user agent: WordPress's default one names this site, which these services don't need.
        $headers = [ 'User-Agent' => 'Handover/' . CLEANUP_REPORT_VERSION, 'Accept' => 'application/json' ];
        $bodies  = [];
        // In parallel when nothing on this site shapes outgoing requests; otherwise through the WordPress HTTP API, so
        // WP_HTTP_BLOCK_EXTERNAL, a proxy and pre_http_request filters apply.
        $parallel = class_exists( '\WpOrg\Requests\Requests' ) && ! ( defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && WP_HTTP_BLOCK_EXTERNAL ) && ! ( defined( 'WP_PROXY_HOST' ) && WP_PROXY_HOST ) && ! has_filter( 'pre_http_request' ) && ! has_filter( 'http_request_args' );
        if ( $todo && $parallel ) {
            // 20 at a time: a full check is a few hundred URLs, and all at once invites rate limiting.
            foreach ( array_chunk( $todo, 20 ) as $chunk ) {
                $requests = [];
                foreach ( $chunk as $url ) {
                    $requests[ $url ] = [ 'url' => $url, 'headers' => $headers, 'type' => 'GET' ];
                }
                $responses = \WpOrg\Requests\Requests::request_multiple( $requests, [ 'timeout' => 15, 'connect_timeout' => 8 ] );
                foreach ( $responses as $url => $response ) {
                    $bodies[ $url ] = $response instanceof \WpOrg\Requests\Response && in_array( $response->status_code, [ 200, 404 ], true ) ? $response->body : null;
                }
            }
        } else {
            // One by one through the WordPress HTTP API: short timeouts and a time budget, so a long list can't run into
            // the request's own time limit. What doesn't fit isn't cached: checking again picks up where this stopped.
            $deadline = microtime( true ) + self::SEQUENTIAL_BUDGET;
            foreach ( $todo as $i => $url ) {
                if ( microtime( true ) > $deadline ) {
                    foreach ( array_slice( $todo, $i ) as $late ) {
                        $out[ $late ] = null;
                    }
                    $todo = array_slice( $todo, 0, $i );
                    break;
                }
                $response       = wp_remote_get( $url, [ 'timeout' => 5, 'headers' => $headers ] );
                $code           = wp_remote_retrieve_response_code( $response );
                $bodies[ $url ] = in_array( $code, [ 200, 404 ], true ) ? wp_remote_retrieve_body( $response ) : null;
            }
        }
        foreach ( $todo as $url ) {
            $data = isset( $bodies[ $url ] ) ? json_decode( $bodies[ $url ], true ) : null;
            $ok   = is_array( $data );
            $data = $ok ? self::slim( $url, $data ) : null;
            set_transient( 'crg_pc_' . md5( $url ), $ok ? $data : [ '_fail' => 1 ], $ok ? self::CACHE_TTL : self::FAIL_TTL );
            $out[ $url ] = $ok ? $data : null;
        }
        return $out;
    }

    /**
     * Only the fields the check uses, so the 12-hour cache stays small: vulnerability lists of big plugins run to
     * hundreds of KB, and WordPress.org answers carry more than needed.
     */
    private static function slim( $url, array $data ) {
        if ( false !== strpos( $url, 'wpvulnerability.net' ) ) {
            $vulns = [];
            foreach ( (array) ( $data['data']['vulnerability'] ?? [] ) as $v ) {
                $vulns[] = [
                    'operator' => array_intersect_key( (array) ( $v['operator'] ?? [] ), array_flip( [ 'min_version', 'min_operator', 'max_version', 'max_operator', 'unfixed' ] ) ),
                    'impact'   => [ 'cvss' => [ 'score' => $v['impact']['cvss']['score'] ?? null ] ],
                ];
            }
            return [ 'data' => [ 'vulnerability' => $vulns ] ];
        }
        if ( false !== strpos( $url, '/info/' ) ) {
            return array_intersect_key( $data, array_flip( [ 'error', 'closed', 'closed_date', 'reason', 'name', 'slug', 'version', 'last_updated', 'tested', 'requires', 'requires_php' ] ) );
        }
        return $data;
    }

    /** "1.2.3-beta" → comparable string for version_compare(). */
    private static function v( $version ) {
        return preg_replace( '/[^0-9.]/', '', (string) $version ) ?: '0';
    }

    /** Does a WPVulnerability "operator" range include the installed version? */
    private static function affects( $installed, array $op ) {
        $v = self::v( $installed );
        if ( ! empty( $op['min_version'] ) ) {
            $cmp = 'gt' === ( $op['min_operator'] ?? '' ) ? '>' : '>=';
            if ( ! version_compare( $v, self::v( $op['min_version'] ), $cmp ) ) {
                return false;
            }
        }
        if ( ! empty( $op['max_version'] ) ) {
            $cmp = 'le' === ( $op['max_operator'] ?? '' ) ? '<=' : '<';
            return version_compare( $v, self::v( $op['max_version'] ), $cmp );
        }
        return true; // no upper bound: every version
    }

    /* ------------------------------------------------------------------ the check */

    /**
     * @param array $items from parse()
     * @return array[] one row per item: what it is, its status, why, and what to put in the report.
     */
    public static function check( array $items ) {
        $premium = Cleanup_Report_Plugin_Registry::decisions(); // items staff have marked (premium/custom): never looked up on WordPress.org
        $urls = [];
        foreach ( $items as $it ) {
            if ( ! isset( $premium[ $it['type'] . ':' . strtolower( $it['slug'] ) ] ) ) {
                $urls[] = self::org_url( $it['type'], $it['slug'] );
            }
            $urls[] = self::vuln_url( $it['type'], $it['slug'] );
            if ( false !== strpos( $it['slug'], '_' ) ) {
                $urls[] = self::vuln_url( $it['type'], str_replace( '_', '-', $it['slug'] ) ); // WPVulnerability uses hyphens (js-composer)
            }
        }
        $data = self::fetch( $urls );

        $wp = self::current_wp();

        $rows = [];
        foreach ( $items as $it ) {
            $pkey = $it['type'] . ':' . strtolower( $it['slug'] );
            $row  = [
                'type'      => $it['type'],
                'slug'      => $it['slug'],
                'name'      => $it['title'] ?: $it['slug'],
                'installed' => $it['version'],
                'latest'    => $it['update'],
                'source'    => 'unknown',
                'status'    => 'ok',
                'reasons'   => [],
                'link'      => '',
                'vulns'     => [ 'count' => 0, 'unfixed' => 0, 'fixed_in' => '', 'score' => 0, 'open' => 0 ],
                'last_release' => '',
                'errors'    => [],
            ];

            // 1. Maintained?
            if ( isset( $premium[ $pkey ] ) ) {
                $p             = $premium[ $pkey ];
                $row['source'] = 'premium';
                $row['name']   = $it['title'] ?: $p['name'];
                $row['link']   = $p['vendor'];
                if ( 'abandoned' === $p['decision'] ) {
                    $row['status']    = 'abandoned';
                    if ( $p['note'] ) {
                        /* translators: %s: the staff note on the plugin list */
                        $row['reasons'][] = sprintf( __( 'Marked as no longer maintained in your plugin list: %s', 'cleanup-report-generator' ), $p['note'] );
                    } else {
                        $row['reasons'][] = __( 'Marked as no longer maintained in your plugin list', 'cleanup-report-generator' );
                    }
                }
            } else {
                $m = self::maintenance( $it, $data[ self::org_url( $it['type'], $it['slug'] ) ] ?? null, $wp );
                $row['source']       = $m['source'];
                $row['link']         = $m['link'];
                $row['latest']       = $m['latest'] ?: $row['latest'];
                $row['last_release'] = $m['last_release'];
                if ( in_array( $m['status'], [ 'closed', 'abandoned' ], true ) ) {
                    $row['status'] = $m['status'];
                }
                if ( $m['reason'] ) {
                    $row['reasons'][] = $m['reason'];
                }
                if ( $m['error'] ) {
                    $row['errors'][] = $m['error'];
                }
            }

            // 2. Known vulnerabilities in the installed version.
            $vdata = $data[ self::vuln_url( $it['type'], $it['slug'] ) ] ?? null;
            if ( false !== strpos( $it['slug'], '_' ) && empty( $vdata['data']['vulnerability'] ) ) {
                $vdata = $data[ self::vuln_url( $it['type'], str_replace( '_', '-', $it['slug'] ) ) ] ?? $vdata;
            }
            if ( null === $vdata ) {
                $row['errors'][] = __( 'WPVulnerability didn\'t answer', 'cleanup-report-generator' );
            }
            $row['vulns']['open'] = self::open_vulns( $vdata );
            foreach ( (array) ( $vdata['data']['vulnerability'] ?? [] ) as $vuln ) {
                $op = (array) ( $vuln['operator'] ?? [] );
                if ( '' === $it['version'] || ! self::affects( $it['version'], $op ) ) {
                    continue;
                }
                $row['vulns']['count']++;
                if ( '1' === (string) ( $op['unfixed'] ?? '' ) ) {
                    $row['vulns']['unfixed']++;
                } elseif ( ! empty( $op['max_version'] ) && version_compare( self::v( $op['max_version'] ), self::v( $row['vulns']['fixed_in'] ?: '0' ), '>' ) ) {
                    $row['vulns']['fixed_in'] = $op['max_version'];
                }
                $row['vulns']['score'] = max( $row['vulns']['score'], (float) ( $vuln['impact']['cvss']['score'] ?? 0 ) );
            }
            if ( $row['vulns']['count'] ) {
                $n      = $row['vulns']['count'];
                /* translators: 1: number of vulnerabilities, 2: installed version */
                $reason = sprintf( _n( '%1$d known vulnerability in %2$s', '%1$d known vulnerabilities in %2$s', $n, 'cleanup-report-generator' ), $n, $it['version'] );
                if ( $row['vulns']['unfixed'] ) {
                    /* translators: 1: "N known vulnerabilities in X", 2: how many of them have no fix */
                    $reason = sprintf( __( '%1$s (%2$d with no fix yet)', 'cleanup-report-generator' ), $reason, $row['vulns']['unfixed'] );
                }
                if ( $row['vulns']['fixed_in'] ) {
                    /* translators: 1: the vulnerability text, 2: version that fixes them */
                    $reason = sprintf( __( '%1$s; fixed in %2$s', 'cleanup-report-generator' ), $reason, $row['vulns']['fixed_in'] );
                }
                $row['reasons'][] = $reason;
                if ( 'ok' === $row['status'] ) {
                    $row['status'] = 'vulnerable';
                }
            }

            // 3. Update available?
            if ( $row['latest'] && $it['version'] && version_compare( self::v( $row['latest'] ), self::v( $it['version'] ), '>' ) ) {
                /* translators: 1: installed version, 2: latest version */
                $row['reasons'][] = sprintf( __( 'Update available: %1$s → %2$s', 'cleanup-report-generator' ), $it['version'], $row['latest'] );
                if ( 'ok' === $row['status'] ) {
                    $row['status'] = 'outdated';
                }
            }
            if ( 'ok' === $row['status'] && 'unknown' === $row['source'] ) {
                $row['status'] = 'unknown';
            }
            $rows[] = $row + self::suggestion( $row );
        }

        // Worst first.
        $rank = [ 'closed' => 0, 'abandoned' => 1, 'vulnerable' => 2, 'outdated' => 3, 'unknown' => 4, 'ok' => 5 ];
        usort( $rows, static function ( $a, $b ) use ( $rank ) { return [ $rank[ $a['status'] ], strtolower( $a['name'] ) ] <=> [ $rank[ $b['status'] ], strtolower( $b['name'] ) ]; } );
        return $rows;
    }

    /** Current WordPress version (from WordPress.org, cached), for judging "Tested up to". */
    public static function current_wp() {
        $core = self::fetch( [ 'https://api.wordpress.org/core/version-check/1.7/' ] );
        return ( $core ? ( current( $core )['offers'][0]['version'] ?? '' ) : '' ) ?: get_bloginfo( 'version' );
    }

    /**
     * Is a WordPress.org plugin/theme still maintained? From its API answer ($org; null when the API failed).
     *
     * @param array $it [ type, slug, title ] — title '' skips the same-name check.
     * @return array [ source wporg|unknown, status ok|abandoned|closed|unknown, reason, latest, last_release Y-m-d, link, error ]
     */
    public static function maintenance( array $it, $org, $wp ) {
        $out   = [ 'source' => 'unknown', 'status' => 'unknown', 'reason' => '', 'latest' => '', 'last_release' => '', 'link' => '', 'error' => '' ];
        $link  = 'https://wordpress.org/' . $it['type'] . 's/' . strtolower( $it['slug'] ) . '/';
        $major = static function ( $v ) { $p = array_map( 'intval', explode( '.', self::v( $v ) ) ); return $p[0] * 10 + ( $p[1] ?? 0 ); };
        if ( null === $org ) {
            $out['error'] = __( 'WordPress.org didn\'t answer', 'cleanup-report-generator' );
        } elseif ( 'closed' === ( $org['error'] ?? '' ) ) {
            $out = [ 'source' => 'wporg', 'status' => 'closed', 'link' => $link, 'last_release' => (string) ( $org['closed_date'] ?? '' ) ] + $out;
            if ( ! empty( $org['closed_date'] ) ) {
                /* translators: %s: date the plugin or theme was closed */
                $out['reason'] = sprintf( __( 'Closed on WordPress.org on %s', 'cleanup-report-generator' ), wp_date( 'j M Y', strtotime( $org['closed_date'] ) ) );
            } else {
                $out['reason'] = __( 'Closed on WordPress.org', 'cleanup-report-generator' );
            }
            if ( ! empty( $org['reason_text'] ) ) {
                /* translators: 1: "Closed on WordPress.org…" text, 2: WordPress.org's reason for closing it */
                $out['reason'] = sprintf( _x( '%1$s (%2$s)', 'closed plugin and the reason', 'cleanup-report-generator' ), $out['reason'], $org['reason_text'] );
            }
        } elseif ( ! empty( $org['version'] ) && self::same_item( $it, $org ) ) {
            $updated   = strtotime( $org['last_updated'] ?? '' );
            $abandoned = [];
            if ( false !== stripos( $org['name'] ?? '', 'discontinued' ) ) {
                $abandoned[] = __( 'marked discontinued by its author', 'cleanup-report-generator' );
            }
            if ( $updated && ( time() - $updated ) / DAY_IN_SECONDS > self::STALE_DAYS ) {
                /* translators: %s: month and year of the last release */
                $abandoned[] = sprintf( __( 'no release since %s', 'cleanup-report-generator' ), wp_date( 'M Y', $updated ) );
            }
            if ( ! empty( $org['tested'] ) && $major( $wp ) - $major( $org['tested'] ) >= self::TESTED_BEHIND ) {
                /* translators: %s: WordPress version */
                $abandoned[] = sprintf( __( 'tested only up to WordPress %s', 'cleanup-report-generator' ), $org['tested'] );
            }
            $out = [
                'source'       => 'wporg',
                'status'       => $abandoned ? 'abandoned' : 'ok',
                /* translators: %s: comma-separated reasons, e.g. "no release since Jan 2022" */
                'reason'       => $abandoned ? sprintf( __( 'Not maintained: %s', 'cleanup-report-generator' ), implode( ', ', $abandoned ) ) : '',
                'latest'       => (string) $org['version'],
                'last_release' => $updated ? gmdate( 'Y-m-d', $updated ) : '',
                'link'         => $link,
            ] + $out;
        } elseif ( ! empty( $org['version'] ) ) {
            /* translators: %s: name of the WordPress.org item that uses this folder name */
            $out['reason'] = sprintf( __( 'Not on WordPress.org under this name (WordPress.org has a different item, "%s", with this folder name). Mark it in Reports › Settings › Plugin list', 'cleanup-report-generator' ), wp_strip_all_tags( $org['name'] ?? '' ) );
        } else {
            $out['reason'] = __( 'Not on WordPress.org: mark it as maintained or abandoned in Reports › Settings › Plugin list', 'cleanup-report-generator' );
        }
        return $out;
    }

    /** Known vulnerabilities with no fix in any version (from a WPVulnerability answer). */
    public static function open_vulns( $vdata ) {
        $n = 0;
        foreach ( (array) ( $vdata['data']['vulnerability'] ?? [] ) as $vuln ) {
            $n += '1' === (string) ( $vuln['operator']['unfixed'] ?? '' ) ? 1 : 0;
        }
        return $n;
    }

    /** Does the WordPress.org item with this slug look like the installed one (same name)? */
    private static function same_item( array $it, array $org ) {
        if ( '' === $it['title'] ) {
            return true;
        }
        $norm = static function ( $s ) { return preg_replace( '/[^a-z0-9]/', '', strtolower( remove_accents( html_entity_decode( wp_strip_all_tags( (string) $s ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) ); };
        $a    = $norm( $it['title'] );
        $b    = $norm( preg_replace( '/\s*[(\x{2013}\x{2014}:|-].*$/u', '', (string) ( $org['name'] ?? '' ) ) ); // "Name – tagline" → "Name"
        if ( '' === $a || '' === $b || false !== strpos( $a, $b ) || false !== strpos( $b, $a ) ) {
            return true;
        }
        similar_text( $a, $b, $pct );
        return $pct >= 60;
    }

    /** The row's suggested entry for the components table and the reinstall/replace lists. */
    private static function suggestion( array $row ) {
        $v      = $row['vulns'];
        $issue  = [];
        if ( in_array( $row['status'], [ 'closed', 'abandoned' ], true ) ) {
            if ( 'premium' === $row['source'] ) {
                $issue[] = __( 'No longer maintained by the vendor', 'cleanup-report-generator' );
            } else {
                // "Not maintained: …" (from maintenance()) reads "No longer maintained: …" in the report.
                $reason = $row['reasons'][0];
                /* translators: %s: comma-separated reasons, e.g. "no release since Jan 2022" */
                $prefix = sprintf( __( 'Not maintained: %s', 'cleanup-report-generator' ), '' );
                if ( '' !== $prefix && 0 === strpos( $reason, $prefix ) ) {
                    /* translators: %s: comma-separated reasons, e.g. "no release since Jan 2022" */
                    $reason = sprintf( __( 'No longer maintained: %s', 'cleanup-report-generator' ), substr( $reason, strlen( $prefix ) ) );
                }
                $issue[] = $reason;
            }
        }
        if ( $v['count'] ) {
            /* translators: %d: number of vulnerabilities */
            $text = sprintf( _n( '%d known vulnerability', '%d known vulnerabilities', $v['count'], 'cleanup-report-generator' ), $v['count'] );
            if ( $v['unfixed'] && $v['unfixed'] === $v['count'] ) {
                /* translators: %s: "N known vulnerabilities" */
                $text = sprintf( __( '%s with no fix available', 'cleanup-report-generator' ), $text );
            } elseif ( $v['unfixed'] ) {
                /* translators: 1: "N known vulnerabilities", 2: how many of them have no fix */
                $text = sprintf( __( '%1$s, %2$d with no fix available', 'cleanup-report-generator' ), $text, $v['unfixed'] );
            }
            if ( $v['fixed_in'] && ! in_array( $row['status'], [ 'closed', 'abandoned' ], true ) ) {
                /* translators: 1: the vulnerability text, 2: version to update to */
                $text = sprintf( __( '%1$s; update to %2$s+', 'cleanup-report-generator' ), $text, $v['fixed_in'] );
            }
            $issue[] = $text;
        } elseif ( 'outdated' === $row['status'] ) {
            /* translators: %s: latest version */
            $issue[] = sprintf( __( 'Outdated; update to %s', 'cleanup-report-generator' ), $row['latest'] );
        }
        if ( ! $issue ) {
            return [ 'severity' => '', 'issue' => '', 'list' => '' ];
        }
        // CVSS bands: 9+ critical, 7+ high. A flaw with no fix, or a plugin closed for security, is critical too.
        $critical = $v['unfixed'] || $v['score'] >= 9 || ( 'closed' === $row['status'] && false !== stripos( $row['reasons'][0], 'security' ) );
        $severity = $critical ? 'critical' : ( $v['score'] >= 7 ? 'high' : ( $v['count'] || in_array( $row['status'], [ 'closed', 'abandoned' ], true ) ? 'medium' : 'low' ) );
        $replace  = in_array( $row['status'], [ 'closed', 'abandoned' ], true ); // an unfixed flaw in a maintained item: staff decide
        return [
            'severity' => $severity,
            'issue'    => ucfirst( implode( '; ', $issue ) ),
            'list'     => $replace ? 'replace' : '',
        ];
    }

    /* ------------------------------------------------------------------ AJAX */

    public static function ajax() {
        check_ajax_referer( 'crg_check_plugins', 'nonce' );
        $post_id = absint( $_POST['post_id'] ?? 0 );
        // Only from a report this user may edit: the results are recorded against it on the plugin list.
        // Not on a report that's read-only for this user (approved, for a Writer): nothing may be recorded against it.
        if ( 'cleanup_report' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || Cleanup_Report_Access::locked( $post_id ) ) {
            wp_send_json_error( [ 'message' => __( 'You are not allowed to do this.', 'cleanup-report-generator' ) ], 403 );
        }
        // At most 10 checks per person in 10 minutes: each one can send a few hundred requests to the two services.
        $rate_key = 'crg_pc_rate_' . get_current_user_id();
        $recent   = (int) get_transient( $rate_key );
        if ( $recent >= 10 ) {
            wp_send_json_error( [ 'message' => __( 'Too many checks in a short time. Wait a few minutes and try again.', 'cleanup-report-generator' ) ], 429 );
        }
        set_transient( $rate_key, $recent + 1, 10 * MINUTE_IN_SECONDS );
        $items = self::parse( sanitize_textarea_field( wp_unslash( $_POST['list'] ?? '' ) ) );
        if ( ! $items ) {
            wp_send_json_error( [ 'message' => __( 'No plugins or themes found. Paste the output of the command above.', 'cleanup-report-generator' ) ] );
        }
        $rows = self::check( $items );
        Cleanup_Report_Plugin_Registry::record( $rows, $post_id );
        wp_send_json_success( [ 'rows' => $rows ] );
    }
}
