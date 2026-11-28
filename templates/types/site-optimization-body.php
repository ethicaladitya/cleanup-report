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
    $shots_title = 'pre' === $phase ? __( 'Relevant screenshots from the pre-work checklist', 'cleanup-report-generator' ) : __( 'Relevant screenshots from the post-work checklist', 'cleanup-report-generator' );
    ?>
    <div class="<?php echo count( $rows ) <= 12 ? 'keep' : 'flow'; ?>"><div class="head"><?php echo wp_kses_post( $h2( $phase ) ); ?></div>
    <?php if ( $rows ) : ?>
    <table class="grid">
        <thead><tr><th style="width:46%"><?php esc_html_e( 'Test', 'cleanup-report-generator' ); ?></th><th style="width:12%"><?php esc_html_e( 'Result', 'cleanup-report-generator' ); ?></th><th><?php esc_html_e( 'Notes', 'cleanup-report-generator' ); ?></th></tr></thead>
        <tbody>
        <?php foreach ( $rows as $row ) : ?>
            <tr><td class="name"><?php echo esc_html( $row['label'] ); ?></td><td><span class="pill <?php echo esc_attr( $pill[ $row['tone'] ] ?? 'na' ); ?>"><?php echo esc_html( $row['answer'] ); ?></span></td><td><?php echo wp_kses_post( make_clickable( esc_html( $row['note'] ) ) ); ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else : ?>
        <div class="box soft muted"><?php esc_html_e( 'No tests recorded.', 'cleanup-report-generator' ); ?></div>
    <?php endif; ?>
    </div>
    <?php if ( $shots ) : ?>
        <?php foreach ( $shots as $i => $shot ) :
            $img = $images[ $shot['url'] ] ?? null; ?>
            <div class="keep">
                <?php if ( 0 === $i ) : ?><h3><?php echo esc_html( $shots_title ); ?></h3><div class="sub"><?php echo esc_html( 'pre' === $phase ? __( 'Relevant visual identifications found in our pre-work checklist.', 'cleanup-report-generator' ) : __( 'Relevant visual identifications found in our post-work checklist.', 'cleanup-report-generator' ) ); ?></div><?php endif; ?>
                <div class="shot">
                    <?php if ( '' !== (string) $shot['title'] ) : ?><div class="cap"><span class="t"><?php echo esc_html( $shot['title'] ); ?></span></div><?php endif; ?>
                    <?php if ( $img ) : ?>
                        <img src="<?php echo esc_attr( $img['src'] ); ?>" width="<?php echo (int) $img['w']; ?>"<?php echo $img['h'] ? ' height="' . (int) $img['h'] . '"' : ''; ?> alt="">
                    <?php else : ?>
                        <div class="box soft muted"><?php esc_html_e( 'Screenshot unavailable.', 'cleanup-report-generator' ); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <div class="keep"><h3><?php echo esc_html( $shots_title ); ?></h3><p class="muted"><?php esc_html_e( 'No relevant screenshots.', 'cleanup-report-generator' ); ?></p></div>
    <?php endif; ?>
    <?php
};
?>
<!-- ======================= AT A GLANCE ======================= -->
<h1 class="page"><?php esc_html_e( 'At a glance', 'cleanup-report-generator' ); ?></h1>
<div class="lede"><?php echo esc_html( sprintf( /* translators: 1: website, 2: date */ __( 'A summary of the optimization of %1$s on %2$s.', 'cleanup-report-generator' ), $host ?: $client, $date ) ); ?></div>

<?php if ( ! $all_measured ) : ?>
    <div class="callout warn"><strong><?php esc_html_e( 'Scores after the work haven\'t been entered yet.', 'cleanup-report-generator' ); ?></strong> <?php esc_html_e( 'The result is shown once they are.', 'cleanup-report-generator' ); ?></div>
<?php elseif ( $missed ) : ?>
    <div class="callout warn"><strong><?php esc_html_e( 'Your site has been optimized.', 'cleanup-report-generator' ); ?></strong> <?php
    // Whole sentences per case ($missed keeps the order of $reached: mobile, then desktop).
    if ( 1 === count( $missed ) ) {
        $below = [
            /* translators: 1: average score after the work, 2: target score */
            'mobile'  => __( 'The mobile average (%1$s) is below the target of %2$d.', 'cleanup-report-generator' ),
            /* translators: 1: average score after the work, 2: target score */
            'desktop' => __( 'The desktop average (%1$s) is below the target of %2$d.', 'cleanup-report-generator' ),
        ];
        $device = $missed[0];
        echo esc_html( sprintf( $below[ $device ], call_user_func( $fmt, $avg[ $device . '_after' ]['avg'] ), (int) $targets[ $device ] ) );
    } else {
        echo esc_html(
            sprintf(
                /* translators: 1: mobile average after the work, 2: mobile target, 3: desktop average after the work, 4: desktop target */
                __( 'The mobile average (%1$s) is below the target of %2$d, and the desktop average (%3$s) is below the target of %4$d.', 'cleanup-report-generator' ),
                call_user_func( $fmt, $avg['mobile_after']['avg'] ),
                (int) $targets['mobile'],
                call_user_func( $fmt, $avg['desktop_after']['avg'] ),
                (int) $targets['desktop']
            )
        );
    }
    ?> <?php echo wp_kses( __( 'See <strong>Optimization result</strong>.', 'cleanup-report-generator' ), [ 'strong' => [] ] ); ?></div>
