<?php
/**
 * Fixture "sample": a complete report, modelled on a real cleanup report with everything identifying replaced
 * (client, site, request link, users). This is the report to look at when judging the design.
 *
 * wp eval-file sample.php <fixture-image-dir>
 */
defined( 'WP_CLI' ) || exit;
require_once __DIR__ . '/_lib.php';

$id  = crg_fixture_report( 'Sample – malware cleanup report' );
$img = $args[0] ?? __DIR__;

$meta = [
    'crg_client_name'  => 'Acme Bakery Ltd',
    'crg_site_url'     => 'https://acmebakery.example.com',
    'crg_report_date'  => '2026-11-06',
    'crg_request_link' => 'https://wpmudev.com/forums/topic/sample-one-time-malware-removal-request/',
    'crg_screenshots'  => [
        [ 'url' => crg_fixture_image( 'sample-scan-before', $img ), 'title' => 'Pre-cleanup: Defender scan', 'type' => 'before' ],
        [ 'url' => crg_fixture_image( 'sample-scan-after', $img ), 'title' => 'Post-cleanup: Defender scan', 'type' => 'after' ],
    ],

    'crg_infection_summary' =>
        '<p>The website was severely compromised by an aggressive, <strong>self-healing backdoor</strong> (tracked as <em>WPanel</em>), together with an obfuscated <strong>SEO spam / cloaking injector</strong>. The malware set up persistence across several layers of the installation to resist removal:</p>'
      . '<ul>'
      . '<li><strong>File system:</strong> backdoors dropped into hidden and legitimate-looking paths — <code>wp-content/.wpanel/agent.php</code>, <code>wp-content/mu-plugins/wp-locale-bootstrap.php</code>, <code>wp-content/languages/</code>, <code>wp-content/cache/</code>, and a standalone runner in <code>wp-content/uploads/wp-locale-cache/index.php</code>.</li>'
      . '<li><strong>Execution hijacking:</strong> loader routines injected into <code>wp-content/db.php</code>, <code>wp-content/advanced-cache.php</code>, the active theme\'s <code>functions.php</code> and the bootstrap files of almost every active plugin.</li>'
      . '<li><strong>Theme infection &amp; cloaking:</strong> the theme\'s <code>functions.php</code> held an AES-256 encrypted dropper that recreated deleted files on every page load, plus an XOR-obfuscated <code>wp_footer</code> hook that showed hidden backlinks and keywords to search engine crawlers only.</li>'
      . '<li><strong>Database &amp; scanner tampering:</strong> the full agent was stored encoded in <code>wp_options</code>, with self-heal transients and command-and-control heartbeats. The attacker also changed the security plugin\'s ignore list so scans would skip the malware.</li>'
      . '</ul>'
      . '<p>All malicious files, droppers, core modifications, infected theme scripts, database entries and encoded backups were identified and removed.</p>',

    // The source report has no counts; these follow its findings (6 persistence points, 5 risky components, 2 follow-ups).
    'crg_summary_critical' => 6,
    'crg_summary_medium'   => 5,
    'crg_summary_low'      => 2,

    'crg_highlights' =>
        '<ul>'
      . '<li><strong>Stopped re-infection:</strong> suspended server cron jobs and ended background PHP workers so running loops could not restore the malware.</li>'
      . '<li><strong>Removed core drop-ins and loaders:</strong> deleted the malicious <code>db.php</code>, <code>advanced-cache.php</code> and <code>object-cache.php</code>.</li>'
      . '<li><strong>Removed hidden backdoors:</strong> deleted the <code>wp-content/.wpanel/</code> agent folder, the fake localisation files in <code>languages/</code>, <code>cache/</code> and <code>upgrade/</code>, and the runner in <code>uploads/</code>.</li>'
      . '<li><strong>Cleaned must-use plugins:</strong> removed the unauthorised loader <code>mu-plugins/wp-locale-bootstrap.php</code>.</li>'
      . '<li><strong>Cleaned plugins:</strong> stripped injected code from every affected plugin and reinstalled clean copies from WordPress.org.</li>'
      . '<li><strong>Cleaned the theme:</strong> restored a clean child-theme <code>functions.php</code> (removing the encrypted dropper and the SEO cloaking hook) and cleaned the compromised parent-theme files.</li>'
      . '<li><strong>Cleaned the database:</strong> removed the encoded agent backup, self-heal transients, heartbeat tokens and registration keys from <code>wp_options</code>, and restored the security plugin\'s scan settings.</li>'
      . '<li><strong>Cleared caches:</strong> flushed the object cache, expired all transients and cleared cached packages so nothing infected is served again.</li>'
      . '</ul>',

    'crg_verification_summary' =>
        '<p>After cleanup, scans no longer find the backdoor, its loaders or the cloaking code. Defender still reports <strong>two files in the site root</strong>; they look like leftover legacy files — please review and remove them (see Pending actions).</p>',

    'crg_reinstall_list' =>
        '<ul>'
      . '<li>All-in-One WP Migration <code>all-in-one-wp-migration</code></li>'
      . '<li>BeTheme Header Builder <code>mfn-header-builder</code></li>'
      . '<li>Slider Revolution (revslider) – no licence provided</li>'
      . '<li>Checkout Field Editor for WooCommerce (Pro) (woocommerce-checkout-field-editor-pro)</li>'
      . '<li>WPBakery Page Builder <code>js_composer</code></li>'
      . '<li>Betheme (theme) <code>betheme</code></li>'
      . '<li>Betheme Child (theme) <code>betheme-child</code></li>'
      . '</ul>',
    'crg_replace_list' =>
        '<ul>'
      . '<li>WP Hardening <code>wp-security-hardening</code> — discontinued on WordPress.org; use Defender instead.</li>'
      . '<li>Theme Editor <code>theme-editor</code> — unfixed CSRF-to-remote-code-execution flaw; likely entry point. Delete it and edit files over SFTP/SSH.</li>'
      . '</ul>',

    'crg_components' =>
          "Theme Editor | 3.2 | Unfixed CSRF-to-remote-code-execution flaw (CVE-2026-39640); likely entry point | critical\n"
        . "Slider Revolution | 6.8.1 | 5 known vulnerabilities incl. arbitrary file access; update to 7.1.0+ | critical\n"
        . "Betheme (theme) | 29.0.5 | 4 open advisories allowing code injection; files modified by the attacker | high\n"
        . "WP Hardening | 1.2.8 | Discontinued on WordPress.org; will never receive security fixes | medium\n"
        . "Yoast Duplicate Post | 4.6 | Stored XSS and CSRF (CVE-2026-53740, CVE-2026-53739) | medium",
    'crg_improvements' =>
        '<ul>'
      . '<li><strong>Remove high-risk plugins:</strong> delete Theme Editor — manage server files only over SFTP/SSH, never from the dashboard.</li>'
      . '<li><strong>Use maintained security tools:</strong> remove WP Hardening and rely on Defender.</li>'
      . '<li><strong>Reduce the attack surface:</strong> if the homepage animations aren\'t essential, replace Slider Revolution with native blocks.</li>'
      . '</ul>',
    'crg_additional_sites' => 'No problem identified',
    'crg_delist'           => 'No problem identified',

    'crg_pending_actions' =>
          "1. Review the two files Defender still reports in the site root and delete them if they are legacy files.
"
        . "2. Review the user account linked to the injection. We changed its role to Subscriber; delete it if you don't recognise it.
"
        . "3. Finish the Defender settings marked Your action in section 06 (2FA, login URL, session security, bot protection).",

    'crg_documentation_links' =>
          "Defender documentation: https://wpmudev.com/docs/wpmu-dev-plugins/defender/\n"
        . "Supported PHP versions: https://www.php.net/supported-versions.php",

    'crg_seo_note' =>
          "IMPORTANT: The malware injected hidden spam for search engines, so Google may show unwanted content for your site for a while.\n\n"
        . "RECOMMENDED ACTIONS:\n"
        . "• Search Google for site:acmebakery.example.com and look for pharmacy pages or other text you don't recognise\n"
        . "• If you find any, resubmit your sitemap in Google Search Console and Bing Webmaster Tools\n\n"
        . "NOTE: It takes time for Google to update its results.",
    'crg_protect_note' =>
          "• Configure and enforce two-factor authentication with Defender\n"
        . "• Keep plugins and themes updated with WPMU DEV Automate\n"
        . "• Keep safe backups (included automatically on WPMU DEV hosting)\n"
        . "• Move any sites hosted elsewhere to WPMU DEV hosting to avoid cross-site infection\n"
        . "• Use WPMU DEV uptime monitoring — a spike in response time is often the first sign of an attack",
    'crg_review_status' => 'approved',
];

