<?php
/**
 * Website optimization report: the summary page and the numbered sections.
 *
 * Part of templates/report-pdf.php: included from inside Cleanup_Report_PDF::render_html(), so every variable here is
 * local to that method, not global.
 */
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- method scope, not global.
if ( ! defined( 'ABSPATH' ) ) { exit; }

$opt_defaults = Cleanup_Report_Optimization::default_texts();

// One checklist table (pre- or post-work), then its screenshots.
$checklist = static function ( $phase, array $rows ) use ( $opt, $images, $h2 ) {
    $pill  = [ 'ok' => 'ok', 'bad' => 'todo', 'info' => 'low', 'na' => 'na' ];
    $shots = $opt['shots'][ $phase ];
    ?>
    <div class="<?php echo count( $rows ) <= 12 ? 'keep' : 'flow'; ?>"><div class="head"><?php echo wp_kses_post( $h2( $phase ) ); ?></div>
    <?php if ( $rows ) : ?>
    <table class="grid">
        <thead><tr><th style="width:46%">Test</th><th style="width:12%">Result</th><th>Notes</th></tr></thead>
        <tbody>
        <?php foreach ( $rows as $row ) : ?>
            <tr><td class="name"><?php echo esc_html( $row['label'] ); ?></td><td><span class="pill <?php echo esc_attr( $pill[ $row['tone'] ] ?? 'na' ); ?>"><?php echo esc_html( $row['answer'] ); ?></span></td><td><?php echo wp_kses_post( make_clickable( esc_html( $row['note'] ) ) ); ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else : ?>
        <div class="box soft muted">No tests recorded.</div>
    <?php endif; ?>
    </div>
    <?php if ( $shots ) : ?>
        <?php foreach ( $shots as $i => $shot ) :
            $img = $images[ $shot['url'] ] ?? null; ?>
            <div class="keep">
                <?php if ( 0 === $i ) : ?><h3>Relevant screenshots from the <?php echo 'pre' === $phase ? 'pre-work' : 'post-work'; ?> checklist</h3><div class="sub">Relevant visual identifications found in our <?php echo 'pre' === $phase ? 'pre-work' : 'post-work'; ?> checklist.</div><?php endif; ?>
                <div class="shot">
                    <?php if ( '' !== (string) $shot['title'] ) : ?><div class="cap"><span class="t"><?php echo esc_html( $shot['title'] ); ?></span></div><?php endif; ?>
                    <?php if ( $img ) : ?>
                        <img src="<?php echo esc_attr( $img['src'] ); ?>" width="<?php echo (int) $img['w']; ?>"<?php echo $img['h'] ? ' height="' . (int) $img['h'] . '"' : ''; ?> alt="">
                    <?php else : ?>
                        <div class="box soft muted">Screenshot unavailable.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <div class="keep"><h3>Relevant screenshots from the <?php echo 'pre' === $phase ? 'pre-work' : 'post-work'; ?> checklist</h3><p class="muted">No relevant screenshots.</p></div>
    <?php endif; ?>
    <?php
};
?>
<!-- ======================= AT A GLANCE ======================= -->
<h1 class="page">At a glance</h1>
<div class="lede">A summary of the optimization of <?php echo esc_html( $host ?: $client ); ?> on <?php echo esc_html( $date ); ?>.</div>

<?php if ( ! $all_measured ) : ?>
    <div class="callout warn"><strong>Scores after the work haven't been entered yet.</strong> The result is shown once they are.</div>
<?php elseif ( $missed ) : ?>
    <div class="callout warn"><strong>Your site has been optimized.</strong> <?php
    $parts = [];
    foreach ( $missed as $device ) {
        $parts[] = 'the ' . strtolower( $devices[ $device ] ) . ' average (' . call_user_func( $fmt, $avg[ $device . '_after' ]['avg'] ) . ') is below the target of ' . (int) $targets[ $device ];
    }
    echo esc_html( ucfirst( implode( ', and ', $parts ) ) . '.' );
    ?> See <strong>Optimization result</strong>.</div>
<?php else : ?>
    <div class="callout ok"><strong>Your site has been optimized.</strong> Both average scores reached their targets: mobile <?php echo (int) $targets['mobile']; ?>, desktop <?php echo (int) $targets['desktop']; ?>.</div>
