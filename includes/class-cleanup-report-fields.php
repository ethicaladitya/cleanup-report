<?php
/**
 * Field types: how each field of a report type (Cleanup_Report_Types::definitions()) is shown on the edit screen,
 * saved, and listed in the history. A report type is a list of sections, each a list of fields; nothing here knows
 * which type it's drawing.
 *
 * Every field: key (post meta, crg_*), type, label, help (may hold <code>, <strong>, <em>), and options by type:
 *  - text, url, date, number, email: placeholder, default, default_new (only on a new report), class, url ('site' or
 *    'link': tidied as you type), min/max (number)
 *  - textarea: rows, placeholder · code: rows, path_key / path_label / path_placeholder (the file it's in)
 *  - editor (rich text): rows, default_new, legacy_text (convert plain text saved by older versions)
 *  - note (rich text with a standard text used when it's left empty): standard (callable( WP_Post ): string)
 *  - select: answers (value => label), note (true: a note beside it, saved as <key>_note)
 *  - questions: several selects with notes, questions (key => [ question, report label, answers, note placeholder ]),
 *    each saved as crg_<key> and crg_<key>_note
 *  - table: columns (name => [ label, placeholder ]): rows typed in a table, saved as "a | b | c" lines
 *  - screenshots: typed (before/after/evidence badge), default_rows (on a new report)
 *  - checklist: items (callable(): settings items), statuses (value => label), note_from (item field holding the default
 *    note), settings (Settings tab where the items are edited)
 * Bespoke types for the built-in reports: key_finding, severity_counts, plugin_check, components, blocklists, defender,
 * followups, scores. A field may also bring its own 'render' and 'save' callables.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Cleanup_Report_Fields {

    /** Rich-text editor settings shared by every editor on the form (the PDF only uses these tags). */
    const EDITOR = [
        'media_buttons' => false,
        'teeny'         => false,
        'tinymce'       => [ 'toolbar1' => 'bold,italic,bullist,numlist,link,unlink,undo,redo', 'toolbar2' => '' ],
        'quicktags'     => [ 'buttons' => 'strong,em,ul,ol,li,link,code,close' ],
    ];

    const HELP_TAGS = [ 'code' => [], 'strong' => [], 'em' => [], 'button' => [ 'type' => [], 'class' => [], 'hidden' => [] ] ];

    /** A field with every option it may use filled in. */
    public static function defaults( array $f ) {
        return $f + [ 'key' => '', 'type' => 'text', 'label' => '', 'help' => '', 'placeholder' => '', 'default' => '', 'default_new' => null, 'class' => '', 'rows' => 4, 'url' => '', 'report' => true ];
    }

    /* ================================================================== edit screen */

    /** One section (an edit-screen box): its fields, side by side when the section has columns. */
    public static function render_section( $post, array $section ) {
        $cols = (int) ( $section['columns'] ?? 0 );
        if ( $cols ) {
            echo '<div class="crg-cols crg-cols-' . (int) $cols . '">';
        }
        foreach ( $section['fields'] as $f ) {
            self::render( $post, self::defaults( $f ) );
        }
        if ( $cols ) {
            echo '</div>';
        }
    }

    public static function render( $post, array $f ) {
        if ( isset( $f['render'] ) && is_callable( $f['render'] ) ) {
            call_user_func( $f['render'], $post, $f );
            return;
        }
        $default = ( null !== $f['default_new'] && 'auto-draft' === $post->post_status ) ? $f['default_new'] : $f['default'];
        switch ( $f['type'] ) {
            case 'text':
            case 'url':
            case 'date':
            case 'number':
            case 'email':
                $attrs = '';
                if ( 'url' === $f['type'] ) {
                    $attrs = 'data-crg-url="' . ( 'site' === $f['url'] ? 'site' : 'link' ) . '" autocomplete="off"';
                } elseif ( 'number' === $f['type'] ) {
                    $attrs = 'min="' . (int) ( $f['min'] ?? 0 ) . '"' . ( isset( $f['max'] ) ? ' max="' . (int) $f['max'] . '"' : '' ) . ' step="1"';
                }
                self::input( $post, $f['key'], $f['label'], [ 'type' => $f['type'], 'help' => $f['help'], 'placeholder' => $f['placeholder'], 'default' => $default, 'class' => $f['class'], 'attrs' => $attrs ] );
                break;
            case 'textarea':
                self::input( $post, $f['key'], $f['label'], [ 'type' => 'textarea', 'rows' => $f['rows'], 'help' => $f['help'], 'placeholder' => $f['placeholder'], 'default' => $default, 'class' => $f['class'] ] );
                break;
            case 'editor':
                if ( ! empty( $f['legacy_text'] ) ) {
                    self::legacy_editor( $post, $f );
                } else {
                    self::editor( $post, $f['key'], $f['label'], $f['help'], $f['rows'], (string) $default );
                }
                break;
            case 'note':
                self::note_editor( $post, $f['key'], $f['label'], $f['help'], is_callable( $f['standard'] ?? null ) ? (string) call_user_func( $f['standard'], $post ) : '' );
                break;
            default:
                $method = 'field_' . $f['type'];
                if ( method_exists( __CLASS__, $method ) ) {
                    self::$method( $post, $f );
                }
        }
    }

    public static function meta( $post, $key, $default = '' ) {
        $value = get_post_meta( $post->ID, $key, true );
        return '' === $value || null === $value ? $default : $value;
    }

    /** Label + input + help, the building block of the form. */
    public static function input( $post, $key, $label, array $args = [] ) {
        $args += [ 'type' => 'text', 'help' => '', 'placeholder' => '', 'default' => '', 'class' => '', 'attrs' => '' ];
        $value = self::meta( $post, $key, $args['default'] );
        ?>
        <div class="crg-field <?php echo esc_attr( $args['class'] ); ?>">
            <label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
            <?php if ( 'textarea' === $args['type'] ) : ?>
                <textarea id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" rows="<?php echo (int) ( $args['rows'] ?? 4 ); ?>" placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
            <?php else : ?>
                <input type="<?php echo esc_attr( $args['type'] ); ?>" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>" <?php echo $args['attrs']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed attribute strings from this class ?>>
            <?php endif; ?>
            <?php if ( $args['help'] ) : ?><p class="description"><?php echo wp_kses( $args['help'], self::HELP_TAGS ); ?></p><?php endif; ?>
        </div>
        <?php
    }

    public static function editor( $post, $key, $label, $help = '', $rows = 6, $default = '' ) {
        ?>
        <div class="crg-field crg-field-editor">
            <label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
            <?php if ( $help ) : ?><p class="description"><?php echo wp_kses( $help, self::HELP_TAGS ); ?></p><?php endif; ?>
            <?php wp_editor( (string) self::meta( $post, $key, $default ), $key, [ 'textarea_name' => $key, 'textarea_rows' => $rows ] + self::EDITOR ); ?>
        </div>
        <?php
    }

    /** An editor whose older reports saved plain lines: they're converted to a list so nothing is lost. */
    private static function legacy_editor( $post, array $f ) {
        $value = (string) self::meta( $post, $f['key'] );
        if ( '' !== trim( $value ) && ! preg_match( '/<[a-z]/i', $value ) ) {
            $value = Cleanup_Report_Render::text_to_html( $value ); // plain lines from before 2.1.0
        }
        ?>
        <div class="crg-field crg-field-editor">
            <label for="<?php echo esc_attr( $f['key'] ); ?>"><?php echo esc_html( $f['label'] ); ?></label>
            <p class="description"><?php echo wp_kses( $f['help'], self::HELP_TAGS ); ?></p>
            <?php wp_editor( $value, $f['key'], [ 'textarea_name' => $f['key'], 'textarea_rows' => $f['rows'] ] + self::EDITOR ); ?>
        </div>
        <?php
    }

    public static function note_editor( $post, $key, $label, $help, $standard ) {
        $value = (string) self::meta( $post, $key );
        if ( '' !== trim( $value ) && ! preg_match( '/<[a-z]/i', $value ) ) {
            $value = Cleanup_Report_Render::text_to_html( $value );
        }
        ?>
        <div class="crg-field crg-field-editor">
            <label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
            <p class="description"><?php echo esc_html( $help ); ?></p>
            <?php wp_editor( $value, $key, [ 'textarea_name' => $key, 'textarea_rows' => 6 ] + self::EDITOR ); ?>
            <details class="crg-std">
                <summary>Standard text, used when this is empty</summary>
                <div class="crg-std-text"><?php echo wp_kses_post( Cleanup_Report_Render::note_html( $standard ) ); ?></div>
                <button type="button" class="button button-small crg-use-std" data-editor="<?php echo esc_attr( $key ); ?>">Copy into the editor to change it</button>
            </details>
        </div>
        <?php
    }

    /** One answer from a list (and a note beside it when $f['note']). */
    public static function field_select( $post, array $f ) {
        self::field_questions( $post, [ 'label' => $f['label'], 'help' => $f['help'], 'class' => $f['class'], 'questions' => [ substr( $f['key'], 4 ) => [ $f['label'], $f['label'], $f['answers'] ?? [], ! empty( $f['note'] ) ? ( $f['placeholder'] ?: __( 'Note', 'cleanup-report-generator' ) ) : false ] ] ] );
    }

    /**
     * Questions answered from a list, each with a note: key => [ question, report label, answers, note placeholder
     * (false: no note) ]. Saved as crg_<key> and crg_<key>_note. (The hosting & access checks of a cleanup report.)
     */
    public static function field_questions( $post, array $f ) {
        ?>
        <div class="crg-field crg-site-checks">
            <span class="crg-field-label"><?php echo esc_html( $f['label'] ); ?></span>
            <?php if ( $f['help'] ) : ?><p class="description"><?php echo wp_kses( $f['help'], self::HELP_TAGS ); ?></p><?php endif; ?>
            <?php foreach ( $f['questions'] as $key => $q ) :
                [ $question, , $answers ] = $q;
                $note = $q[3] ?? __( 'Note', 'cleanup-report-generator' );
                $id   = 'crg_' . $key; ?>
                <div class="crg-site-check">
                    <label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $question ); ?></label>
                    <select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>">
                        <option value="">—</option>
                        <?php foreach ( $answers as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( self::meta( $post, $id ), $value ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ( false !== $note ) : ?>
                    <input type="text" name="<?php echo esc_attr( $id . '_note' ); ?>" value="<?php echo esc_attr( self::meta( $post, $id . '_note' ) ); ?>" placeholder="<?php echo esc_attr( $note ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: a question */ __( 'Note: %s', 'cleanup-report-generator' ), $question ) ); ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Rows typed in a table, saved as "a | b | c" lines (columns: name => [ label, placeholder ]). With JavaScript
     * off, or by choice, the lines are edited as text.
     */
    public static function field_table( $post, array $f ) {
        $key  = $f['key'];
        $cols = $f['columns'] ?? [];
        ?>
        <div class="crg-field crg-gtable" data-columns="<?php echo esc_attr( (string) wp_json_encode( array_values( array_map( static function ( $c ) { return [ 'label' => $c[0], 'placeholder' => $c[1] ?? '' ]; }, $cols ) ) ) ); ?>">
            <span class="crg-field-label"><?php echo esc_html( $f['label'] ); ?></span>
            <?php if ( $f['help'] ) : ?><p class="description"><?php echo wp_kses( $f['help'], self::HELP_TAGS ); ?></p><?php endif; ?>
            <table class="crg-table hide-if-no-js">
                <thead><tr><th class="crg-col-drag"></th><?php foreach ( $cols as $c ) : ?><th><?php echo esc_html( $c[0] ); ?></th><?php endforeach; ?><th class="crg-col-x"></th></tr></thead>
                <tbody class="crg-gtable-rows"></tbody>
            </table>
            <div class="crg-gtable-text">
                <textarea id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" rows="<?php echo (int) $f['rows']; ?>" class="code"><?php echo esc_textarea( self::meta( $post, $key ) ); ?></textarea>
                <p class="description"><?php echo esc_html( sprintf( /* translators: %s: column names, e.g. "Name | Version" */ __( 'One row per line: %s', 'cleanup-report-generator' ), implode( ' | ', wp_list_pluck( $cols, 0 ) ) ) ); ?></p>
            </div>
            <p class="hide-if-no-js">
                <button type="button" class="button crg-gtable-add"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add row', 'cleanup-report-generator' ); ?></button>
                <button type="button" class="button-link crg-gtable-toggle"><?php esc_html_e( 'Edit as text', 'cleanup-report-generator' ); ?></button>
            </p>
        </div>
        <?php
    }

    /** Screenshots (image + caption, and for cleanup reports a before/after/evidence type). */
    public static function field_screenshots( $post, array $f ) {
        $shots = get_post_meta( $post->ID, $f['key'], true );
        if ( ! is_array( $shots ) && 'crg_screenshots' === $f['key'] ) {
            $shots = [];
            foreach ( [ 'before' => 'crg_ss_before', 'after' => 'crg_ss_after' ] as $type => $key ) { // reports from 1.x
                if ( self::meta( $post, $key ) ) {
                    $shots[] = [ 'url' => self::meta( $post, $key ), 'title' => 'Security scan – ' . $type . ' cleanup', 'type' => $type ];
                }
            }
        }
        $shots = is_array( $shots ) ? $shots : [];
        if ( ! $shots && 'auto-draft' === $post->post_status && ! empty( $f['default_rows'] ) ) {
            $shots = $f['default_rows'];
        }
        self::shots_field( $f['key'], $shots, $f['label'], $f['help'], ! empty( $f['typed'] ) );
    }

    /** A list of screenshots (image, caption and, for cleanup reports, a type), saved as the meta $field. */
    private static function shots_field( $field, array $screenshots, $label, $help, $typed = true ) {
        ?>
        <div class="crg-field crg-shots-field">
            <span class="crg-field-label"><?php echo esc_html( $label ); ?></span>
            <p class="description"><?php echo esc_html( $help ); ?></p>
            <div class="crg-shots" data-next="<?php echo count( $screenshots ); ?>">
                <?php foreach ( array_values( $screenshots ) as $i => $shot ) { self::screenshot_row( $i, $shot, $field, $typed ); } ?>
            </div>
            <template class="crg-shot-template"><?php self::screenshot_row( '__i__', [ 'type' => $typed ? 'evidence' : 'other' ], $field, $typed ); ?></template>
            <button type="button" class="button crg-shot-add"><span class="dashicons dashicons-format-image"></span> Add screenshot</button>
        </div>
        <?php
    }

    private static function screenshot_row( $i, array $shot, $field = 'crg_screenshots', $typed = true ) {
        $name = $field . '[' . $i . ']';
        $url  = $shot['url'] ?? '';
        ?>
        <div class="crg-shot">
            <span class="crg-drag dashicons dashicons-menu" title="Drag to reorder"></span>
            <button type="button" class="crg-shot-thumb crg-media" data-target="<?php echo esc_attr( '[name="' . $name . '[url]"]' ); ?>" title="Choose from the media library">
                <?php if ( $url ) : ?><img src="<?php echo esc_url( $url ); ?>" alt=""><?php else : ?><span class="dashicons dashicons-format-image"></span><?php endif; ?>
            </button>
            <div class="crg-shot-fields">
                <input type="text" name="<?php echo esc_attr( $name . '[title]' ); ?>" value="<?php echo esc_attr( $shot['title'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $typed ? 'Caption, e.g. Defender scan after cleanup' : 'Caption, e.g. Logo loads without its size' ); ?>" class="crg-shot-title" aria-label="Caption">
                <div class="crg-shot-line">
                    <?php if ( $typed ) : ?>
                    <select name="<?php echo esc_attr( $name . '[type]' ); ?>" aria-label="Type">
                        <?php foreach ( [ 'before' => 'Before', 'after' => 'After', 'evidence' => 'Evidence', 'other' => 'Other' ] as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $shot['type'] ?? 'other', $value ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php else : ?>
                    <input type="hidden" name="<?php echo esc_attr( $name . '[type]' ); ?>" value="other">
                    <?php endif; ?>
                    <input type="url" name="<?php echo esc_attr( $name . '[url]' ); ?>" value="<?php echo esc_attr( $url ); ?>" placeholder="Image URL, or choose from the media library" class="crg-shot-url" aria-label="Image URL">
                    <button type="button" class="button crg-media" data-target="<?php echo esc_attr( '[name="' . $name . '[url]"]' ); ?>">Choose</button>
                </div>
            </div>
            <button type="button" class="button-link crg-remove" title="Remove"><span class="dashicons dashicons-no-alt"></span><span class="screen-reader-text">Remove screenshot</span></button>
        </div>
        <?php
    }

    /** Key finding: one line on the summary page (older reports start from the infection summary's first sentence). */
    public static function field_key_finding( $post, array $f ) {
        // Reports saved before this field existed showed the summary's first sentence: start from it so saving keeps it.
        $auto = Cleanup_Report_Render::auto_key_finding( (string) self::meta( $post, 'crg_infection_summary' ) );
        $kf   = metadata_exists( 'post', $post->ID, 'crg_key_finding' ) ? (string) get_post_meta( $post->ID, 'crg_key_finding', true ) : $auto;
        ?>
        <div class="crg-field">
            <label for="crg_key_finding">Key finding</label>
            <input type="text" id="crg_key_finding" name="crg_key_finding" value="<?php echo esc_attr( $kf ); ?>" maxlength="300" placeholder="<?php echo esc_attr( $auto ?: 'e.g. A self-healing backdoor was hidden in the theme and re-created deleted files on every page load.' ); ?>">
            <p class="description">One line, shown as <strong>Key finding</strong> on the summary page. Leave empty to leave it out.</p>
        </div>
        <?php
    }

    /** Issues found: the critical / medium / low counts on the summary page. */
    public static function field_severity_counts( $post, array $f ) {
        ?>
        <div class="crg-field">
            <span class="crg-field-label">Issues found</span>
            <p class="description">The numbers on the summary page.</p>
            <div class="crg-cols crg-cols-3 crg-counts">
                <?php
                self::input( $post, 'crg_summary_critical', 'Critical', [ 'type' => 'number', 'default' => 0, 'help' => 'Malware and backdoors removed', 'attrs' => 'min="0" step="1"' ] );
                self::input( $post, 'crg_summary_medium', 'Medium', [ 'type' => 'number', 'default' => 0, 'help' => 'Vulnerable components <button type="button" class="button-link crg-count-components" hidden>use the table below</button>', 'attrs' => 'min="0" step="1"' ] );
                self::input( $post, 'crg_summary_low', 'Low', [ 'type' => 'number', 'default' => 0, 'help' => 'Hardening issues', 'attrs' => 'min="0" step="1"' ] );
                ?>
            </div>
        </div>
        <?php
    }

    /** "Check plugins & themes": paste `wp plugin list` output, see what's abandoned or vulnerable, add it to the report. */
    public static function field_plugin_check( $post, array $f ) {
        $saved = (string) self::meta( $post, 'crg_plugin_list' );
        ?>
        <details class="crg-pc crg-field hide-if-no-js" data-post="<?php echo (int) $post->ID; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'crg_check_plugins' ) ); ?>" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"<?php echo $saved ? '' : ' open'; ?>>
            <summary><span class="dashicons dashicons-search"></span> <strong>Check plugins &amp; themes</strong> <span class="crg-pc-sub">Find what's abandoned, vulnerable or out of date, and add it to the report.</span></summary>
            <ol class="crg-pc-steps">
                <li>On the member's server, in the WordPress folder, run:
                    <div class="crg-pc-cmd"><code><?php echo esc_html( Cleanup_Report_Plugin_Check::COMMAND ); ?></code><button type="button" class="button button-small crg-pc-copy">Copy</button></div>
                </li>
                <li><label for="crg_plugin_list">Paste the output here</label> (the default table from <code>wp plugin list</code> works too):
                    <textarea id="crg_plugin_list" name="crg_plugin_list" rows="4" class="code" placeholder="name,title,version,update_version,status&#10;revslider,&quot;Slider Revolution&quot;,6.8.1,,active"><?php echo esc_textarea( $saved ); ?></textarea>
                </li>
            </ol>
            <p class="crg-pc-bar">
                <button type="button" class="button button-primary crg-pc-run">Check</button>
                <span class="spinner"></span>
                <span class="crg-pc-msg" aria-live="polite"></span>
            </p>
            <div class="crg-pc-results"></div>
            <p class="description">Each folder name is looked up on WordPress.org (closed, last release, "Tested up to") and WPVulnerability.net (known vulnerabilities in the installed version); results are cached for 12 hours. Everything checked is added to the <?php echo isset( Cleanup_Report_Settings::tabs()['plugins'] ) ? '<a href="' . esc_url( Cleanup_Report_Settings::url( 'plugins' ) ) . '">plugin list</a>' : 'team\'s plugin list'; ?>, where premium items are marked as maintained or not.</p>
        </details>
        <?php
    }

    /** Vulnerable & outdated components: a table, saved as "Name | Version | Issue | Severity" lines. */
    public static function field_components( $post, array $f ) {
        ?>
        <div class="crg-field crg-components">
            <span class="crg-field-label">Vulnerable &amp; outdated components</span>
            <p class="description">Plugins and themes with known vulnerabilities or no longer maintained.</p>
            <table class="crg-table hide-if-no-js">
                <thead><tr><th class="crg-col-drag"></th><th class="crg-col-name">Plugin / theme</th><th class="crg-col-version">Version</th><th>Issue</th><th class="crg-col-sev">Severity</th><th class="crg-col-x"></th></tr></thead>
                <tbody class="crg-components-rows"></tbody>
            </table>
            <p class="crg-components-empty hide-if-no-js description">No components listed.</p>
            <div class="crg-components-warn notice notice-warning inline" hidden><p></p></div>
            <div class="crg-components-text">
                <textarea id="crg_components" name="crg_components" rows="5" class="code" placeholder="Slider Revolution | 6.8.1 | 5 known vulnerabilities; update to 7.1.0+ | critical"><?php echo esc_textarea( self::meta( $post, 'crg_components' ) ); ?></textarea>
                <p class="description">One per line: <code>Name | Version | Issue | Severity</code> (critical, high, medium or low).</p>
            </div>
            <p class="hide-if-no-js">
                <button type="button" class="button crg-component-add"><span class="dashicons dashicons-plus-alt2"></span> Add component</button>
                <button type="button" class="button-link crg-components-toggle">Edit as text</button>
            </p>
        </div>
        <?php
    }

    /** Security blocklists: tick the vendors still flagging the site; the report lists them with where to request delisting. */
    public static function field_blocklists( $post, array $f ) {
        $ticked = Cleanup_Report_Checklists::report_blocklists( $post->ID );
        $items  = [];
        foreach ( Cleanup_Report_Checklists::blocklist_items() as $item ) {
            if ( ! empty( $item['enabled'] ) || isset( $ticked[ $item['key'] ] ) ) {
                $items[ $item['key'] ] = $item;
            }
        }
        $items += $ticked; // ticked earlier, since removed from settings
        ?>
        <div class="crg-field crg-delist">
            <span class="crg-field-label">Security blocklists (delist request)</span>
            <p class="description">Tick each vendor that still flags the site, e.g. on VirusTotal<span class="crg-vt-wrap" hidden> (<a href="#" class="crg-vt" target="_blank" rel="noopener">check this site on VirusTotal</a>)</span>. The report lists them with where to request delisting. Nothing ticked and no note: "No problem identified".</p>
            <?php if ( $items ) : ?>
                <div class="crg-delist-bar hide-if-no-js">
                    <input type="search" class="crg-delist-filter" placeholder="Filter vendors…" aria-label="Filter vendors">
                    <span class="crg-delist-count" aria-live="polite"></span>
                </div>
                <div class="crg-delist-grid">
                    <?php foreach ( $items as $key => $item ) :
                        $contact = implode( ' · ', array_filter( [ preg_replace( '#^https?://#', '', (string) ( $item['url'] ?? '' ) ), (string) ( $item['email'] ?? '' ) ] ) ); ?>
                        <label title="<?php echo esc_attr( $contact ); ?>"><input type="checkbox" name="crg_delist_vendors[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( isset( $ticked[ $key ] ) ); ?>> <?php echo esc_html( $item['label'] ); ?></label>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="crg-empty">No blocklists are set up. <?php self::settings_link( 'blocklists', 'Add them in Settings' ); ?></p>
            <?php endif; ?>
            <input type="hidden" name="crg_delist_vendors[]" value="">
            <label for="crg_delist" class="crg-sub-label">Other blocklists or notes</label>
            <textarea id="crg_delist" name="crg_delist" rows="2" placeholder="e.g. Delist requested from Norton Safe Web on 6 November"><?php echo esc_textarea( self::meta( $post, 'crg_delist' ) ); ?></textarea>
            <p class="crg-checklist-foot"><?php self::settings_link( 'blocklists', 'Edit the blocklists' ); ?></p>
        </div>
        <?php
    }

    public static function field_defender( $post, array $f ) {
        $answers = Cleanup_Report_Checklists::report_defender( $post->ID );
        $items   = Cleanup_Report_Checklists::form_items( Cleanup_Report_Checklists::defender_items(), $answers );
        $groups  = [ 'agent' => [], 'member' => [] ];
        foreach ( $items as $key => $item ) {
            $groups[ Cleanup_Report_Checklists::owner( $item ) ][ $key ] = $item;
        }
        if ( ! $items ) : ?>
            <p class="crg-empty">No Defender settings are set up. <?php self::settings_link( 'defender', 'Add them in Settings' ); ?></p>
        <?php endif;
        $parts = [
            'agent'  => [
                'title'   => 'To be done by agent',
                'help'    => '<strong>Applied</strong>: we configured it. <strong>Pending</strong>: not configured yet. <strong>Member to do</strong>: we couldn\'t, so the member has to.',
                'all'     => [ 'applied', 'Mark all applied' ],
                'choices' => [ '' => 'Leave out', 'applied' => 'Applied', 'in_progress' => 'Pending', 'pending' => 'Member to do' ],
            ],
            'member' => [
                'title'   => 'To be done by member',
                'help'    => 'Settings that need the site owner. <strong>Member to do</strong>: listed for the member. <strong>Applied</strong>: already set up.',
                'all'     => [ 'pending', 'Mark all member to do' ],
                'choices' => [ '' => 'Leave out', 'pending' => 'Member to do', 'applied' => 'Applied' ],
            ],
        ];
        foreach ( $parts as $owner => $part ) {
            if ( ! $groups[ $owner ] ) {
                continue;
            }
            ?>
            <div class="crg-checklist crg-def-part" data-kind="defender">
                <h3 class="crg-def-title"><?php echo esc_html( $part['title'] ); ?></h3>
                <div class="crg-checklist-bar">
                    <p class="description"><?php echo wp_kses( $part['help'], self::HELP_TAGS ); ?> Click a selected button again to leave the setting out of the report.</p>
                    <span class="crg-checklist-count" aria-live="polite"></span>
                    <button type="button" class="button button-small crg-set-all" data-status="<?php echo esc_attr( $part['all'][0] ); ?>"><?php echo esc_html( $part['all'][1] ); ?></button>
                    <button type="button" class="button-link crg-set-all" data-status="">Clear</button>
                </div>
                <?php
                foreach ( $groups[ $owner ] as $key => $item ) {
                    $answer  = $answers[ $key ] ?? [];
                    $choices = $part['choices'];
                    if ( ! empty( $answer['status'] ) && ! isset( $choices[ $answer['status'] ] ) ) {
                        $choices[ $answer['status'] ] = $parts['agent']['choices'][ $answer['status'] ] ?? $answer['status']; // keep an answer given before the item moved part
                    }
                    self::check_row( 'crg_defender', $key, $item, $answer['status'] ?? '', $answer['notes'] ?? '', 'notes', $choices, [
                        ''            => 'Not in the report',
                        'applied'     => ( $item['applied_note'] ?? '' ) ?: 'Configured to recommended settings.',
                        'in_progress' => ( $item['progress_note'] ?? '' ) ?: Cleanup_Report_Checklists::PROGRESS_FALLBACK,
                        'pending'     => ( $item['pending_note'] ?? '' ) ?: 'Note for the member: what they need to do',
                    ], true );
                }
                ?>
            </div>
            <?php
        }
        ?>
        <p class="crg-checklist-foot"><?php self::settings_link( 'defender', 'Edit the Defender checklist' ); ?></p>
        <?php
    }

    /** Follow-ups ("Recommended actions"), from Settings › Follow-ups. */
    public static function field_followups( $post, array $f ) {
        $answers = Cleanup_Report_Checklists::report_followups( $post->ID );
        $items   = Cleanup_Report_Checklists::form_items( Cleanup_Report_Checklists::followup_items(), $answers );
        ?>
        <div class="crg-field">
            <span class="crg-field-label">Follow-ups</span>
            <div class="crg-checklist" data-kind="followups">
                <div class="crg-checklist-bar">
                    <p class="description">Shown as <em>Recommended actions</em>. <strong>Already configured</strong>: in place before the cleanup. <strong>Done</strong>: we did it during the cleanup. <strong>Member to do</strong>: the member needs to act. Click a selected button again to leave the item out of the report.</p>
                    <span class="crg-checklist-count" aria-live="polite"></span>
                </div>
                <?php if ( ! $items ) : ?>
                    <p class="crg-empty">No follow-ups are set up. <?php self::settings_link( 'followups', 'Add them in Settings' ); ?></p>
                <?php endif; ?>
                <?php
                foreach ( $items as $key => $item ) {
                    $answer = $answers[ $key ] ?? [];
                    self::check_row( 'crg_followups', $key, $item, $answer['status'] ?? '', $answer['note'] ?? '', 'note', [
                        ''             => 'Leave out', // hidden: click the selected button again to clear
                        'not_required' => 'Already configured',
                        'fixed'        => 'Done',
                        'required'     => 'Member to do',
                    ], [
                        ''             => 'Not in the report',
                        'not_required' => $item['done'] ?? '',
                        'fixed'        => ( $item['fixed'] ?? '' ) ?: ( Cleanup_Report_Checklists::DEFAULT_FIXED[ $key ] ?? Cleanup_Report_Checklists::FIXED_FALLBACK ),
                        'required'     => $item['todo'] ?? '',
                    ], true );
                }
                ?>
                <p class="crg-checklist-foot"><?php self::settings_link( 'followups', 'Edit the follow-ups list' ); ?></p>
            </div>
        </div>
        <?php
    }

    public static function check_row( $field, $key, array $item, $status, $note, $note_field, array $choices, array $previews, $hide_none = false ) {
        $name = $field . '[' . $key . ']';
        ?>
        <div class="crg-check<?php echo $status ? ' is-' . esc_attr( $status ) : ''; ?>" data-previews="<?php echo esc_attr( wp_json_encode( $previews ) ); ?>"<?php echo isset( $item['good'] ) ? ' data-good="' . esc_attr( $item['good'] ) . '"' : ''; ?>>
            <div class="crg-check-name">
                <strong><?php echo esc_html( $item['label'] ); ?></strong>
                <?php if ( 'recommended' === ( $item['tone'] ?? '' ) ) : ?><span class="crg-tag" title="Shown in the report as Recommended rather than Required">Recommended</span><?php endif; ?>
                <?php if ( ! empty( $item['orphan'] ) ) : ?><span class="crg-tag" title="Kept because this report already uses it"><?php echo esc_html( 'off' === $item['orphan'] ? 'Switched off in settings' : 'Removed from settings' ); ?></span><?php endif; ?>
                <?php if ( ! empty( $item['hint'] ) ) : ?><span class="crg-check-hint"><?php echo esc_html( $item['hint'] ); ?></span><?php endif; ?>
                <?php if ( ! empty( $item['docs'] ) ) : ?><a class="crg-check-docs" href="<?php echo esc_url( $item['docs'] ); ?>" target="_blank" rel="noopener">Docs<span class="screen-reader-text"> (opens in a new tab)</span></a><?php endif; ?>
                <?php if ( ! empty( $item['link'] ) ) : ?><a class="crg-check-docs crg-check-link" data-link="<?php echo esc_attr( $item['link'] ); ?>" href="#" target="_blank" rel="noopener" hidden>Open on site<span class="screen-reader-text"> (opens in a new tab)</span></a><?php endif; ?>
            </div>
            <div class="crg-seg" role="radiogroup" aria-label="<?php echo esc_attr( $item['label'] ); ?>">
                <?php foreach ( $choices as $value => $label ) : ?>
                    <label class="crg-seg-<?php echo esc_attr( $value ?: 'none' ); ?>"<?php echo ( $hide_none && '' === $value ) ? ' hidden' : ''; ?>><input type="radio" name="<?php echo esc_attr( $name . '[status]' ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( $status, $value ); ?>><span><?php echo esc_html( $label ); ?></span></label>
                <?php endforeach; ?>
            </div>
            <input type="text" class="crg-check-note" name="<?php echo esc_attr( $name . '[' . $note_field . ']' ); ?>" value="<?php echo esc_attr( $note ); ?>" placeholder="<?php echo esc_attr( $previews[ $status ] ?? '' ); ?>" aria-label="<?php echo esc_attr( 'Note for ' . $item['label'] ); ?>">
        </div>
        <?php
    }

    public static function settings_link( $tab, $text ) {
        if ( isset( Cleanup_Report_Settings::tabs()[ $tab ] ) ) {
            printf( '<a href="%s">%s</a>', esc_url( Cleanup_Report_Settings::url( $tab ) ), esc_html( $text ) );
        }
    }

    public static function field_scores( $post, array $f ) {
        $targets = Cleanup_Report_Optimization::report_targets( $post->ID );
        ?>
        <div class="crg-field crg-scores" data-target-mobile="<?php echo (int) $targets['mobile']; ?>" data-target-desktop="<?php echo (int) $targets['desktop']; ?>">
            <span class="crg-field-label">Pages used for the average score</span>
            <p class="description">PageSpeed Insights performance score (0–100) of each page, before and after the work. Leave an "after" empty until the work is done. Each average is the sum of the pages' scores divided by the number of pages. Targets: <strong>mobile <?php echo (int) $targets['mobile']; ?></strong>, <strong>desktop <?php echo (int) $targets['desktop']; ?></strong> (average after the work<?php echo isset( Cleanup_Report_Settings::tabs()['optimization'] ) ? '; <a href="' . esc_url( Cleanup_Report_Settings::url( 'optimization' ) ) . '">change</a>' : ''; ?>).</p>
            <table class="crg-table crg-score-table hide-if-no-js">
                <thead>
                    <tr><th class="crg-col-drag" rowspan="2"></th><th rowspan="2">Page URL</th><th colspan="2" class="crg-col-group">Before</th><th colspan="2" class="crg-col-group">After</th><th class="crg-col-x" rowspan="2"></th></tr>
                    <tr><th class="crg-col-score">Mobile</th><th class="crg-col-score">Desktop</th><th class="crg-col-score">Mobile</th><th class="crg-col-score">Desktop</th></tr>
                </thead>
                <tbody class="crg-score-rows"></tbody>
                <tfoot><tr><td></td><th scope="row">Average</th><td class="crg-avg" data-col="mobile_before">—</td><td class="crg-avg" data-col="desktop_before">—</td><td class="crg-avg" data-col="mobile_after">—</td><td class="crg-avg" data-col="desktop_after">—</td><td></td></tr></tfoot>
            </table>
            <div class="crg-scores-text">
                <textarea id="crg_opt_scores" name="crg_opt_scores" rows="5" class="code" placeholder="https://example.com/ | 56 | 85 | 95 | 100"><?php echo esc_textarea( self::meta( $post, 'crg_opt_scores' ) ); ?></textarea>
                <p class="description">One page per line: <code>URL | mobile before | desktop before | mobile after | desktop after</code>.</p>
            </div>
            <p class="hide-if-no-js">
                <button type="button" class="button crg-score-add"><span class="dashicons dashicons-plus-alt2"></span> Add page</button>
                <button type="button" class="button-link crg-scores-toggle">Edit as text</button>
            </p>
        </div>
        <?php
    }

    /** Code added to the site (shown escaped in a code block), with the file it's in ($f['path_key']). */
    public static function field_code( $post, array $f ) {
        $key = $f['key'];
        ?>
        <div class="crg-field crg-code-field">
            <span class="crg-field-label"><?php echo esc_html( $f['label'] ); ?></span>
            <?php if ( $f['help'] ) : ?><p class="description"><?php echo wp_kses( $f['help'], self::HELP_TAGS ); ?></p><?php endif; ?>
            <?php if ( ! empty( $f['path_key'] ) ) { self::input( $post, $f['path_key'], $f['path_label'] ?? __( 'File', 'cleanup-report-generator' ), [ 'placeholder' => $f['path_placeholder'] ?? '', 'class' => 'crg-sub-field' ] ); } ?>
            <label for="<?php echo esc_attr( $key ); ?>" class="crg-sub-label"><?php esc_html_e( 'Code', 'cleanup-report-generator' ); ?></label>
            <textarea id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" rows="<?php echo (int) $f['rows']; ?>" class="code" spellcheck="false"><?php echo esc_textarea( self::meta( $post, $key ) ); ?></textarea>
        </div>
        <?php
    }

    /**
     * A checklist from settings: each item answered from $f['statuses'] (Yes / No / N/A by default) with a note. Items
     * with a "good" answer show green or red for it. Saved as a snapshot that keeps each item's wording.
     */
    public static function field_checklist( $post, array $f ) {
        $field    = $f['key'];
        $phase    = $f['phase'] ?? '';
        $answers  = self::report_checklist( $post->ID, $f );
        $items    = Cleanup_Report_Checklists::form_items( self::checklist_items( $f ), $answers );
        $statuses = self::checklist_statuses( $f );
        ?>
        <div class="crg-field">
            <div class="crg-checklist" data-kind="opt" data-phase="<?php echo esc_attr( $phase ); ?>">
                <div class="crg-checklist-bar">
                    <p class="description"><?php echo wp_kses( $f['help'], self::HELP_TAGS ); ?> The report colours each answer: green when it's the good one (e.g. <em>No</em> console errors), red when it isn't, and grey for N/A. Click a selected answer again to leave the test out of the report.</p>
                    <span class="crg-checklist-count" aria-live="polite"></span>
                    <?php if ( ! empty( $f['copy_from'] ) ) : ?><button type="button" class="button button-small crg-copy-pre" data-from="<?php echo esc_attr( $f['copy_from'] ); ?>"><?php echo esc_html( $f['copy_label'] ?? __( 'Same answers as before', 'cleanup-report-generator' ) ); ?></button><?php endif; ?>
                </div>
                <?php if ( ! $items ) : ?>
                    <p class="crg-empty"><?php esc_html_e( 'No items are set up.', 'cleanup-report-generator' ); ?> <?php self::settings_link( $f['settings'] ?? '', __( 'Add them in Settings', 'cleanup-report-generator' ) ); ?></p>
                <?php endif; ?>
                <?php
                foreach ( $items as $key => $item ) {
                    $answer = $answers[ $key ] ?? [];
                    $note   = ( $item[ $f['note_from'] ?? '' ] ?? '' ) ?: __( 'Note (optional)', 'cleanup-report-generator' );
                    $item  += [ 'tone' => '', 'good' => '' ];
                    self::check_row( $field, $key, $item, $answer['status'] ?? '', $answer['note'] ?? '', 'note', [ '' => __( 'Leave out', 'cleanup-report-generator' ) ] + $statuses, [ '' => __( 'Not in the report', 'cleanup-report-generator' ) ] + array_fill_keys( array_keys( $statuses ), $note ), true );
                }
                ?>
                <p class="crg-checklist-foot"><?php self::settings_link( $f['settings'] ?? '', __( 'Edit the list', 'cleanup-report-generator' ) ); ?></p>
            </div>
        </div>
        <?php
    }

    /** A checklist field's items: from its settings option, or its defaults when the option was never saved. */
    public static function checklist_items( array $f ) {
        return is_callable( $f['items'] ?? null ) ? (array) call_user_func( $f['items'] ) : [];
    }

    public static function checklist_statuses( array $f ) {
        return $f['statuses'] ?? [ 'yes' => __( 'Yes', 'cleanup-report-generator' ), 'no' => __( 'No', 'cleanup-report-generator' ), 'na' => __( 'N/A', 'cleanup-report-generator' ) ];
    }

    /** A report's answers to a checklist field, in order: key => item snapshot + status + note. */
    public static function report_checklist( $post_id, array $f ) {
        $saved = get_post_meta( $post_id, $f['key'], true );
        return is_array( $saved ) ? $saved : [];
    }

    /* ================================================================== saving */

    /**
     * Saves one field from the edit form (nonce, capability and lock are checked by the caller). Only fields that were
     * posted are saved, except screenshot lists, which are emptied when the form had none.
     */
    public static function save( $post_id, array $f ) {
        $f   = self::defaults( $f );
        $key = $f['key'];
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in Cleanup_Report_Admin::save_meta()
        if ( isset( $f['save'] ) && is_callable( $f['save'] ) ) {
            call_user_func( $f['save'], $post_id, $f );
            return;
        }
        switch ( $f['type'] ) {
            case 'screenshots':
                self::save_screenshots( $post_id, $key );
                return;
            case 'checklist':
                if ( isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ) {
                    self::save_checklist( $post_id, $f, map_deep( wp_unslash( $_POST[ $key ] ), 'sanitize_text_field' ) );
                }
                return;
            case 'followups':
            case 'defender':
                $field = 'followups' === $f['type'] ? 'crg_followups' : 'crg_defender';
                if ( isset( $_POST[ $field ] ) ) {
                    $posted = map_deep( wp_unslash( $_POST[ $field ] ), 'sanitize_text_field' );
                    Cleanup_Report_Checklists::save_report( $post_id, 'followups' === $f['type'] ? $posted : null, 'defender' === $f['type'] ? $posted : null );
                }
                return;
            case 'blocklists':
                if ( isset( $_POST['crg_delist_vendors'] ) && is_array( $_POST['crg_delist_vendors'] ) ) {
                    Cleanup_Report_Checklists::save_report_blocklists( $post_id, array_filter( array_map( 'sanitize_key', wp_unslash( $_POST['crg_delist_vendors'] ) ) ) );
                }
                self::save_value( $post_id, 'crg_delist', 'textarea' );
                return;
            case 'questions':
            case 'select':
                $questions = 'select' === $f['type'] ? [ substr( $key, 4 ) => [ '', '', $f['answers'] ?? [] ] ] : $f['questions'];
                foreach ( $questions as $q_key => $q ) {
                    if ( isset( $_POST[ 'crg_' . $q_key ] ) ) {
                        $answer = sanitize_key( wp_unslash( $_POST[ 'crg_' . $q_key ] ) );
                        update_post_meta( $post_id, 'crg_' . $q_key, isset( $q[2][ $answer ] ) ? $answer : '' );
                    }
                    self::save_value( $post_id, 'crg_' . $q_key . '_note', 'text' );
                }
                return;
            case 'severity_counts':
                foreach ( [ 'crg_summary_critical', 'crg_summary_medium', 'crg_summary_low' ] as $count ) {
                    self::save_value( $post_id, $count, 'number' );
                }
                return;
            case 'plugin_check':
                self::save_value( $post_id, 'crg_plugin_list', 'textarea' );
                return;
            case 'code':
                if ( ! empty( $f['path_key'] ) ) {
                    self::save_value( $post_id, $f['path_key'], 'text' );
                }
                self::save_value( $post_id, $key, 'code' );
                return;
            case 'components':
            case 'scores':
            case 'table':
                self::save_value( $post_id, $key, 'textarea' );
                return;
            case 'key_finding':
                self::save_value( $post_id, $key, 'text' );
                return;
            default:
                self::save_value( $post_id, $key, $f['type'], $f );
        }
        // phpcs:enable
    }

    /** Saves one posted value, sanitised for its kind; nothing happens when it wasn't posted. */
    public static function save_value( $post_id, $key, $kind, array $f = [] ) {
        if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in Cleanup_Report_Admin::save_meta()
            return;
        }
        $raw = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified by the caller; sanitised by kind below
        if ( ! is_string( $raw ) ) {
            return;
        }
        switch ( $kind ) {
            case 'number':
                $value = max( (int) ( $f['min'] ?? 0 ), (int) $raw );
                if ( isset( $f['max'] ) ) {
                    $value = min( (int) $f['max'], $value );
                }
                break;
            case 'url':
                $value = esc_url_raw( Cleanup_Report_Admin::normalize_url( sanitize_text_field( $raw ), 'site' === ( $f['url'] ?? '' ) ) );
                break;
            case 'email':
                $value = sanitize_email( $raw );
                break;
            case 'date':
                $value = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ? $raw : sanitize_text_field( $raw );
                break;
            case 'textarea':
                $value = sanitize_textarea_field( $raw );
                break;
            case 'code': // shown escaped, in a code block: tags and < > must survive
                $value = Cleanup_Report_Optimization::sanitize_code( $raw );
                break;
            case 'editor':
            case 'note':
                $value = wp_kses_post( $raw );
                break;
            default:
                $value = sanitize_text_field( $raw );
        }
        update_post_meta( $post_id, $key, wp_slash( $value ) );
    }

    /** A screenshot list from the form; the form always holds the whole list, so an absent list means none. */
    private static function save_screenshots( $post_id, $key ) {
        $screenshots = [];
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in Cleanup_Report_Admin::save_meta()
        if ( isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ) {
            // Each part is sanitised below; wp_unslash() first, wp_slash() before saving (update_post_meta() unslashes).
            foreach ( wp_unslash( $_POST[ $key ] ) as $shot ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                if ( is_array( $shot ) && ! empty( $shot['url'] ) ) {
                    $shot_type     = sanitize_key( $shot['type'] ?? 'other' );
                    $screenshots[] = [
                        'url'   => esc_url_raw( $shot['url'] ),
                        'title' => sanitize_text_field( $shot['title'] ?? '' ),
                        'type'  => in_array( $shot_type, [ 'before', 'after', 'evidence', 'other' ], true ) ? $shot_type : 'other',
                    ];
                }
            }
        }
        update_post_meta( $post_id, $key, wp_slash( $screenshots ) );
    }

    /**
     * Saves a checklist field's answers. Wording comes from settings (or, for an item no longer in settings, from the
     * report's own snapshot), never from the request.
     *
     * @param array $posted key => [ status, note ] (unslashed, sanitised)
     */
    public static function save_checklist( $post_id, array $f, array $posted ) {
        $defs     = Cleanup_Report_Checklists::keyed( self::checklist_items( $f ) );
        $old      = self::report_checklist( $post_id, $f );
        $statuses = self::checklist_statuses( $f );
        $rows     = [];
        foreach ( $posted as $item_key => $answer ) {
            $item_key = sanitize_key( $item_key );
            $status   = sanitize_key( $answer['status'] ?? '' );
            $def      = $defs[ $item_key ] ?? $old[ $item_key ] ?? null;
            if ( $def && isset( $statuses[ $status ] ) ) {
                unset( $def['enabled'], $def['status'], $def['note'], $def['orphan'] );
                $rows[ $item_key ] = [ 'key' => $item_key ] + $def + [ 'status' => $status, 'note' => sanitize_text_field( $answer['note'] ?? '' ) ];
            }
        }
        update_post_meta( $post_id, $f['key'], wp_slash( $rows ) );
    }

    /**
     * A checklist's answers as a report shows them: label, answer, tone (ok, bad, info, na) and note. An answer is ok
     * when it's the item's good answer, bad when the item has a good answer and this isn't it, info otherwise.
     */
    public static function checklist_for_output( array $rows, array $f, $site_url ) {
        $statuses = self::checklist_statuses( $f );
        $out      = [];
        foreach ( $rows as $row ) {
            $status = (string) ( $row['status'] ?? '' );
            $good   = (string) ( $row['good'] ?? '' );
            $note   = '' !== (string) ( $row['note'] ?? '' ) ? $row['note'] : (string) ( $row[ $f['note_from'] ?? '' ] ?? '' );
            $out[]  = [
                'label'  => $row['label'],
                'answer' => $statuses[ $status ] ?? $status,
                'tone'   => 'na' === $status ? 'na' : ( '' === $good ? 'info' : ( $good === $status ? 'ok' : 'bad' ) ),
                'note'   => Cleanup_Report_Checklists::fill( $note, $site_url ),
            ];
        }
        return $out;
    }

    /** The post meta a field writes, for the history ("Edited Findings, Defender"). */
    public static function meta_keys( array $f ) {
        $f = self::defaults( $f );
        switch ( $f['type'] ) {
            case 'questions':
                $keys = [];
                foreach ( array_keys( $f['questions'] ) as $q ) {
                    $keys[] = 'crg_' . $q;
                    $keys[] = 'crg_' . $q . '_note';
                }
                return $keys;
            case 'select':
                return [ $f['key'], $f['key'] . '_note' ];
            case 'severity_counts':
                return [ 'crg_summary_critical', 'crg_summary_medium', 'crg_summary_low' ];
            case 'plugin_check':
                return [ 'crg_plugin_list' ];
            case 'blocklists':
                return [ 'crg_delist', 'crg_delist_vendors' ];
            case 'followups':
                return [ 'crg_followups' ];
            case 'defender':
                return [ 'crg_defender' ];
            case 'code':
                return array_filter( [ $f['path_key'] ?? '', $f['key'] ] );
            default:
                return array_merge( [ $f['key'] ], (array) ( $f['meta_keys'] ?? [] ) );
        }
    }
}
