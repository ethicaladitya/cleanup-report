<?php
/**
 * Generic report: the summary page and one numbered section per section of the definition, each block drawn by its
 * kind (see Cleanup_Report_Render::generic_sections()).
 *
 * Part of templates/report-pdf.php: included from inside Cleanup_Report_PDF::render_html(), so every variable here is
 * local to that method, not global.
 */
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- method scope, not global.
if ( ! defined( 'ABSPATH' ) ) { exit; }

$pill = [ 'ok' => 'ok', 'bad' => 'todo', 'info' => 'low', 'na' => 'na' ];
?>
<!-- ======================= AT A GLANCE ======================= -->
<h1 class="page"><?php esc_html_e( 'At a glance', 'cleanup-report-generator' ); ?></h1>
<div class="lede"><?php echo esc_html( sprintf( /* translators: 1: report type, 2: website, 3: date */ __( 'A summary of the %1$s of %2$s on %3$s.', 'cleanup-report-generator' ), strtolower( $type_info['label'] ), $host ?: $client, $date ) ); ?></div>

<?php if ( $sections ) : ?>
<table class="toc">
    <?php foreach ( $sections as $key => $title ) : ?>
        <tr><td class="n"><?php echo esc_html( $num( $key ) ); ?></td><td><?php echo esc_html( $title ); ?></td></tr>
    <?php endforeach; ?>
</table>
<?php else : ?>
    <div class="box soft muted"><?php esc_html_e( 'Nothing has been filled in yet.', 'cleanup-report-generator' ); ?></div>
<?php endif; ?>

<div class="break"></div>

<?php foreach ( $generic as $i => $section ) : $key = 's' . $i; ?>
<div class="sec<?php echo 0 === $i ? ' first' : ''; ?>">
    <?php foreach ( $section['blocks'] as $b => $block ) :
        $heading = 0 === $b ? wp_kses_post( $h2( $key ) ) : '';
        $label   = '' !== (string) ( $block['label'] ?? '' ) && ( 0 !== $b || count( $section['blocks'] ) > 1 ) ? '<h3>' . esc_html( $block['label'] ) . '</h3>' : ''; ?>
        <?php if ( 'facts' === $block['kind'] ) : ?>
            <div class="<?php echo count( $block['rows'] ) <= 8 ? 'keep' : 'flow'; ?>"><?php echo $heading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>
            <table class="grid"><tbody>
                <?php foreach ( $block['rows'] as [ $fact_label, $fact_value ] ) : ?>
                    <tr><td class="name" style="width:38%"><?php echo esc_html( $fact_label ); ?></td><td><?php echo wp_kses_post( make_clickable( esc_html( $fact_value ) ) ); ?></td></tr>
                <?php endforeach; ?>
            </tbody></table></div>
        <?php elseif ( 'html' === $block['kind'] ) : ?>
            <div class="head"><?php echo $heading . $label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?></div>
            <?php echo wp_kses_post( $block['html'] ); ?>
        <?php elseif ( 'table' === $block['kind'] ) : ?>
            <div class="<?php echo count( $block['rows'] ) <= 6 ? 'keep' : 'flow'; ?>"><div class="head"><?php echo $heading . $label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?></div>
            <table class="grid">
                <thead><tr><?php foreach ( $block['columns'] as $col ) : ?><th><?php echo esc_html( $col ); ?></th><?php endforeach; ?></tr></thead>
                <tbody><?php foreach ( $block['rows'] as $row ) : ?><tr><?php foreach ( $row as $c => $cell ) : ?><td<?php echo 0 === $c ? ' class="name"' : ''; ?>><?php echo esc_html( $cell ); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
            </table></div>
        <?php elseif ( 'checks' === $block['kind'] ) : ?>
            <div class="<?php echo count( $block['rows'] ) <= 12 ? 'keep' : 'flow'; ?>"><div class="head"><?php echo $heading . $label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?></div>
            <table class="grid">
                <thead><tr><th style="width:46%"><?php esc_html_e( 'Check', 'cleanup-report-generator' ); ?></th><th style="width:12%"><?php esc_html_e( 'Result', 'cleanup-report-generator' ); ?></th><th><?php esc_html_e( 'Notes', 'cleanup-report-generator' ); ?></th></tr></thead>
                <tbody><?php foreach ( $block['rows'] as $row ) : ?><tr><td class="name"><?php echo esc_html( $row['label'] ); ?></td><td><span class="pill <?php echo esc_attr( $pill[ $row['tone'] ] ?? 'na' ); ?>"><?php echo esc_html( $row['answer'] ); ?></span></td><td><?php echo wp_kses_post( make_clickable( esc_html( $row['note'] ) ) ); ?></td></tr><?php endforeach; ?></tbody>
            </table></div>
        <?php elseif ( 'shots' === $block['kind'] ) : ?>
            <?php foreach ( $block['shots'] as $s => $shot ) : $img = $images[ $shot['url'] ] ?? null; ?>
                <div class="keep"><?php echo 0 === $s ? $heading . $label : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>
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
        <?php elseif ( 'code' === $block['kind'] ) : ?>
            <div class="head"><?php echo $heading . $label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?><?php if ( $block['file'] ) : ?><div class="sub"><code><?php echo esc_html( $block['file'] ); ?></code></div><?php endif; ?></div>
            <pre class="code"><?php echo esc_html( str_replace( "\t", '    ', $block['code'] ) ); ?></pre>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<div class="sec">
    <div class="closing keep">
        <div class="t"><?php echo esc_html( sprintf( /* translators: %s: brand name */ __( 'Thank you for trusting %s.', 'cleanup-report-generator' ), $data['brand']['name'] ) ); ?></div>
        <?php if ( $data['request_link'] && filter_var( $data['request_link'], FILTER_VALIDATE_URL ) ) : ?>
            <?php echo wp_kses_post( sprintf( /* translators: %s: link to the member's support request */ __( 'Questions about this report? Reply on your <a href="%s">support request</a> and our team will help.', 'cleanup-report-generator' ), esc_url( $data['request_link'] ) ) ); ?>
        <?php else : ?>
            <?php esc_html_e( 'Questions about this report? Contact our support team and we\'ll be happy to help.', 'cleanup-report-generator' ); ?>
        <?php endif; ?>
    </div>
</div>