<?php endif; ?>

<table class="tiles"><tr>
    <?php foreach ( $devices as $device => $label ) :
        $after_class = null === $reached[ $device ] ? 'total' : ( $reached[ $device ] ? 'low' : 'medium' ); ?>
    <td><div class="tile total"><div class="n"><?php echo esc_html( call_user_func( $fmt, $avg[ $device . '_before' ]['avg'] ) ); ?></div><div class="l"><?php echo esc_html( $label ); ?></div><div class="d">Before</div></div></td>
    <td><div class="tile <?php echo esc_attr( $after_class ); ?>"><div class="n"><?php echo esc_html( call_user_func( $fmt, $avg[ $device . '_after' ]['avg'] ) ); ?></div><div class="l"><?php echo esc_html( $label ); ?></div><div class="d">After · target <?php echo (int) $targets[ $device ]; ?></div></div></td>
    <?php endforeach; ?>
</tr></table>

<table class="kpis"><tr>
    <td><div class="kpi"><div class="n"><?php echo esc_html( $gain( 'mobile' ) ); ?></div><div class="l">Mobile score change</div></div></td>
    <td><div class="kpi"><div class="n"><?php echo esc_html( $gain( 'desktop' ) ); ?></div><div class="l">Desktop score change</div></div></td>
    <td><div class="kpi"><div class="n"><?php echo count( $opt['pages'] ); ?></div><div class="l"><?php echo 1 === count( $opt['pages'] ) ? 'Page tested' : 'Pages tested'; ?></div></div></td>
</tr></table>

<table class="toc">
    <?php foreach ( $sections as $key => $title ) : ?>
        <tr><td class="n"><?php echo esc_html( $num( $key ) ); ?></td><td><?php echo esc_html( $title ); ?></td></tr>
    <?php endforeach; ?>
</table>

<div class="break"></div>

<!-- ======================= SCORES ======================= -->
<div class="sec first">
    <div class="<?php echo count( $opt['pages'] ) <= 8 ? 'keep' : 'flow'; ?>"><div class="head"><?php echo wp_kses_post( $h2( 'scores' ) ); ?>
    <div class="sub">PageSpeed Insights performance scores of the pages used for the average score, before and after the optimization.</div></div>
    <?php if ( $opt['pages'] ) : ?>
    <table class="grid scores">
        <thead>
            <tr><th style="width:40%">Page</th><th>Mobile before</th><th>Mobile after</th><th>Desktop before</th><th>Desktop after</th></tr>
        </thead>
        <tbody>
        <?php foreach ( $opt['pages'] as $page ) : ?>
            <tr>
                <td class="name"><?php echo esc_html( preg_replace( '#^https?://(www\.)?#', '', $page['url'] ) ); ?></td>
                <?php foreach ( [ 'mobile_before', 'mobile_after', 'desktop_before', 'desktop_after' ] as $col ) : ?>
                    <td><?php echo null === $page[ $col ] ? '<span class="muted">—</span>' : '<span class="pill ' . esc_attr( $band( $page[ $col ] ) ) . '">' . (int) $page[ $col ] . '</span>'; ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
            <tr class="avg">
                <td class="name">Average</td>
                <?php foreach ( [ 'mobile_before', 'mobile_after', 'desktop_before', 'desktop_after' ] as $col ) :
                    $a = $avg[ $col ]; ?>
                    <td><?php if ( null === $a['avg'] ) : ?><span class="muted">—</span><?php else : ?><strong><?php echo esc_html( call_user_func( $fmt, $a['avg'] ) ); ?></strong><div class="muted" style="font-size:7pt"><?php echo (int) $a['sum'] . ' / ' . (int) $a['count']; ?></div><?php endif; ?></td>
                <?php endforeach; ?>
            </tr>
        </tbody>
    </table>
    <p class="muted" style="font-size:7.5pt">* Score calculated by the sum of the individual pages' score divided by the number of selected pages. Score calculated using a VPN to the closest server location.<?php if ( $opt['json_link'] ) : ?> To view the data before the optimization, open the <a href="<?php echo esc_url( $opt['json_link'] ); ?>">shared JSON</a> in the <a href="<?php echo esc_url( Cleanup_Report_Optimization::LIGHTHOUSE ); ?>">Lighthouse viewer</a>.<?php endif; ?></p>
    <?php else : ?>
        <div class="box soft muted">No scores entered.</div>
    <?php endif; ?>
    </div>