$defender = [
    'defender_recommendations'  => [ 'applied', 'Hardening recommendations reviewed and applied' ],
    'malware_scanning'          => [ 'applied', 'Issues scan enabled with scheduled reports' ],
    'audit_logging'             => [ 'applied', 'Helps you spot unwanted changes' ],
    'firewall_antibot'          => [ 'applied', 'Global AntiBot firewall and malicious bot detector on' ],
    'firewall_404'              => [ 'applied', 'Bans IPs that repeatedly request missing pages' ],
    'user_agent_banning'        => [ 'applied', 'Custom user-agent blocking rules' ],
    'user_agent_bot_trap'       => [ 'applied', 'Traps bots that ignore robots.txt; blocks fake crawlers' ],
    'user_agent_empty_headers'  => [ 'applied', 'Blocks requests with missing or empty headers' ],
    'user_agent_script_presets' => [ 'pending', 'Block automated scripts — needs your review of legitimate integrations' ],
    'two_factor_auth'           => [ 'pending', 'Each administrator must set up 2FA' ],
    'mask_login'                => [ 'pending', 'Choose a custom login URL and share it with your team' ],
    'security_headers'          => [ 'applied', 'Protects against code injection and clickjacking' ],
    'pwned_passwords'           => [ 'applied', 'Checks passwords against known breaches' ],
    'strong_passwords'          => [ 'applied', 'Weak passwords must be reset on next login' ],
    'password_reset'            => [ 'pending', 'Force all users to change their password after the breach' ],
    'session_protection'        => [ 'pending', 'Protects against session hijacking' ],
    'google_recaptcha'          => [ 'in_progress', 'Bot protection (CAPTCHA) for login and forms: we will turn this on once the contact form is tested' ],
    'ip_detection'              => [ 'applied', 'Visitor IP detection method set for this host' ],
];
// Checklists are saved the way the edit screen saves them (wording from Reports › Settings).
$followups = [
    'ssl'             => 'not_required',
    'cloudflare'      => 'required',
    'php_update'      => 'required',
    'check_admins'    => 'fixed',
    'reset_passwords' => 'required',
    'mask_login'      => 'required',
    'automate'        => 'required',
    'review_content'  => 'required',
];
Cleanup_Report_Checklists::save_report(
    $id,
    array_map( static function ( $status ) { return [ 'status' => $status, 'note' => '' ]; }, $followups ),
    array_map( static function ( $row ) { return [ 'status' => $row[0], 'notes' => $row[1] ]; }, $defender )
);
crg_fixture_save( $id, $meta );