<?php else : ?>
    <div class="callout ok"><strong><?php esc_html_e( 'Your site has been optimized.', 'cleanup-report-generator' ); ?></strong> <?php echo esc_html( sprintf( /* translators: 1: mobile target score, 2: desktop target score */ __( 'Both average scores reached their targets: mobile %1$d, desktop %2$d.', 'cleanup-report-generator' ), (int) $targets['mobile'], (int) $targets['desktop'] ) ); ?></div>
<?php endif; ?>

<table class="tiles"><tr>
    <?php foreach ( $devices as $device => $label ) :
        $after_class = null === $reached[ $device ] ? 'total' : ( $reached[ $device ] ? 'low' : 'medium' ); ?>
    <td><div class="tile total"><div class="n"><?php echo esc_html( call_user_func( $fmt, $avg[ $device . '_before' ]['avg'] ) ); ?></div><div class="l"><?php echo esc_html( $label ); ?></div><div class="d"><?php esc_html_e( 'Before', 'cleanup-report-generator' ); ?></div></div></td>
    <td><div class="tile <?php echo esc_attr( $after_class ); ?>"><div class="n"><?php echo esc_html( call_user_func( $fmt, $avg[ $device . '_after' ]['avg'] ) ); ?></div><div class="l"><?php echo esc_html( $label ); ?></div><div class="d"><?php echo esc_html( sprintf( /* translators: %d: target score */ __( 'After · target %d', 'cleanup-report-generator' ), (int) $targets[ $device ] ) ); ?></div></div></td>
    <?php endforeach; ?>
</tr></table>

<table class="kpis"><tr>
    <td><div class="kpi"><div class="n"><?php echo esc_html( $gain( 'mobile' ) ); ?></div><div class="l"><?php esc_html_e( 'Mobile score change', 'cleanup-report-generator' ); ?></div></div></td>
    <td><div class="kpi"><div class="n"><?php echo esc_html( $gain( 'desktop' ) ); ?></div><div class="l"><?php esc_html_e( 'Desktop score change', 'cleanup-report-generator' ); ?></div></div></td>
    <td><div class="kpi"><div class="n"><?php echo count( $opt['pages'] ); ?></div><div class="l"><?php echo esc_html( _n( 'Page tested', 'Pages tested', count( $opt['pages'] ), 'cleanup-report-generator' ) ); ?></div></div></td>
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
    <div class="sub"><?php esc_html_e( 'PageSpeed Insights performance scores of the pages used for the average score, before and after the optimization.', 'cleanup-report-generator' ); ?></div></div>
    <?php if ( $opt['pages'] ) : ?>
    <table class="grid scores">
        <thead>
            <tr><th style="width:40%"><?php esc_html_e( 'Page', 'cleanup-report-generator' ); ?></th><th><?php esc_html_e( 'Mobile before', 'cleanup-report-generator' ); ?></th><th><?php esc_html_e( 'Mobile after', 'cleanup-report-generator' ); ?></th><th><?php esc_html_e( 'Desktop before', 'cleanup-report-generator' ); ?></th><th><?php esc_html_e( 'Desktop after', 'cleanup-report-generator' ); ?></th></tr>
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
                <td class="name"><?php esc_html_e( 'Average', 'cleanup-report-generator' ); ?></td>
                <?php foreach ( [ 'mobile_before', 'mobile_after', 'desktop_before', 'desktop_after' ] as $col ) :
                    $a = $avg[ $col ]; ?>
                    <td><?php if ( null === $a['avg'] ) : ?><span class="muted">—</span><?php else : ?><strong><?php echo esc_html( call_user_func( $fmt, $a['avg'] ) ); ?></strong><div class="muted" style="font-size:7pt"><?php echo (int) $a['sum'] . ' / ' . (int) $a['count']; ?></div><?php endif; ?></td>
                <?php endforeach; ?>
            </tr>
        </tbody>
    </table>
    <p class="muted" style="font-size:7.5pt"><?php esc_html_e( '* Score calculated by the sum of the individual pages\' score divided by the number of selected pages. Score calculated using a VPN to the closest server location.', 'cleanup-report-generator' ); ?><?php if ( $opt['json_link'] ) : ?> <?php echo wp_kses( sprintf( /* translators: 1: link to the shared JSON, 2: link to the Lighthouse viewer */ __( 'To view the data before the optimization, open the <a href="%1$s">shared JSON</a> in the <a href="%2$s">Lighthouse viewer</a>.', 'cleanup-report-generator' ), esc_url( $opt['json_link'] ), esc_url( Cleanup_Report_Optimization::LIGHTHOUSE ) ), [ 'a' => [ 'href' => [] ] ] ); ?><?php endif; ?></p>
    <?php else : ?>
        <div class="box soft muted"><?php esc_html_e( 'No scores entered.', 'cleanup-report-generator' ); ?></div>
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
    <div class="sub"><?php esc_html_e( 'For the site optimization, the following steps were taken:', 'cleanup-report-generator' ); ?></div></div>
    <?php echo wp_kses_post( $opt['steps'] ); ?>
    <?php if ( '' !== trim( $opt['code'] ) ) : ?>
        <div class="box soft keep" style="margin-top:6pt"><?php
        echo $opt['code_path']
            /* translators: %s: file the code was added to */
            ? wp_kses( sprintf( __( 'We also added custom code to the site, in <code>%s</code>.', 'cleanup-report-generator' ), esc_html( $opt['code_path'] ) ), [ 'code' => [] ] )
            : esc_html__( 'We also added custom code to the site.', 'cleanup-report-generator' );
        ?> <?php echo wp_kses( __( 'It\'s shown in full in <strong>Code added to your site</strong>, at the end of this report.', 'cleanup-report-generator' ), [ 'strong' => [] ] ); ?></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ======================= RESULT ======================= -->