</div>

<!-- ======================= PRE-WORK CHECKLIST ======================= -->
<div class="sec">
    <?php $checklist( 'pre', $pre_checks ); ?>
</div>

<?php if ( isset( $sections['steps'] ) ) : ?>
<!-- ======================= OPTIMIZATION STEPS ======================= -->
<div class="sec">
    <div class="head"><?php echo wp_kses_post( $h2( 'steps' ) ); ?>
    <div class="sub">For the site optimization, the following steps were taken:</div></div>
    <?php echo wp_kses_post( $opt['steps'] ); ?>
    <?php if ( '' !== trim( $opt['code'] ) ) : ?>
        <div class="box soft keep" style="margin-top:6pt">We also added custom code to the site<?php echo $opt['code_path'] ? ', in <code>' . esc_html( $opt['code_path'] ) . '</code>' : ''; ?>. It's shown in full in <strong>Code added to your site</strong>, at the end of this report.</div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ======================= RESULT ======================= -->
<div class="sec">
    <div class="keep">
        <?php echo wp_kses_post( $h2( 'result' ) ); ?>
        <table class="grid">
            <thead><tr><th style="width:52%">Question</th><th style="width:14%">Result</th><th>Average after the work</th></tr></thead>
            <tbody>
            <?php foreach ( $devices as $device => $label ) :
                $r = $reached[ $device ]; ?>
                <tr>
                    <td class="name">Was the expected score reached for <?php echo esc_html( strtolower( $label ) ); ?>? (<?php echo (int) $targets[ $device ]; ?> avg)</td>
                    <td><?php if ( null === $r ) : ?><span class="pill na">Not measured</span><?php else : ?><span class="pill <?php echo $r ? 'ok' : 'todo'; ?>"><?php echo $r ? 'Yes' : 'No'; ?></span><?php endif; ?></td>
                    <td><?php echo esc_html( call_user_func( $fmt, $avg[ $device . '_after' ]['avg'] ) ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ( $has( $opt['summary'] ) ) : ?>
        <div class="head"><h3>Optimization summary</h3></div>
        <?php echo wp_kses_post( $opt['summary'] ); ?>
    <?php endif; ?>
</div>

<!-- ======================= POST-WORK CHECKLIST ======================= -->
<div class="sec">
    <?php $checklist( 'post', $post_checks ); ?>
</div>

<!-- ======================= HELPFUL INFORMATION ======================= -->
<div class="sec">
    <div class="keep"><?php echo wp_kses_post( $h2( 'info' ) ); ?>
    <?php echo wp_kses_post( $note_html( $opt['info'] ?: $opt_defaults['info'] ) ); ?></div>
    <div class="box soft"><?php echo wp_kses_post( $note_html( $opt['faq'] ?: $opt_defaults['faq'] ) ); ?></div>

    <div class="closing keep">
        <div class="t">Thank you for trusting <?php echo esc_html( $data['brand']['name'] ); ?>.</div>
        <?php if ( $data['request_link'] && filter_var( $data['request_link'], FILTER_VALIDATE_URL ) ) : ?>
            Questions about this report? Reply on your <a href="<?php echo esc_url( $data['request_link'] ); ?>">support request</a> and our team will help.
        <?php else : ?>
            Questions about this report? Contact our support team and we'll be happy to help.
        <?php endif; ?>
    </div>
</div>

<?php if ( isset( $sections['code'] ) ) : ?>
<!-- ======================= CODE ======================= -->
<div class="sec">
    <div class="head"><?php echo wp_kses_post( $h2( 'code' ) ); ?>
    <div class="sub"><?php echo $opt['code_path'] ? 'Added as <code>' . esc_html( $opt['code_path'] ) . '</code>.' : 'Added to your site during the optimization.'; ?></div></div>
    <pre class="code"><?php echo esc_html( str_replace( "\t", '    ', $opt['code'] ) ); ?></pre>
</div>
<?php endif; ?>
