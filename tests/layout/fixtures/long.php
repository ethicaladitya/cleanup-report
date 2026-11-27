<?php
/**
 * Fixture "long": every field at its worst — a very long client name and URL, 6 screenshots, pages of text,
 * 25 components, 20 pending actions, long tables. Catches overflow, clipping and stranded headings.
 *
 * wp eval-file long.php <fixture-image-dir>
 */
defined( 'WP_CLI' ) || exit;
require_once __DIR__ . '/_lib.php';

$id  = crg_fixture_report( 'Layout test – long' );
$img = $args[0] ?? __DIR__;
$b   = crg_fixture_image( 'sample-scan-before', $img );
$a   = crg_fixture_image( 'sample-scan-after', $img );

$para = '<p>The attacker used several stages of obfuscation, including base64-encoded payloads, string reversal and dynamic function names built at runtime, which made the code hard to spot by reading the files. Each stage decoded the next and wrote it to a different location so that deleting one file did not stop the infection.</p>';
$items = '';
for ( $i = 1; $i <= 18; $i++ ) { $items .= "<li><strong>Finding {$i}:</strong> malicious loader found in <code>wp-content/plugins/plugin-{$i}/includes/class-loader-{$i}.php</code>, injected at the top of the file and calling a remote command server.</li>"; }
$comp = 'ThisPluginSlugHasNoSpacesAtAllAndKeepsGoingWellPastTheWidthOfTheColumn | 1.0 | ' . str_repeat( 'x', 120 ) . ' | critical' . "\n";
for ( $i = 1; $i <= 25; $i++ ) { $comp .= "Plugin number {$i} with a fairly long name | " . ( $i % 3 ? '1.' . $i . '.0' : '12.3.4-beta.' . $i ) . " | Known vulnerability allowing authenticated users to upload arbitrary files and execute code (CVE-2026-{$i}000{$i}) | " . [ 'critical', 'medium', 'low' ][ $i % 3 ] . "\n"; }
$pending = '';
for ( $i = 1; $i <= 20; $i++ ) { $pending .= "{$i}. Pending action number {$i}: review this item carefully and confirm it's done before the next scheduled scan.\n"; }
$list = '<ul>';
for ( $i = 1; $i <= 14; $i++ ) { $list .= "<li>Premium plugin {$i} <code>premium-plugin-{$i}</code></li>"; }
$list .= '</ul>';

$m = [
    'crg_client_name' => 'Very Long Client Name International Holdings and Subsidiaries Limited (Europe, Middle East & Africa) — Online Retail, Wholesale and Franchise Division',
    'crg_site_url' => 'https://a-really-long-subdomain-name.with-another-part.and-a-third-part.example-company-website.co.uk',
    'crg_report_date' => '2026-11-06',
    'crg_screenshots' => [
        [ 'url' => $b, 'title' => 'Before 1', 'type' => 'before' ], [ 'url' => $a, 'title' => 'After 1', 'type' => 'after' ],
        [ 'url' => $b, 'title' => 'A screenshot with a very long caption that goes on and on to see how the caption wraps across the width of the page', 'type' => 'evidence' ],
        [ 'url' => $a, 'title' => 'After 2', 'type' => 'after' ], [ 'url' => $b, 'title' => 'Before 2', 'type' => 'before' ], [ 'url' => $a, 'title' => 'Other', 'type' => 'other' ],
    ],
    'crg_infection_summary' => '<p>Payload found in the database: <code>' . str_repeat( 'ZXZhbChiYXNlNjRfZGVjb2RlKCRfUE9TVFsieCJdKSk7', 8 ) . '</code> and loaded from https://malicious-command-server.example.net/' . str_repeat( 'a1b2c3d4e5', 12 ) . '.php</p>' . str_repeat( $para, 6 ) . '<ul>' . $items . '</ul>' . str_repeat( $para, 3 ),
    'crg_summary_critical' => 128, 'crg_summary_medium' => 1045, 'crg_summary_low' => 77,
    'crg_components' => $comp,
    'crg_improvements' => '<ul>' . str_repeat( '<li>Improvement suggestion with enough words to wrap onto a second line in the PDF layout.</li>', 8 ) . '</ul>',
    'crg_highlights' => '<ul>' . str_repeat( '<li><strong>Action:</strong> removed a backdoor from <code>wp-content/uploads/2026/11/some-very-long-file-name-that-will-wrap.php</code> and verified the file was not recreated.</li>', 12 ) . '</ul>',
    'crg_verification_summary' => str_repeat( $para, 2 ),
    'crg_additional_sites' => "staging.example-company-website.co.uk: scanned, clean.\nshop.example-company-website.co.uk: scanned, clean.\nblog.example-company-website.co.uk: scanned, clean.",
    'crg_delist' => "Listed on Google Safe Browsing; review requested on 6 Nov 2026.\nListed on McAfee SiteAdvisor; delist request sent on 6 Nov 2026.",
    'crg_reinstall_list' => $list,
    'crg_replace_list' => '<ul>' . str_repeat( '<li>Abandoned plugin <code>abandoned-plugin</code> — not updated since 2019 and has known unpatched vulnerabilities</li>', 6 ) . '</ul>',
    'crg_pending_actions' => $pending,
    'crg_documentation_links' => str_repeat( "Some documentation page: https://wpmudev.com/docs/\n", 1 ),
];
// Old (2.0) one-key-per-item storage on purpose: checks that reports saved by earlier versions still render.
foreach ( [ 'ssl', 'cloudflare', 'check_admins', 'reset_passwords', 'mask_login', 'php_update', 'automate', 'review_content' ] as $k ) { $m[ 'crg_' . $k ] = 'required'; }
foreach ( [ 'defender_recommendations', 'malware_scanning', 'audit_logging', 'firewall_antibot', 'firewall_404', 'user_agent_banning', 'user_agent_bot_trap', 'user_agent_empty_headers', 'user_agent_script_presets', 'two_factor_auth', 'mask_login', 'security_headers', 'pwned_passwords', 'strong_passwords', 'password_reset', 'session_protection', 'google_recaptcha', 'ip_detection' ] as $i => $k ) {
    $m[ 'crg_defender_' . $k ] = $i % 2 ? 'pending' : 'applied';
    $m[ 'crg_defender_' . $k . '_notes' ] = $i % 4 ? 'A longer note that explains exactly what was configured and why it matters for this particular website.' : '';
}
$m['crg_review_status'] = 'approved'; // the final report; unapproved ones carry a "NOT FINAL" mark
crg_fixture_save( $id, $m );
