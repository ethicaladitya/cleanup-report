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
    const BLOCKLIST_OPTION = 'crg_blocklists';

    /**
     * Security vendors that can list a site (URL engines on VirusTotal, named as VirusTotal shows them), with where the
     * member requests delisting: [ name, web form, email, how ]. Edited under Reports › Settings › Blocklists.
     * Checked in November 2026 against each vendor's site; addresses found only on VirusTotal's false-positive contacts
     * page (docs.virustotal.com/docs/false-positive-contacts) are kept as VirusTotal gives them.
     */
    const DEFAULT_BLOCKLISTS = [
        [ 'alphaMountain.ai', 'https://alphamountain.freshdesk.com/support/tickets/new', '', '' ],
        [ 'Antiy-AVL', '', 'avlsdk_support@antiy.cn', '' ],
        [ 'Avira', 'https://www.avira.com/en/analysis/submit-url', '', 'Choose "False Positive URLs (Not Malware)" and enter the full URL.' ],
        [ 'BitDefender', 'https://www.bitdefender.com/submit/', '', '' ],
        [ 'Certego', '', 'fp@certego.net', '' ],
        [ 'CRDF', 'https://threatcenter.crdf.fr/false_positive.html', '', '' ],
        [ 'Criminal IP', '', 'support@aispera.com', '' ],
        [ 'CyRadar', '', 'contact@cyradar.com', '' ],
        [ 'Dr.Web', 'https://support.drweb.com/new/detection_issue/', '', '' ],
        [ 'Emsisoft', '', 'submit@emsisoft.com', 'Include the blocked web address.' ],
        [ 'Ermes', '', 'support-vt@ermes.company', '' ],
        [ 'ESET', '', 'samples@eset.com', 'Subject "Domain whitelist" followed by the domain; include the URL, why it is a false positive and a screenshot.' ],
        [ 'Forcepoint ThreatSeeker', 'https://support.forcepoint.com/s/site-lookup', '', 'Look up the site and ask for it to be re-evaluated.' ],
        [ 'Fortinet', 'https://www.fortiguard.com/faq/malurl', '', '' ],
        [ 'G-Data', 'https://su.gdatasoftware.com/url-submission', '', '' ],
        [ 'Google Safebrowsing', 'https://search.google.com/search-console/security-issues', '', 'Verify the site in Google Search Console, then click Request Review.' ],
        [ 'Gridinsoft', 'https://portal.gridinsoft.com/false-positive', '', '' ],
        [ 'Heimdal Security', '', 'report-vt@heimdalsecurity.com', '' ],
        [ 'Kaspersky', 'https://opentip.kaspersky.com/', '', 'Look up the URL, then click Submit to reanalyze.' ],
        [ 'Lionic', '', 'support@lionic.com', '' ],
        [ 'Netcraft', 'https://report.netcraft.com/report/mistake', '', '' ],
        [ 'OpenPhish', '', 'support@openphish.com', '' ],
        [ 'Phishing Database', 'https://github.com/Phishing-Database/Phishing.Database/issues', '', 'Open an issue with the false-positive template.' ],
        [ 'Phishtank', 'https://www.phishtank.com/phish_archive.php', '', 'Open the site\'s entry and choose "Site is not a phish" (needs a PhishTank account).' ],
        [ 'Quttera', '', 'support@quttera.com', '' ],
        [ 'Seclookup', '', 'info@seclookup.com', '' ],
        [ 'SOCRadar', '', 'vt@socradar.io', '' ],
        [ 'Sophos', 'https://support.sophos.com/support/s/filesubmission', '', 'Choose "Web Address (URL)".' ],
        [ 'Spamhaus', 'https://check.spamhaus.org/', '', 'Look up the domain and follow the removal steps.' ],
        [ 'Sucuri SiteCheck', '', 'soc@sucuri.net', '' ],
        [ 'Trustwave', 'https://support.levelblue.com/submit-url.asp', '', 'Choose "Remove URL from Database".' ],
        [ 'URLhaus', 'https://urlhaus.abuse.ch/browse/', '', 'Open the URL\'s entry, then Actions › Report a False Positive.' ],
        [ 'Webroot', 'https://support.threatintel.opentext.com/tools/change-request.php', '', '' ],
        [ 'Xcitium Verdict Cloud', '', 'support@xcitium.com', '' ],
        [ 'Yandex Safebrowsing', 'https://webmaster.yandex.com/', '', 'Verify the site in Yandex Webmaster, then Security and violations › Violation fixed.' ],
    ];

    /** not_required = "Already configured" (nothing to do), fixed = "Done" (we did it during the cleanup), required = "Member to do". */
    const FOLLOWUP_STATUSES = [ 'not_required', 'fixed', 'required' ];
    /**
     * applied = "Applied" (we configured it), in_progress = "Pending" (not configured yet), pending = "Member to do"
     * (the member has to finish it). "pending" keeps its 2.0 meaning, so older reports still read the same.
     */
    const DEFENDER_STATUSES = [ 'applied', 'in_progress', 'pending' ];



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

    /** The report text for a follow-up we did during the cleanup (DEFAULT_FIXED, translated), or the general one. */
    public static function fixed_text( $key ) {
        $texts = [
            'ssl'             => __( 'We installed an SSL certificate; all traffic is now encrypted.', 'cleanup-report-generator' ),
            'cloudflare'      => __( 'We set up Cloudflare to cache and protect your site.', 'cleanup-report-generator' ),
            'php_update'      => __( 'We moved the site to a supported PHP version.', 'cleanup-report-generator' ),
            'check_admins'    => __( 'We reviewed the administrators and removed accounts that weren\'t yours.', 'cleanup-report-generator' ),
            'reset_passwords' => __( 'We reset the passwords of all users.', 'cleanup-report-generator' ),
            'mask_login'      => __( 'We set a custom login URL in Defender.', 'cleanup-report-generator' ),
            'automate'        => __( 'We enabled WPMU DEV Automate to keep plugins and themes updated.', 'cleanup-report-generator' ),
            'review_content'  => __( 'We reviewed posts and comments and removed the spam.', 'cleanup-report-generator' ),
        ];
        return $texts[ $key ] ?? __( 'Done during the cleanup.', 'cleanup-report-generator' );
    }

    /** The report text for a Defender setting not configured yet, when the item has none of its own. */
    public static function progress_text() {
        return __( 'Not configured yet.', 'cleanup-report-generator' );
    }

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
        'reports_alerts'            => '{wp_admin}/admin.php?page=wdf-notification',
    ];

    const FOLLOWUP_LINKS = [
        'check_admins'    => '{wp_admin}/users.php?role=administrator',
        'reset_passwords' => '{wp_admin}/admin.php?page=wdf-advanced-tools&view=password-rules',
        'mask_login'      => '{wp_admin}/admin.php?page=wdf-advanced-tools&view=hide-login-url',
        'review_content'  => '{wp_admin}/edit-comments.php',
    ];

    /** Defender's documentation; each item links to its section (anchors as in the docs, checked against Defender 6.4). */
    const DEFENDER_DOCS = 'https://wpmudev.com/docs/wpmu-dev-plugins/defender/';

    /**
     * Who does each Defender setting by default: the agent during the cleanup, or the member (things that need the
     * site owner's choices or accounts). Settings › Defender checklist can change it per item.
     */
    const MEMBER_ITEMS = [ 'mask_login', 'two_factor_auth', 'session_protection', 'google_recaptcha', 'user_agent_script_presets' ];

    /**
     * Names before 2.1.0 switched to the wording of the team's report template (and Defender's screens). Saved lists
     * and unfinished reports still using one of these are renamed once by Cleanup_Report_Admin::upgrade().
     */
    const OLD_LABELS = [
        'defender' => [
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
        ],
        'followups' => [
            'php_update'      => 'Update PHP version',
            'check_admins'    => 'Check for suspicious admins',
            'reset_passwords' => 'Reset all passwords',
            'mask_login'      => 'Hide the login URL',
            'review_content'  => 'Review posts & comments',
        ],
    ];

    public static function default_followups() {
        // done = report text when already configured; fixed = when we did it during the cleanup; todo = when the member must.
        $f = static function ( $key, $label, $hint, $todo, $done, $tone = 'required', $docs = '', $enabled = true ) {
            return compact( 'key', 'label', 'hint', 'docs', 'todo', 'done', 'tone' ) + [ 'fixed' => isset( self::DEFAULT_FIXED[ $key ] ) ? self::fixed_text( $key ) : '', 'link' => self::FOLLOWUP_LINKS[ $key ] ?? '', 'enabled' => $enabled ];
        };
        // Names as in the team's report template.
        return [
            $f( 'ssl', __( 'Configure SSL', 'cleanup-report-generator' ), __( 'Does the site need an SSL certificate?', 'cleanup-report-generator' ), __( 'Install an SSL certificate so all traffic is encrypted.', 'cleanup-report-generator' ), __( 'Your site already has SSL.', 'cleanup-report-generator' ) ),
            $f( 'cloudflare', __( 'Configure Cloudflare', 'cleanup-report-generator' ), __( 'Cloudflare caches the site globally and adds security.', 'cleanup-report-generator' ), __( 'Once configured, Cloudflare caches and protects your site across its worldwide network.', 'cleanup-report-generator' ), __( 'Already in place.', 'cleanup-report-generator' ) ),
            $f( 'php_update', __( 'Update PHP Version', 'cleanup-report-generator' ), __( 'Running a supported PHP version improves security and speed.', 'cleanup-report-generator' ), __( 'The latest PHP version gives better security and performance.', 'cleanup-report-generator' ), __( 'Running a supported version.', 'cleanup-report-generator' ), 'required', 'https://www.php.net/supported-versions.php' ),
            $f( 'check_admins', __( 'Check any suspect admins', 'cleanup-report-generator' ), __( 'Any administrator accounts the member should confirm?', 'cleanup-report-generator' ), __( 'Review Users and remove any administrator you don\'t recognise.', 'cleanup-report-generator' ), __( 'No unknown administrators found.', 'cleanup-report-generator' ) ),
            $f( 'reset_passwords', __( 'Login & Access - Password Rules', 'cleanup-report-generator' ), __( 'Force users to change their passwords at their next login after the breach.', 'cleanup-report-generator' ), __( 'Force users to change their passwords at their next login, because of the security breach.', 'cleanup-report-generator' ), __( 'Not needed.', 'cleanup-report-generator' ), 'required', self::DEFENDER_DOCS . '#reset-passwords' ),
            // Off by default: Hide Login URL is in the Defender checklist's member part.
            $f( 'mask_login', __( 'Hide Login URL', 'cleanup-report-generator' ), __( 'Setting a custom login URL needs the site owner.', 'cleanup-report-generator' ), __( 'Set a custom login URL in Defender and share it with your team.', 'cleanup-report-generator' ), __( 'Already configured.', 'cleanup-report-generator' ), 'recommended', self::DEFENDER_DOCS . '#hide-login-url', false ),
            $f( 'automate', __( 'Enable WPMU DEV Automate', 'cleanup-report-generator' ), __( 'Keeps plugins and themes updated automatically.', 'cleanup-report-generator' ), __( 'Keeps plugins and themes updated automatically.', 'cleanup-report-generator' ), __( 'Already enabled.', 'cleanup-report-generator' ), 'required', '', (bool) Cleanup_Report_Presets::get( 'product_items' ) ), // WPMU DEV preset
            $f( 'review_content', __( 'Review Post/Comment Section', 'cleanup-report-generator' ), __( 'Check for spam the attacker may have added.', 'cleanup-report-generator' ), __( 'Check recent posts and comments for spam the attacker may have added.', 'cleanup-report-generator' ), __( 'Nothing suspicious found.', 'cleanup-report-generator' ) ),
        ];
    }

    public static function default_defender() {
        // [ name as in the team's report template, docs anchor, report text when the member needs to act ]
        $items = [
            // To be done by the agent.
            'defender_recommendations'  => [ __( 'Defender Hardening', 'cleanup-report-generator' ), 'security-recommendations', '' ],
            'audit_logging'             => [ __( 'Enabling Audit Log', 'cleanup-report-generator' ), 'audit-log', '' ],
            'firewall_antibot'          => [ __( 'Firewalls - Global Firewall', 'cleanup-report-generator' ), 'antibot-global-firewall', '' ],
            'login_protection'          => [ __( 'Local Firewall - Login Protection', 'cleanup-report-generator' ), 'login-protection', '' ],
            'firewall_404'              => [ __( 'Local Firewall - 404 Detection', 'cleanup-report-generator' ), '404-detection', '' ],
            'malicious_bot'             => [ __( 'Local Firewall - Malicious Bot Detector', 'cleanup-report-generator' ), 'malicious-bot-detector', '' ],
            'user_agent_bot_trap'       => [ __( 'Local Firewall - Trap Bots That Ignore robots.txt', 'cleanup-report-generator' ), 'trap-bots-that-ignore-robots-txt', '' ],
            'fake_bots'                 => [ __( 'Local Firewall - Catch Fake Bots', 'cleanup-report-generator' ), 'catch-fake-bots-impersonating-legitimate-crawlers', '' ],
            'user_agent_empty_headers'  => [ __( 'Local Firewall - Empty Headers', 'cleanup-report-generator' ), 'empty-headers', '' ],
            'user_agent_banning'        => [ __( 'Custom Rules - User Agent', 'cleanup-report-generator' ), 'user-agent-banning', '' ],
            'pwned_passwords'           => [ __( 'Password Rules - Pwned Passwords', 'cleanup-report-generator' ), 'pwned-passwords', '' ],
            'strong_passwords'          => [ __( 'Password Rules - Strong Password', 'cleanup-report-generator' ), 'strong-passwords', '' ],
            'password_reset'            => [ __( 'Password Rules - Reset Passwords', 'cleanup-report-generator' ), 'reset-passwords', '' ],
            'malware_scanning'          => [ __( 'Tools > Issues Scan', 'cleanup-report-generator' ), 'issues-scan', '' ],
            'ip_detection'              => [ __( 'Tools > Firewall Detect IP Addresses', 'cleanup-report-generator' ), 'firewall-tools', '' ],
            'blocklist_monitor'         => [ __( 'Tools > Blocklist Monitor', 'cleanup-report-generator' ), 'blocklist-monitor', '' ],
            'security_headers'          => [ __( 'Tools > Security Headers', 'cleanup-report-generator' ), 'security-policies', '' ],
            'reports_alerts'            => [ __( 'Reports & Alerts - Scheduled Reports', 'cleanup-report-generator' ), 'reports-alerts', '' ],
            // To be done by the member.
            'mask_login'                => [ __( 'Hide Login URL', 'cleanup-report-generator' ), 'hide-login-url', __( 'Choose a custom login URL and share it with your team.', 'cleanup-report-generator' ) ],
            'two_factor_auth'           => [ __( 'Login & Access - Two-Factor Auth', 'cleanup-report-generator' ), 'two-factor-auth', __( 'Each administrator needs to set up 2FA on their own account.', 'cleanup-report-generator' ) ],
            'session_protection'        => [ __( 'Login & Access - Session Security', 'cleanup-report-generator' ), 'session-security', __( 'Set an idle timeout and session lock to protect your users from session hijacking.', 'cleanup-report-generator' ) ],
            'google_recaptcha'          => [ __( 'Login & Access - Bot Protection', 'cleanup-report-generator' ), 'bot-protection', __( 'Add a CAPTCHA (Google reCAPTCHA or Cloudflare Turnstile) to your login, registration and comment forms. It needs your own API keys.', 'cleanup-report-generator' ) ],
            'user_agent_script_presets' => [ __( 'Custom Rules - User Agent - Scripts', 'cleanup-report-generator' ), 'blocklist-presets', __( 'Block the Scripts preset (automated clients) if none of your own services reach the site that way.', 'cleanup-report-generator' ) ],
        ];
        $out = [];
        foreach ( $items as $key => [ $label, $anchor, $pending ] ) {
            $out[] = [
                'key'          => $key,
                'label'        => $label,
                'owner'        => in_array( $key, self::MEMBER_ITEMS, true ) ? 'member' : 'agent',
                'hint'         => '',
                'applied_note' => '',
                'pending_note' => $pending,
                'link'         => self::DEFENDER_LINKS[ $key ] ?? '',
                'docs'         => self::DEFENDER_DOCS . '#' . $anchor,
                'enabled'      => (bool) Cleanup_Report_Presets::get( 'product_items' ), // on with the WPMU DEV preset; can be switched on in Settings
            ];
        }
        return $out;
    }

    /** "agent" or "member": who does this Defender setting (items saved before 2.1.0 get their default). */
    public static function owner( array $item ) {
        $owner = $item['owner'] ?? '';
        if ( in_array( $owner, [ 'agent', 'member' ], true ) ) {
            return $owner;
        }
        return in_array( $item['key'] ?? '', self::MEMBER_ITEMS, true ) ? 'member' : 'agent';
    }

    /**
     * Renames items still called by a pre-2.1.0 default name to today's default (an item someone renamed is left
     * alone), and gives Defender items their owner and docs link. $kind: 'defender' or 'followups'.
     */
    private static function renamed( array $items, $kind ) {
        $defaults = self::keyed( 'defender' === $kind ? self::default_defender() : self::default_followups() );
        foreach ( $items as $i => $item ) {
            $key = $item['key'] ?? '';
            if ( ! isset( $defaults[ $key ] ) ) {
                continue;
            }
            if ( ( $item['label'] ?? '' ) === ( self::OLD_LABELS[ $kind ][ $key ] ?? null ) ) {
                $items[ $i ]['label'] = $defaults[ $key ]['label'];
            }
            if ( 'defender' === $kind ) {
                $items[ $i ]['owner'] = self::owner( $item );
                $items[ $i ]['docs']  = ( $item['docs'] ?? '' ) ?: $defaults[ $key ]['docs'];
            }
        }
        return $items;
    }

    /** One-time rename in the saved checklists (Cleanup_Report_Admin::upgrade()). */
    public static function rename_saved_labels() {
        foreach ( [ 'defender' => self::DEFENDER_OPTION, 'followups' => self::FOLLOWUP_OPTION ] as $kind => $option ) {
            $items = get_option( $option, null );
            if ( is_array( $items ) ) {
                $new = self::renamed( $items, $kind );
                if ( $new !== $items ) {
                    update_option( $option, $new, false );
                }
            }
        }
    }

    /** One-time rename in a report's saved checklists (only for reports not yet approved). */
    public static function rename_report_labels( $post_id ) {
        foreach ( [ 'defender' => 'crg_defender', 'followups' => 'crg_followups' ] as $kind => $meta ) {
            $rows = get_post_meta( $post_id, $meta, true );
            if ( is_array( $rows ) && $rows ) {
                $new = self::renamed( $rows, $kind );
                if ( $new !== $rows ) {
                    update_post_meta( $post_id, $meta, wp_slash( $new ) );
                }
            }
        }
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

    public static function default_blocklists() {
        $out = [];
        foreach ( self::DEFAULT_BLOCKLISTS as [ $label, $url, $email, $note ] ) {
            $out[] = [
                'key'     => substr( trim( preg_replace( '/[^a-z0-9]+/', '_', strtolower( remove_accents( $label ) ) ), '_' ), 0, 40 ),
                'label'   => $label,
                'url'     => $url,
                'email'   => $email,
                'note'    => $note,
                'enabled' => true,
            ];
        }
        return $out;
    }

    public static function blocklist_items() {
        $items = get_option( self::BLOCKLIST_OPTION, null );
        return is_array( $items ) ? $items : self::default_blocklists();
    }

    public static function sanitize_blocklists( $input ) {
        return self::sanitize_items( $input, [
            'label' => 'sanitize_text_field',
            'url'   => static function ( $v ) { return esc_url_raw( $v, [ 'http', 'https' ] ); },
            'email' => 'sanitize_email',
            'note'  => 'sanitize_text_field',
        ] );
    }

    /** The blocklists a report says list the site, in settings order: key => [ key, label, url, email, note ]. */
    public static function report_blocklists( $post_id ) {
        $saved = get_post_meta( $post_id, 'crg_delist_vendors', true );
        return is_array( $saved ) ? $saved : [];
    }

    /**
     * Saves the ticked blocklists with their contact details as they are now (a report keeps them if settings
     * change). A vendor no longer in settings stays as the report saved it.
     *
     * @param string[] $keys $_POST['crg_delist_vendors'] (sanitised keys)
     */
    public static function save_report_blocklists( $post_id, array $keys ) {
        $defs = self::keyed( self::blocklist_items() );
        $old  = self::report_blocklists( $post_id );
        $rows = [];
        foreach ( array_map( 'sanitize_key', $keys ) as $key ) {
            $def = $defs[ $key ] ?? $old[ $key ] ?? null;
            if ( $def ) {
                $rows[ $key ] = [ 'key' => $key, 'label' => $def['label'], 'url' => $def['url'] ?? '', 'email' => $def['email'] ?? '', 'note' => $def['note'] ?? '' ];
            }
        }
        update_post_meta( $post_id, 'crg_delist_vendors', wp_slash( $rows ) );
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
            'docs'         => 'esc_url_raw',
        ], static function ( $raw ) {
            return [ 'owner' => self::owner( $raw ) ];
        } );
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
    public static function sanitize_items( $input, array $fields, ?callable $extra = null ) {
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
            'fixed'  => ( $def['fixed'] ?? '' ) ?: self::fixed_text( $def['key'] ),
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
            'docs'         => $def['docs'] ?? '',
            'owner'        => self::owner( $def ),
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
                    [ $status, $text ] = [ 'done', ( $row['fixed'] ?? '' ) ?: self::fixed_text( '' ) ];
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
                'in_progress' => ( $row['progress_note'] ?? '' ) ?: self::progress_text(),
            ][ $row['status'] ] ?? ( $row['pending_note'] ?? '' );
            $out[]    = [
                'setting' => $row['label'],
                'status'  => $row['status'],
                'notes'   => self::fill( '' !== (string) $row['notes'] ? $row['notes'] : $fallback, $site_url ),
                'link'    => self::link( $row['link'] ?? ( self::DEFENDER_LINKS[ $row['key'] ] ?? '' ), $site_url ),
                'docs'    => esc_url_raw( (string) ( $row['docs'] ?? '' ) ),
                'owner'   => self::owner( $row ),
            ];
        }
        return $out;
    }
}