<div class="sec">
    <div class="keep">
        <?php echo wp_kses_post( $h2( 'result' ) ); ?>
        <table class="grid">
            <thead><tr><th style="width:52%"><?php esc_html_e( 'Question', 'cleanup-report-generator' ); ?></th><th style="width:14%"><?php esc_html_e( 'Result', 'cleanup-report-generator' ); ?></th><th><?php esc_html_e( 'Average after the work', 'cleanup-report-generator' ); ?></th></tr></thead>
            <tbody>
            <?php
            $question = [
                /* translators: %d: target average score */
                'mobile'  => __( 'Was the expected score reached for mobile? (%d avg)', 'cleanup-report-generator' ),
                /* translators: %d: target average score */
                'desktop' => __( 'Was the expected score reached for desktop? (%d avg)', 'cleanup-report-generator' ),
            ];
            foreach ( $devices as $device => $label ) :
                $r = $reached[ $device ]; ?>
                <tr>
                    <td class="name"><?php echo esc_html( sprintf( $question[ $device ], (int) $targets[ $device ] ) ); ?></td>
                    <td><?php if ( null === $r ) : ?><span class="pill na"><?php esc_html_e( 'Not measured', 'cleanup-report-generator' ); ?></span><?php else : ?><span class="pill <?php echo $r ? 'ok' : 'todo'; ?>"><?php echo esc_html( $r ? __( 'Yes', 'cleanup-report-generator' ) : __( 'No', 'cleanup-report-generator' ) ); ?></span><?php endif; ?></td>
                    <td><?php echo esc_html( call_user_func( $fmt, $avg[ $device . '_after' ]['avg'] ) ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ( $has( $opt['summary'] ) ) : ?>
        <div class="head"><h3><?php esc_html_e( 'Optimization summary', 'cleanup-report-generator' ); ?></h3></div>
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
        <div class="t"><?php echo esc_html( sprintf( /* translators: %s: brand name */ __( 'Thank you for trusting %s.', 'cleanup-report-generator' ), $data['brand']['name'] ) ); ?></div>
        <?php if ( $data['request_link'] && filter_var( $data['request_link'], FILTER_VALIDATE_URL ) ) : ?>
            <?php echo wp_kses_post( sprintf( /* translators: %s: link to the member's support request */ __( 'Questions about this report? Reply on your <a href="%s">support request</a> and our team will help.', 'cleanup-report-generator' ), esc_url( $data['request_link'] ) ) ); ?>
        <?php else : ?>
            <?php esc_html_e( 'Questions about this report? Contact our support team and we\'ll be happy to help.', 'cleanup-report-generator' ); ?>
        <?php endif; ?>
    </div>
</div>

<?php if ( isset( $sections['code'] ) ) : ?>
<!-- ======================= CODE ======================= -->
<div class="sec">
    <div class="head"><?php echo wp_kses_post( $h2( 'code' ) ); ?>
    <div class="sub"><?php echo $opt['code_path'] ? wp_kses( sprintf( /* translators: %s: file the code was added to */ __( 'Added as <code>%s</code>.', 'cleanup-report-generator' ), esc_html( $opt['code_path'] ) ), [ 'code' => [] ] ) : esc_html__( 'Added to your site during the optimization.', 'cleanup-report-generator' ); ?></div></div>
    <pre class="code"><?php echo esc_html( str_replace( "\t", '    ', $opt['code'] ) ); ?></pre>
</div>
<?php endif; ?>
