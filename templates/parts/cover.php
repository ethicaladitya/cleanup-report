<?php
/**
 * The cover page, shared by every report type; the type sets $cover (see templates/types/*.php).
 *
 * Part of templates/report-pdf.php: included from inside Cleanup_Report_PDF::render_html(), so every variable here is
 * local to that method, not global.
 */
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- method scope, not global.
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<!-- ======================= COVER ======================= -->
<div class="cover">
    <?php if ( $glow_k > 0 ) : ?>
    <img class="glow" src="<?php echo esc_attr( $glow ); ?>" alt="">
    <img class="glow2" src="<?php echo esc_attr( $glow ); ?>" alt="">
    <?php endif; ?>
    <div class="brand">
        <?php if ( ! empty( $images['logo'] ) ) : ?>
            <img src="<?php echo esc_attr( $images['logo']['src'] ); ?>" alt="<?php echo esc_attr( $data['brand']['name'] ); ?>">
        <?php else : ?>
            <?php echo esc_html( $brand_short ); ?>
        <?php endif; ?>
    </div>
    <div class="conf"><?php esc_html_e( 'Confidential', 'cleanup-report-generator' ); ?></div>

    <div class="inner">
        <div class="eyebrow"><?php echo esc_html( $cover['eyebrow'] ); ?></div>
        <h1 style="font-size:<?php echo (int) $h1_size; ?>pt"><?php echo esc_html( $headline ); ?></h1>
        <?php if ( $prepared_for ) : ?><div class="site"><?php echo wp_kses( sprintf( /* translators: %s: client name */ __( 'Prepared for <strong>%s</strong>', 'cleanup-report-generator' ), esc_html( $prepared_for ) ), [ 'strong' => [] ] ); ?></div><?php endif; ?>
        <div class="chip <?php echo esc_attr( $cover['chip'][0] ); ?>"><?php echo esc_html( $cover['chip'][1] ); ?></div>
    </div>

    <div class="card">
        <table class="stats"><tr>
            <?php foreach ( $cover['stats'] as [ $stat_n, $stat_label ] ) : // $stat_n: HTML built by the type file from numbers ?>
            <td><div class="n"><?php echo wp_kses( $stat_n, [ 'small' => [] ] ); ?></div><div class="l"><?php echo esc_html( $stat_label ); ?></div></td>
            <?php endforeach; ?>
        </tr></table>
        <table class="meta"><tr>
            <td style="width:28%"><div class="k"><?php esc_html_e( 'Completed', 'cleanup-report-generator' ); ?></div><div class="v"><?php echo esc_html( $date ); ?></div></td>
            <td style="width:22%"><div class="k"><?php esc_html_e( 'Report ID', 'cleanup-report-generator' ); ?></div><div class="v"><?php echo esc_html( sprintf( /* translators: %d: report number */ __( '#%d', 'cleanup-report-generator' ), (int) $data['post_id'] ) ); ?></div></td>
            <td style="width:50%"><div class="k"><?php esc_html_e( 'Prepared by', 'cleanup-report-generator' ); ?></div><div class="v"><?php echo esc_html( sprintf( /* translators: 1: brand name, e.g. "Acme", 2: team, e.g. "Security Team" */ __( '%1$s %2$s', 'cleanup-report-generator' ), $brand_short, $cover['prepared_by'] ) ); ?></div></td>
        </tr></table>
    </div>

    <div class="foot"><span class="dot">●</span>&nbsp; <?php echo esc_html( $foot_name ? sprintf( /* translators: %s: client name or website */ __( 'Confidential — prepared for %s.', 'cleanup-report-generator' ), $foot_name ) : __( 'Confidential.', 'cleanup-report-generator' ) ); ?> <?php echo esc_html( $cover['foot'] ); ?></div>
</div>
