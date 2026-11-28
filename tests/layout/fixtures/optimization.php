<?php
/**
 * Fixture "optimization": a complete Website optimization report, modelled on the team's optimization report
 * template with an invented site (Northfield Gardens). The one to look at when judging the optimization design.
 *
 * wp eval-file optimization.php <fixture-image-dir>
 */
defined( 'WP_CLI' ) || exit;
require_once __DIR__ . '/_lib.php';

$id  = crg_fixture_report( 'Sample – website optimization report' );
$img = $args[0] ?? __DIR__;

/** Answers for every default test: key => [ status, note ]. */
function crg_fixture_checks( array $answers ) {
	$rows = [];
	foreach ( Cleanup_Report_Optimization::default_checks() as $def ) {
		[ $status, $note ] = $answers[ $def['key'] ] + [ '', '' ];
		$rows[ $def['key'] ] = [ 'key' => $def['key'], 'label' => $def['label'], 'good' => $def['good'], 'pre_note' => $def['pre_note'], 'post_note' => $def['post_note'], 'status' => $status, 'note' => $note ];
	}
	return $rows;
}

$shop_na = [ 'product_variation' => [ 'na' ], 'add_to_cart' => [ 'na' ], 'checkout' => [ 'na' ] ];

$code = <<<'CODE'
<?php
/**
 * Plugin Name: Optimization tweaks (WPMU DEV)
 * Description: Keeps Hummingbird's Delay JS and Critical CSS working with the theme's menu and the booking form.
 */

namespace Northfield_Gardens\Optimization;

defined( 'ABSPATH' ) || exit;

/** Scripts that must run straight away on every page. */
const ALWAYS_LOAD = array( 'northfield-mobile-menu', 'wp-hooks-js', 'wp-i18n-js' );

add_filter(
	'wphb_delay_js_exclusions',
	static function ( $exclusions ) {
		$exclusions = array_merge( $exclusions, ALWAYS_LOAD );

		// The booking form (page 412) needs jQuery and its validation before the first tap.
		if ( is_page( 412 ) ) {
			$exclusions = array_merge(
				$exclusions,
				array( 'jquery-core-js', 'forminator-jquery-validate-js', 'forminator-form-js', 'forminator-front-scripts-js' )
			);
		}

		return $exclusions;
	}
);

add_filter(
	'wphb_should_load_stylesheet_on_user_interaction',
	static function ( $delay, $stylesheet ) {
		if ( ! $delay || ! preg_match( '~\sid\s*=\s*(["\'])([a-zA-Z0-9_-]+)\1~', $stylesheet[0] ?? '', $match ) ) {
			return $delay;
		}
		// The navigation and the events grid are above the fold: load their styles at once to avoid a layout shift.
		return ! in_array( $match[2], array( 'widget-nav-menu-css', 'northfield-events-grid-css' ), true );
	},
	10,
	2
);

add_action(
	'wp_head',
	static function () {
		$script = <<<'JS'
(() => {
	const toggle = document.querySelector('.site-header .menu-toggle');
	const menu = document.querySelector('.site-header .main-navigation');
	if (!toggle || !menu) return;
	// Open the menu on the first tap, even while delayed scripts are still loading.
	toggle.addEventListener('click', (event) => {
		event.preventDefault();
		const open = toggle.getAttribute('aria-expanded') !== 'true';
		toggle.setAttribute('aria-expanded', String(open));
		menu.classList.toggle('toggled', open);
	}, { capture: true });
})();
JS;
		wp_print_inline_script_tag( $script, array( 'id' => 'northfield-mobile-menu' ) );
	},
	100
);

// A very long line, to check that code wraps inside the page margins instead of running off the page: aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
CODE;

