<?php
/**
 * The two editable checklists on every report:
 *  - Follow-ups ("Your next steps › Recommended actions"): things the member may need to do.
 *  - Defender configuration: the Defender settings we applied or left for the member.
 *
 * Their items are edited under Reports › Settings and stored in the options crg_followup_items and
 * crg_defender_items (missing option = the defaults below).
 *
 * A report stores its answers as an ordered snapshot (meta crg_followups / crg_defender) that includes each
 * item's wording at the time it was saved, so a report keeps reading the same after the settings change.
 * Reports saved by 2.0 used one meta key per item (crg_ssl, crg_defender_mask_login, ...); those are read
 * through legacy_*() until the report is next saved.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Cleanup_Report_Checklists {

    const FOLLOWUP_OPTION = 'crg_followup_items';
    const DEFENDER_OPTION = 'crg_defender_items';

    /** not_required = "Already configured" (nothing to do), fixed = "Done" (we did it during the cleanup), required = "Member to do". */
    const FOLLOWUP_STATUSES = [ 'not_required', 'fixed', 'required' ];
    /**
     * applied = "Applied" (we configured it), in_progress = "Pending" (not configured yet), pending = "Member to do"
     * (the member has to finish it). "pending" keeps its 2.0 meaning, so older reports still read the same.
     */
    const DEFENDER_STATUSES = [ 'applied', 'in_progress', 'pending' ];
    const PROGRESS_FALLBACK = 'Not configured yet.';

    /** Placeholders allowed in client-facing texts. */
    const PLACEHOLDERS = [ '{site}', '{host}', '{wp_admin}' ];

    const DEFAULT_FIXED = [
        'ssl'             => 'We installed an SSL certificate; all traffic is now encrypted.',
        'cloudflare'      => 'We set up Cloudflare to cache and protect your site.',
        'php_update'      => 'We moved the site to a supported PHP version.',
        'check_admins'    => 'We reviewed the administrators and removed accounts that weren\'t yours.',
        'reset_passwords' => 'We reset the passwords of all users.',
        'mask_login'      => 'We set a custom login URL in Defender.',
        'automate'        => 'We enabled WPMU DEV Automate to keep plugins and themes updated.',
        'review_content'  => 'We reviewed posts and comments and removed the spam.',
    ];
    const FIXED_FALLBACK = 'Done during the cleanup.';

    /**
     * Where each item is configured on the member's site, as linked in the team's cleanup report template.
     * Page and view names are Defender 6.4's own (wdf-ip-lockout&view=local-firewall …). {wp_admin} becomes the
     * report's website URL + /wp-admin, so the link opens that exact screen on the member's site.
     */
    const DEFENDER_LINKS = [
        'defender_recommendations'  => '{wp_admin}/admin.php?page=wdf-hardener',
        'malware_scanning'          => '{wp_admin}/admin.php?page=wdf-setting',
        'audit_logging'             => '{wp_admin}/admin.php?page=wdf-logging',
        'firewall_antibot'          => '{wp_admin}/admin.php?page=wdf-ip-lockout',
        'login_protection'          => '{wp_admin}/admin.php?page=wdf-ip-lockout&view=local-firewall',
        'firewall_404'              => '{wp_admin}/admin.php?page=wdf-ip-lockout&view=local-firewall',
        'malicious_bot'             => '{wp_admin}/admin.php?page=wdf-ip-lockout&view=local-firewall',
        'user_agent_bot_trap'       => '{wp_admin}/admin.php?page=wdf-ip-lockout&view=local-firewall',
        'fake_bots'                 => '{wp_admin}/admin.php?page=wdf-ip-lockout&view=local-firewall',
        'user_agent_empty_headers'  => '{wp_admin}/admin.php?page=wdf-ip-lockout&view=local-firewall',
        'user_agent_banning'        => '{wp_admin}/admin.php?page=wdf-ip-lockout&view=custom-rules',
        'user_agent_script_presets' => '{wp_admin}/admin.php?page=wdf-ip-lockout&view=custom-rules',
        'two_factor_auth'           => '{wp_admin}/admin.php?page=wdf-advanced-tools&view=two-factor-auth',
        'mask_login'                => '{wp_admin}/admin.php?page=wdf-advanced-tools&view=hide-login-url',
        'security_headers'          => '{wp_admin}/admin.php?page=wdf-setting&view=security-policies',
        'pwned_passwords'           => '{wp_admin}/admin.php?page=wdf-advanced-tools&view=password-rules',
        'strong_passwords'          => '{wp_admin}/admin.php?page=wdf-advanced-tools&view=password-rules',
        'password_reset'            => '{wp_admin}/admin.php?page=wdf-advanced-tools&view=password-rules',
        'session_protection'        => '{wp_admin}/admin.php?page=wdf-advanced-tools&view=session-security',
        'google_recaptcha'          => '{wp_admin}/admin.php?page=wdf-advanced-tools&view=bot-protection',
        'ip_detection'              => '{wp_admin}/admin.php?page=wdf-setting&view=tools',
        'blocklist_monitor'         => '{wp_admin}/admin.php?page=wdf-setting&view=tools',
    ];

    const FOLLOWUP_LINKS = [
        'check_admins'    => '{wp_admin}/users.php?role=administrator',
        'reset_passwords' => '{wp_admin}/admin.php?page=wdf-advanced-tools&view=password-rules',
        'mask_login'      => '{wp_admin}/admin.php?page=wdf-advanced-tools&view=hide-login-url',
        'review_content'  => '{wp_admin}/edit-comments.php',
    ];

    public static function default_followups() {
        // done = report text when already configured; fixed = when we did it during the cleanup; todo = when the member must.
        $f = static function ( $key, $label, $hint, $todo, $done, $tone = 'required', $docs = '' ) {
            return compact( 'key', 'label', 'hint', 'docs', 'todo', 'done', 'tone' ) + [ 'fixed' => self::DEFAULT_FIXED[ $key ] ?? '', 'link' => self::FOLLOWUP_LINKS[ $key ] ?? '', 'enabled' => true ];
        };
        return [
            $f( 'ssl', 'Configure SSL', 'Does the site need an SSL certificate?', 'Install an SSL certificate so all traffic is encrypted.', 'Your site already has SSL.' ),
            $f( 'cloudflare', 'Configure Cloudflare', 'Cloudflare caches the site globally and adds security.', 'Cloudflare caches and protects your site across its global network.', 'Already in place.' ),
            $f( 'php_update', 'Update PHP version', 'Running a supported PHP version improves security and speed.', 'A supported PHP version is more secure and faster.', 'Running a supported version.', 'required', 'https://www.php.net/supported-versions.php' ),
            $f( 'check_admins', 'Check for suspicious admins', 'Any administrator accounts the member should confirm?', 'Review Users and remove any administrator you don\'t recognise.', 'No unknown administrators found.' ),
            $f( 'reset_passwords', 'Reset all passwords', 'Should every user set a new password after the breach?', 'Make every user set a new password after the breach.', 'Not needed.' ),
            $f( 'mask_login', 'Hide the login URL', 'Masking the login URL and its notifications needs the site owner.', 'Set a custom login URL in Defender and share it with your team.', 'Already configured.', 'recommended', 'https://wpmudev.com/docs/wpmu-dev-plugins/defender/#mask-login-area' ),
            $f( 'automate', 'Enable WPMU DEV Automate', 'Keeps plugins and themes updated automatically.', 'Keeps plugins and themes updated automatically.', 'Already enabled.' ),
            $f( 'review_content', 'Review posts & comments', 'Check for spam the attacker may have added.', 'Check recent posts and comments for spam the attacker may have added.', 'Nothing suspicious found.' ),
        ];
    }

    public static function default_defender() {
        $labels = [
            'defender_recommendations'  => 'Defender recommendations',
            'malware_scanning'          => 'Scheduled malware scanning',
            'audit_logging'             => 'Audit logging',
            'firewall_antibot'          => 'Firewall: AntiBot',
            'login_protection'          => 'Firewall: login protection',
            'firewall_404'              => 'Firewall: 404 detection',
            'malicious_bot'             => 'Firewall: malicious bot detector',
            'fake_bots'                 => 'Firewall: catch fake bots',
            'user_agent_banning'        => 'User agent banning',
            'user_agent_bot_trap'       => 'User agent: bot trap',
            'user_agent_empty_headers'  => 'Empty headers protection',
            'user_agent_script_presets' => 'User agent: script presets',
            'two_factor_auth'           => 'Two-factor authentication (2FA)',
            'mask_login'                => 'Mask login area',
            'security_headers'          => 'Security headers',
            'pwned_passwords'           => 'Pwned passwords check',
            'strong_passwords'          => 'Strong password policy',
            'password_reset'            => 'Password reset protection',
            'session_protection'        => 'Session protection',
            'google_recaptcha'          => 'Google reCAPTCHA',
            'ip_detection'              => 'Correct IP detection',
            'blocklist_monitor'         => 'Blocklist monitor',
        ];
        $pending = [
            'two_factor_auth' => 'Each administrator needs to set up 2FA on their own account.',
            'mask_login'      => 'Choose a custom login URL and share it with your team.',
        ];
        $items = [];
        foreach ( $labels as $key => $label ) {
            $items[] = [
                'key'          => $key,
                'label'        => $label,
                'hint'         => '',
                'applied_note' => '',
                'pending_note' => $pending[ $key ] ?? '',
                'link'         => self::DEFENDER_LINKS[ $key ] ?? '',
                'enabled'      => true,
            ];
        }
        return $items;
    }

    /** All configured follow-up items (enabled or not), in order. */
    public static function followup_items() {
        $items = get_option( self::FOLLOWUP_OPTION, null );
        return is_array( $items ) ? $items : self::default_followups();
    }

    public static function defender_items() {
        $items = get_option( self::DEFENDER_OPTION, null );
        return is_array( $items ) ? $items : self::default_defender();
    }

    /* ------------------------------------------------------------------ settings sanitizing */

    public static function sanitize_followup_items( $input ) {
        return self::sanitize_items( $input, [
            'label' => 'sanitize_text_field',
            'hint'  => 'sanitize_text_field',
            'docs'  => 'esc_url_raw',
            'todo'  => 'sanitize_textarea_field',
            'done'  => 'sanitize_textarea_field',
            'fixed' => 'sanitize_textarea_field',
            'link'  => [ __CLASS__, 'sanitize_link' ],
        ], static function ( $raw ) {
            return [ 'tone' => ( 'recommended' === ( $raw['tone'] ?? '' ) ) ? 'recommended' : 'required' ];
        } );
    }

    public static function sanitize_defender_items( $input ) {
        return self::sanitize_items( $input, [
            'label'        => 'sanitize_text_field',
            'hint'         => 'sanitize_text_field',
            'applied_note' => 'sanitize_textarea_field',
            'pending_note' => 'sanitize_textarea_field',
            'progress_note' => 'sanitize_textarea_field',
            'link'         => [ __CLASS__, 'sanitize_link' ],
        ] );
    }

    /** A link template: {wp_admin}/… , {site}/… or a full https:// URL. Anything else is dropped. */
    public static function sanitize_link( $value ) {
        $value = trim( sanitize_text_field( (string) $value ) );
        if ( '' === $value ) {
            return '';
        }
        if ( preg_match( '#^\{(wp_admin|site)\}(/[^\s"<>]*)?$#', $value ) ) {
            return $value;
        }
        return esc_url_raw( $value, [ 'http', 'https' ] );
    }

    /**
     * A link template → URL on the report's website, or '' when it needs the website URL and the report has none.
     */
    public static function link( $template, $site_url ) {
        $template = (string) $template;
        if ( '' === $template || ( ! $site_url && preg_match( '/\{(wp_admin|site|host)\}/', $template ) ) ) {
            return '';
        }
        return esc_url_raw( self::fill( $template, $site_url ) );
    }

    /**
     * Rows posted from the settings repeater → clean list. Rows keep their key (reports refer to it); new rows
     * get one made from their label. Rows without a label are dropped. Safe to run twice on its own output.
     */
    private static function sanitize_items( $input, array $fields, ?callable $extra = null ) {
        if ( ! is_array( $input ) ) {
            return [];
        }
        $out  = [];
        $used = [];
        foreach ( $input as $raw ) {
            if ( ! is_array( $raw ) ) {
                continue;
            }
            $row = [];
            foreach ( $fields as $name => $clean ) {
                $row[ $name ] = trim( call_user_func( $clean, (string) ( $raw[ $name ] ?? '' ) ) );
            }
            if ( '' === $row['label'] ) {
                continue;
            }
            $key = sanitize_key( $raw['key'] ?? '' );
            if ( '' === $key ) {
                $key = substr( trim( preg_replace( '/[^a-z0-9]+/', '_', strtolower( remove_accents( $row['label'] ) ) ), '_' ), 0, 40 ) ?: 'item';
            }
            $base = $key;
            for ( $n = 2; isset( $used[ $key ] ); $n++ ) {
                $key = $base . '_' . $n;
            }
            $used[ $key ] = true;
            $row          = [ 'key' => $key ] + $row + ( $extra ? $extra( $raw ) : [] );
            $row['enabled'] = ! empty( $raw['enabled'] );
            $out[]        = $row;
        }
        return $out;
    }

    /* ------------------------------------------------------------------ a report's answers */

    /**
     * The report's follow-ups, in order: [ key => [ key, label, todo, done, tone, status, note ] ].
     */
    public static function report_followups( $post_id ) {
        $saved = get_post_meta( $post_id, 'crg_followups', true );
        return is_array( $saved ) ? $saved : self::legacy_followups( $post_id );
    }

    /**
     * The report's Defender rows, in order: [ key => [ key, label, applied_note, pending_note, status, notes ] ].
     */
    public static function report_defender( $post_id ) {
        $saved = get_post_meta( $post_id, 'crg_defender', true );
        return is_array( $saved ) ? $saved : self::legacy_defender( $post_id );
    }

    private static function legacy_followups( $post_id ) {
        $defs = self::keyed( array_merge( self::default_followups(), self::followup_items() ) );
        $rows = [];
        // The order 2.0 reports used.
        foreach ( [ 'ssl', 'cloudflare', 'php_update', 'check_admins', 'reset_passwords', 'mask_login', 'automate', 'review_content' ] as $key ) {
            $status = get_post_meta( $post_id, 'crg_' . $key, true );
            if ( in_array( $status, self::FOLLOWUP_STATUSES, true ) ) {
                $rows[ $key ] = self::followup_row( $defs[ $key ], $status, '' );
            }
        }
        return $rows;
    }

    private static function legacy_defender( $post_id ) {
        $defs = self::keyed( array_merge( self::default_defender(), self::defender_items() ) );
        $rows = [];
        foreach ( self::default_defender() as $item ) {
            $key    = $item['key'];
            $status = get_post_meta( $post_id, 'crg_defender_' . $key, true );
            if ( in_array( $status, self::DEFENDER_STATUSES, true ) ) {
                $rows[ $key ] = self::defender_row( $defs[ $key ], $status, (string) get_post_meta( $post_id, 'crg_defender_' . $key . '_notes', true ) );
            }
        }
        return $rows;
    }

    private static function followup_row( array $def, $status, $note ) {
        return [
            'key'    => $def['key'],
            'label'  => $def['label'],
            'todo'   => $def['todo'] ?? '',
            'done'   => $def['done'] ?? '',
            'fixed'  => ( $def['fixed'] ?? '' ) ?: ( self::DEFAULT_FIXED[ $def['key'] ] ?? self::FIXED_FALLBACK ),
            'tone'   => $def['tone'] ?? 'required',
            'link'   => $def['link'] ?? ( self::FOLLOWUP_LINKS[ $def['key'] ] ?? '' ),
            'status' => $status,
            'note'   => $note,
        ];
    }

    private static function defender_row( array $def, $status, $notes ) {
        return [
            'key'          => $def['key'],
            'label'        => $def['label'],
            'applied_note' => $def['applied_note'] ?? '',
            'pending_note' => $def['pending_note'] ?? '',
            'progress_note' => $def['progress_note'] ?? '',
            'link'         => $def['link'] ?? ( self::DEFENDER_LINKS[ $def['key'] ] ?? '' ),
            'status'       => $status,
            'notes'        => $notes,
        ];
    }

    public static function keyed( array $items ) {
        $out = [];
        foreach ( $items as $item ) {
            $out[ $item['key'] ] = $item;
        }
        return $out;
    }

    /**
     * Items shown on the edit screen: the enabled ones from settings, then anything this report already answered
     * that has since been disabled or removed (so saving never silently drops an answer).
     *
     * @return array[] each item plus 'orphan': false, 'off' (switched off in settings) or 'removed'
     */
    public static function form_items( array $settings_items, array $answers ) {
        $items = [];
        foreach ( $settings_items as $item ) {
            if ( ! empty( $item['enabled'] ) ) {
                $items[ $item['key'] ] = $item + [ 'orphan' => false ];
            }
        }
        $all = self::keyed( $settings_items );
        foreach ( $answers as $key => $row ) {
            if ( ! isset( $items[ $key ] ) ) {
                $items[ $key ] = isset( $all[ $key ] ) ? $all[ $key ] + [ 'orphan' => 'off' ] : $row + [ 'hint' => '', 'docs' => '', 'link' => '', 'orphan' => 'removed' ];
            }
        }
        return $items;
    }

    /**
     * Saves both checklists from the edit form. Wording comes from settings (or, for an item no longer in
     * settings, from the report's own snapshot), never from the request.
     *
     * @param array $followups $_POST['crg_followups'] (unslashed): key => [ status, note ]
     * @param array $defender  $_POST['crg_defender'] (unslashed):  key => [ status, notes ]
     */
    public static function save_report( $post_id, $followups, $defender ) {
        if ( is_array( $followups ) ) {
            $defs = self::keyed( self::followup_items() );
            $old  = self::report_followups( $post_id );
            $rows = [];
            foreach ( $followups as $key => $posted ) {
                $key    = sanitize_key( $key );
                $status = sanitize_key( $posted['status'] ?? '' );
                $def    = $defs[ $key ] ?? $old[ $key ] ?? null;
                if ( $def && in_array( $status, self::FOLLOWUP_STATUSES, true ) ) {
                    $rows[ $key ] = self::followup_row( $def, $status, sanitize_text_field( $posted['note'] ?? '' ) );
                }
            }
            update_post_meta( $post_id, 'crg_followups', wp_slash( $rows ) );
        }
        if ( is_array( $defender ) ) {
            $defs = self::keyed( self::defender_items() );
            $old  = self::report_defender( $post_id );
            $rows = [];
            foreach ( $defender as $key => $posted ) {
                $key    = sanitize_key( $key );
                $status = sanitize_key( $posted['status'] ?? '' );
                $def    = $defs[ $key ] ?? $old[ $key ] ?? null;
                if ( $def && in_array( $status, self::DEFENDER_STATUSES, true ) ) {
                    $rows[ $key ] = self::defender_row( $def, $status, sanitize_text_field( $posted['notes'] ?? '' ) );
                }
            }
            update_post_meta( $post_id, 'crg_defender', wp_slash( $rows ) );
        }
    }

    /* ------------------------------------------------------------------ output helpers */

    /**
     * Fills {site}, {host} and {wp_admin} in a client-facing text from the report's website URL.
     */
    public static function fill( $text, $site_url ) {
        $site = untrailingslashit( (string) $site_url );
        $host = $site ? ( wp_parse_url( $site, PHP_URL_HOST ) ?: $site ) : '';
        return strtr( (string) $text, [
            '{site}'     => $site ?: 'your website',
            '{host}'     => $host ?: 'your website',
            '{wp_admin}' => $site ? $site . '/wp-admin' : 'your WordPress dashboard',
        ] );
    }

    /**
     * Follow-ups as the report shows them: label, status ('in_place' | 'done' | 'required' | 'recommended') and the note.
     */
    public static function followups_for_output( array $rows, $site_url ) {
        $out = [];
        foreach ( $rows as $row ) {
            switch ( $row['status'] ) {
                case 'required':
                    [ $status, $text ] = [ 'recommended' === $row['tone'] ? 'recommended' : 'required', $row['todo'] ];
                    break;
                case 'fixed':
                    [ $status, $text ] = [ 'done', ( $row['fixed'] ?? '' ) ?: self::FIXED_FALLBACK ];
                    break;
                default: // not_required
                    [ $status, $text ] = [ 'in_place', $row['done'] ];
            }
            $out[] = [
                'label'  => $row['label'],
                'status' => $status,
                'note'   => self::fill( '' !== (string) $row['note'] ? $row['note'] : $text, $site_url ),
                // Reports saved before links existed use today's default for the item.
                'link'   => self::link( $row['link'] ?? ( self::FOLLOWUP_LINKS[ $row['key'] ] ?? '' ), $site_url ),
            ];
        }
        return $out;
    }

    /**
     * Defender rows as the report shows them (keys kept from 2.0: setting, status, notes).
     */
    public static function defender_for_output( array $rows, $site_url ) {
        $out = [];
        foreach ( $rows as $row ) {
            $fallback = [
                'applied'     => ( $row['applied_note'] ?? '' ) ?: 'Configured to recommended settings.',
                'in_progress' => ( $row['progress_note'] ?? '' ) ?: self::PROGRESS_FALLBACK,
            ][ $row['status'] ] ?? ( $row['pending_note'] ?? '' );
            $out[]    = [
                'setting' => $row['label'],
                'status'  => $row['status'],
                'notes'   => self::fill( '' !== (string) $row['notes'] ? $row['notes'] : $fallback, $site_url ),
                'link'    => self::link( $row['link'] ?? ( self::DEFENDER_LINKS[ $row['key'] ] ?? '' ), $site_url ),
            ];
        }
        return $out;
    }
}
