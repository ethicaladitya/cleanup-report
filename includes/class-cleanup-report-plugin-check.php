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
        $headers = [ 'User-Agent' => 'Cleanup-Report-Generator/' . CLEANUP_REPORT_VERSION, 'Accept' => 'application/json' ];
        $bodies  = [];
        if ( $todo && class_exists( '\WpOrg\Requests\Requests' ) ) {
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
            foreach ( $todo as $url ) {
                $response       = wp_remote_get( $url, [ 'timeout' => 15, 'headers' => $headers ] );
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
                    $row['reasons'][] = 'Marked as no longer maintained in your plugin list' . ( $p['note'] ? ': ' . $p['note'] : '' );
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
                $row['errors'][] = 'WPVulnerability didn\'t answer';
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
                $n                = $row['vulns']['count'];
                $row['reasons'][] = $n . ' known ' . ( 1 === $n ? 'vulnerability' : 'vulnerabilities' ) . ' in ' . $it['version']
                    . ( $row['vulns']['unfixed'] ? ' (' . $row['vulns']['unfixed'] . ' with no fix yet)' : '' )
                    . ( $row['vulns']['fixed_in'] ? '; fixed in ' . $row['vulns']['fixed_in'] : '' );
                if ( 'ok' === $row['status'] ) {
                    $row['status'] = 'vulnerable';
                }
            }

            // 3. Update available?
            if ( $row['latest'] && $it['version'] && version_compare( self::v( $row['latest'] ), self::v( $it['version'] ), '>' ) ) {
                $row['reasons'][] = 'Update available: ' . $it['version'] . ' → ' . $row['latest'];
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
            $out['error'] = 'WordPress.org didn\'t answer';
        } elseif ( 'closed' === ( $org['error'] ?? '' ) ) {
            $out = [ 'source' => 'wporg', 'status' => 'closed', 'link' => $link, 'last_release' => (string) ( $org['closed_date'] ?? '' ) ] + $out;
            $out['reason'] = 'Closed on WordPress.org' . ( ! empty( $org['closed_date'] ) ? ' on ' . wp_date( 'j M Y', strtotime( $org['closed_date'] ) ) : '' ) . ( ! empty( $org['reason_text'] ) ? ' (' . $org['reason_text'] . ')' : '' );
        } elseif ( ! empty( $org['version'] ) && self::same_item( $it, $org ) ) {
            $updated   = strtotime( $org['last_updated'] ?? '' );
            $abandoned = [];
            if ( false !== stripos( $org['name'] ?? '', 'discontinued' ) ) {
                $abandoned[] = 'marked discontinued by its author';
            }
            if ( $updated && ( time() - $updated ) / DAY_IN_SECONDS > self::STALE_DAYS ) {
                $abandoned[] = 'no release since ' . wp_date( 'M Y', $updated );
            }
            if ( ! empty( $org['tested'] ) && $major( $wp ) - $major( $org['tested'] ) >= self::TESTED_BEHIND ) {
                $abandoned[] = 'tested only up to WordPress ' . $org['tested'];
            }
            $out = [
                'source'       => 'wporg',
                'status'       => $abandoned ? 'abandoned' : 'ok',
                'reason'       => $abandoned ? 'Not maintained: ' . implode( ', ', $abandoned ) : '',
                'latest'       => (string) $org['version'],
                'last_release' => $updated ? gmdate( 'Y-m-d', $updated ) : '',
                'link'         => $link,
            ] + $out;
        } elseif ( ! empty( $org['version'] ) ) {
            $out['reason'] = 'Not on WordPress.org under this name (WordPress.org has a different item, "' . wp_strip_all_tags( $org['name'] ?? '' ) . '", with this folder name). Mark it in Reports › Settings › Plugin list';
        } else {
            $out['reason'] = 'Not on WordPress.org: mark it as maintained or abandoned in Reports › Settings › Plugin list';
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
            $issue[] = 'premium' === $row['source']
                ? 'No longer maintained by the vendor'
                : preg_replace( '/^Not maintained: /', 'No longer maintained: ', $row['reasons'][0] );
        }
        if ( $v['count'] ) {
            $issue[] = $v['count'] . ' known ' . ( 1 === $v['count'] ? 'vulnerability' : 'vulnerabilities' )
                . ( $v['unfixed'] ? ( $v['unfixed'] === $v['count'] ? ' with no fix available' : ', ' . $v['unfixed'] . ' with no fix available' ) : '' )
                . ( $v['fixed_in'] && ! in_array( $row['status'], [ 'closed', 'abandoned' ], true ) ? '; update to ' . $v['fixed_in'] . '+' : '' );
        } elseif ( 'outdated' === $row['status'] ) {
            $issue[] = 'Outdated; update to ' . $row['latest'];
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
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'You are not allowed to do this.' ], 403 );
        }
        $items = self::parse( sanitize_textarea_field( wp_unslash( $_POST['list'] ?? '' ) ) );
        if ( ! $items ) {
            wp_send_json_error( [ 'message' => 'No plugins or themes found. Paste the output of the command above.' ] );
        }
        $rows = self::check( $items );
        Cleanup_Report_Plugin_Registry::record( $rows, absint( $_POST['post_id'] ?? 0 ) );
        wp_send_json_success( [ 'rows' => $rows ] );
    }
}