$meta = [
	'crg_report_type'   => 'site_optimization',
	'crg_client_name'   => 'Northfield Gardens Trust',
	'crg_site_url'      => 'https://northfieldgardens.example.org',
	'crg_report_date'   => '2026-11-24',
	'crg_request_link'  => 'https://wpmudev.com/forums/topic/sample-one-time-site-speed-optimization-request/',
	'crg_opt_scores'    => implode( "\n", [
		'https://northfieldgardens.example.org/ | 52 | 81 | 93 | 100',
		'https://northfieldgardens.example.org/visit/ | 61 | 88 | 96 | 100',
		'https://northfieldgardens.example.org/events/ | 49 | 74 | 90 | 98',
		'https://northfieldgardens.example.org/2025/06/14/summer-plant-sale-and-open-garden-weekend/ | 90 | 89 | 99 | 100',
	] ),
	'crg_opt_json_link' => 'https://drive.google.com/file/d/sample-lighthouse-json/view',
	'crg_opt_targets'   => [ 'mobile' => 80, 'desktop' => 95 ],
	'crg_opt_pre_checks'  => crg_fixture_checks( [
		'menu'             => [ 'yes' ],
		'console_errors'   => [ 'no' ],
		'responsive'       => [ 'no' ],
		'layout'           => [ 'no' ],
		'cls'              => [ 'yes', 'The logo and the hero image load without their size, so the page moves down once they appear.' ],
		'backup'           => [ 'yes' ],
		'cloudflare'       => [ 'no' ],
		'other_issues'     => [ 'no' ],
		'initial_response' => [ 'yes', 'The member asked us to keep the booking form on Visit working.' ],
	] + $shop_na ),
	'crg_opt_pre_shots'   => [
		[ 'url' => crg_fixture_image( 'optimization-cls-before', $img ), 'title' => 'Home page while loading: the logo has no size yet, so the hero moves down', 'type' => 'other' ],
	],
	'crg_opt_steps'     =>
		'<p><strong>Theme (Astra):</strong></p><ul><li>Width and height added to the logo and hero images in Appearance › Customize, so the browser reserves their space.</li></ul>'
		. '<p><strong>Hummingbird:</strong></p><ul><li>Delay JS enabled in Hummingbird › Asset Optimization, with these exclusions: <code>wp-hooks-js</code>, <code>wp-i18n-js</code>, <code>northfield-mobile-menu</code>.</li>'
		. '<li>Critical CSS enabled in the "Above the fold optimization" mode, with <code>astra-theme-css</code> and <code>northfield-events-grid-css</code> excluded.</li>'
		. '<li>Page caching and browser caching checked; Gzip compression already active.</li></ul>'
		. '<p><strong>Smush:</strong></p><ul><li>"northfield-logo" excluded from Smush › Lazy Load &amp; Preload › Lazy Load.</li><li>Fetchpriority enabled in Smush › Lazy Load &amp; Preload › Preload.</li><li>Bulk smush run on 1,284 images; WebP delivery switched on.</li></ul>'
		. '<p><strong>Hosting:</strong></p><ul><li>Static Server Cache for mobile enabled in the Hub › the site › Hosting › Tools.</li></ul>'
		. '<p><strong>mu-plugin:</strong></p><p>We added a small plugin so Hummingbird\'s optimizations work with the theme\'s mobile menu and the booking form: the menu opens on the first tap, and the form loads with its styles and validation, without waiting for a tap or scroll.</p>',
	'crg_opt_code_path' => 'wp-content/mu-plugins/optimization-wpmu.php',
	'crg_opt_code'      => wp_slash( $code ), // update_post_meta() unslashes; the code has backslashes
	'crg_opt_summary'   => '<p>The main gains came from giving the logo and hero images their size (no more layout shift) and delaying non-essential scripts. The booking form and the mobile menu needed exclusions, which are kept in an mu-plugin so only the pages that need them load the extra scripts.</p>',
	'crg_opt_post_checks' => crg_fixture_checks( [
		'menu'             => [ 'yes' ],
		'console_errors'   => [ 'no' ],
		'responsive'       => [ 'no' ],
		'layout'           => [ 'no' ],
		'cls'              => [ 'no' ],
		'backup'           => [ 'yes' ],
		'cloudflare'       => [ 'no' ],
		'other_issues'     => [ 'no' ],
		'initial_response' => [ 'yes', 'The booking form works and keeps its styling.' ],
	] + $shop_na ),
	'crg_opt_post_shots' => [],
	'crg_review_status'  => 'approved',
];

crg_fixture_save( $id, $meta );
