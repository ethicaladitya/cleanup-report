<?php
/**
 * Report type builder (Reports › Report types): administrators create, edit, turn off and delete their own report
 * types without code. Each type is a name, a few report labels and its sections of fields, using the generic field
 * types (Cleanup_Report_Fields). Custom types are drawn by the generic report template.
 *
 * Stored in the option crg_custom_types (not autoloaded): type key => definition as saved here (no code). They join
 * the registry through the cleanup_report_types filter at priority 5, so code can still change or remove them.
 *
 * Reports keep their values when a type changes:
 *  - values are stored per field (meta crg_t_<type>_<field>); renaming a field changes no stored value;
 *  - a field removed from a type keeps its value; the report's edit screen lists it under "Fields no longer in this
 *    type", where it can be removed from that report;
 *  - every save stores the definition the report was filled in with (meta crg_type_snapshot); an approved report's
 *    PDF uses it, so it reads the same after the type is edited.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Cleanup_Report_Type_Builder {

    const OPTION   = 'crg_custom_types';
    const SNAPSHOT = 'crg_type_snapshot';
    const PAGE     = 'crg-report-types';
    const LIMITS   = [ 'sections' => 20, 'fields' => 40, 'options' => 40, 'columns' => 8 ];

    /** Field types offered in the builder: type => label. (Bespoke types of the built-in reports aren't offered.) */
    public static function field_types() {
        return [
            'text'        => __( 'Short text', 'cleanup-report-generator' ),
            'textarea'    => __( 'Plain text (several lines)', 'cleanup-report-generator' ),
            'editor'      => __( 'Rich text', 'cleanup-report-generator' ),
            'note'        => __( 'Rich text with a standard text', 'cleanup-report-generator' ),
            'url'         => __( 'Web address', 'cleanup-report-generator' ),
            'date'        => __( 'Date', 'cleanup-report-generator' ),
            'number'      => __( 'Number', 'cleanup-report-generator' ),
            'select'      => __( 'Choice from a list', 'cleanup-report-generator' ),
            'questions'   => __( 'Questions with answers', 'cleanup-report-generator' ),
            'checklist'   => __( 'Checklist (Yes / No / N/A)', 'cleanup-report-generator' ),
            'table'       => __( 'Table', 'cleanup-report-generator' ),
            'screenshots' => __( 'Screenshots', 'cleanup-report-generator' ),
            'code'        => __( 'Code', 'cleanup-report-generator' ),
        ];
    }

    public static function init() {
        add_filter( 'cleanup_report_types', [ __CLASS__, 'add_types' ], 5 );
        if ( is_admin() ) {
            add_action( 'admin_menu', [ __CLASS__, 'menu' ], 20 );
            add_action( 'admin_post_crg_save_report_type', [ __CLASS__, 'handle_save' ] );
            add_action( 'admin_post_crg_report_type_action', [ __CLASS__, 'handle_action' ] );
            add_action( 'admin_post_crg_preview_report_type', [ __CLASS__, 'handle_preview' ] );
            add_action( 'add_meta_boxes_cleanup_report', [ __CLASS__, 'orphan_box' ], 20 );
            add_action( 'admin_enqueue_scripts', [ __CLASS__, 'assets' ] );
        }
        add_action( 'save_post_cleanup_report', [ __CLASS__, 'save_report' ], 20 );
    }

    /* ================================================================== storage → registry */

    /** Saved custom types: key => definition (as stored). */
    public static function stored() {
        $types = get_option( self::OPTION, [] );
        return is_array( $types ) ? $types : [];
    }

    /** The cleanup_report_types filter: every saved custom type, turned off ones too (their reports keep working). */
    public static function add_types( $types ) {
        foreach ( self::stored() as $key => $def ) {
            if ( ! isset( $types[ $key ] ) && is_array( $def ) ) {
                $types[ $key ] = self::runtime( $key, $def );
            }
        }
        return $types;
    }

    /** The meta key a custom type's field is stored under. */
    public static function meta_key( $type, $field_id ) {
        return 'crg_t_' . $type . '_' . $field_id;
    }

    /** A stored definition → a registry definition (Cleanup_Report_Types::definitions()). Sections are built when needed. */
    public static function runtime( $key, array $def ) {
        // Wording left empty gets the registry's defaults, made from the name (Cleanup_Report_Types::complete()).
        $wording = array_filter( [
            'eyebrow'     => (string) ( $def['eyebrow'] ?? '' ),
            'file'        => (string) ( $def['file'] ?? '' ),
            'prepared_by' => (string) ( $def['prepared_by'] ?? '' ),
        ], 'strlen' );
        return $wording + [
            'label'    => (string) $def['label'],
            'about'    => (string) ( $def['about'] ?? '' ),
            'icon'     => 'media-document',
            'template' => 'generic',
            'custom'   => true,
            'hidden'   => empty( $def['enabled'] ),
            'on_save'  => [ __CLASS__, 'store_snapshot' ],
            'sections' => static function () use ( $key, $def ) { return Cleanup_Report_Type_Builder::sections( $key, $def ); },
        ];
    }

    /** A stored definition's sections as registry sections: the shared "Member & website" first, then its own. */
    public static function sections( $key, array $def ) {
        $out = [ 'site' => Cleanup_Report_Types::site_section() ];
        foreach ( (array) ( $def['sections'] ?? [] ) as $section ) {
            $fields = [];
            foreach ( (array) ( $section['fields'] ?? [] ) as $f ) {
                $fields[] = self::runtime_field( $key, $f );
            }
            $out[ 's_' . $section['id'] ] = [ 'title' => $section['title'], 'fields' => $fields ];
        }
        return $out;
    }

    private static function runtime_field( $type, array $f ) {
        $meta = self::meta_key( $type, $f['id'] );
        $out  = [ 'key' => $meta, 'type' => $f['type'], 'label' => $f['label'], 'help' => $f['help'] ?? '', 'placeholder' => $f['placeholder'] ?? '' ];
        switch ( $f['type'] ) {
            case 'textarea':
            case 'editor':
            case 'code':
                $out['rows'] = (int) ( $f['rows'] ?? 5 );
                break;
            case 'select':
                $out['answers'] = $f['answers'] ?? [];
                $out['note']    = ! empty( $f['note'] );
                break;
            case 'questions':
                $out['questions'] = [];
                foreach ( (array) ( $f['questions'] ?? [] ) as $q ) {
                    $out['questions'][ substr( $meta, 4 ) . '_' . $q['id'] ] = [ $q['question'], $q['label'] ?: $q['question'], $q['answers'], __( 'Note', 'cleanup-report-generator' ) ];
                }
                break;
            case 'table':
                $out['columns'] = [];
                foreach ( (array) ( $f['columns'] ?? [] ) as $i => $c ) {
                    $out['columns'][ 'c' . $i ] = [ $c['label'], $c['placeholder'] ?? '' ];
                }
                break;
            case 'checklist':
                $items          = (array) ( $f['items'] ?? [] );
                $out['items']   = static function () use ( $items ) {
                    return array_map( static function ( $i ) { return $i + [ 'enabled' => true, 'hint' => '', 'good' => '' ]; }, $items );
                };
                $out['note_from'] = 'note';
                break;
            case 'note':
                $standard        = (string) ( $f['standard'] ?? '' );
                $out['standard'] = static function () use ( $standard ) { return $standard; };
                break;
        }
        return $out;
    }

    /* ================================================================== sanitising */

    /** Short key from a label: lowercase letters, digits and underscores. */
    private static function slug( $text, $max = 30 ) {
        $slug = trim( preg_replace( '/[^a-z0-9]+/', '_', strtolower( remove_accents( (string) $text ) ) ), '_' );
        return substr( $slug, 0, $max ) ?: 'item';
    }

    /** A key unique among $used (adds _2, _3 …). */
    private static function unique( $key, array &$used ) {
        $base = $key;
        for ( $n = 2; isset( $used[ $key ] ); $n++ ) {
            $key = substr( $base, 0, 26 ) . '_' . $n;
        }
        $used[ $key ] = true;
        return $key;
    }

    /** "value = Label" lines (value optional) → value => label. Existing values stay as they are. */
    public static function parse_answers( $text ) {
        $out  = [];
        $used = [];
        foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
            $line = trim( $line );
            if ( '' === $line ) {
                continue;
            }
            [ $value, $label ] = false !== strpos( $line, '=' ) ? array_map( 'trim', explode( '=', $line, 2 ) ) : [ '', $line ];
            $label = sanitize_text_field( $label );
            if ( '' === $label ) {
                continue;
            }
            $value         = self::unique( sanitize_key( $value ) ?: self::slug( $label, 20 ), $used );
            $out[ $value ] = $label;
            if ( count( $out ) >= self::LIMITS['options'] ) {
                break;
            }
        }
        return $out;
    }

    public static function answers_text( array $answers ) {
        $lines = [];
        foreach ( $answers as $value => $label ) {
            $lines[] = $value . ' = ' . $label;
        }
        return implode( "\n", $lines );
    }

    /**
     * A definition posted from the editor → a clean stored definition. Keys of existing sections, fields, answers and
     * items are kept (reports' values are stored by them); new ones are made from their labels. Anything not allowed
     * is dropped: unknown field types, HTML outside help and standard texts, empty labels.
     *
     * @param array  $raw      Posted definition (unslashed).
     * @param string $type_key The type's key (fields are unique within it).
     */
    public static function sanitize_definition( array $raw, $type_key ) {
        $types = self::field_types();
        $def   = [
            'label'       => mb_substr( sanitize_text_field( $raw['label'] ?? '' ), 0, 60 ),
            'about'       => mb_substr( sanitize_text_field( $raw['about'] ?? '' ), 0, 200 ),
            'eyebrow'     => mb_substr( sanitize_text_field( $raw['eyebrow'] ?? '' ), 0, 60 ),
            'prepared_by' => mb_substr( sanitize_text_field( $raw['prepared_by'] ?? '' ), 0, 60 ),
            'file'        => substr( sanitize_title( $raw['file'] ?? '' ), 0, 40 ),
            'enabled'     => ! empty( $raw['enabled'] ),
            'sections'    => [],
        ];
        $section_ids = [];
        $field_ids   = [];
        foreach ( array_slice( (array) ( $raw['sections'] ?? [] ), 0, self::LIMITS['sections'] ) as $s ) {
            if ( ! is_array( $s ) ) {
                continue;
            }
            $title = mb_substr( sanitize_text_field( $s['title'] ?? '' ), 0, 80 );
            if ( '' === $title ) {
                continue;
            }
            $section = [ 'id' => self::unique( sanitize_key( $s['id'] ?? '' ) ?: self::slug( $title, 20 ), $section_ids ), 'title' => $title, 'fields' => [] ];
            foreach ( array_slice( (array) ( $s['fields'] ?? [] ), 0, self::LIMITS['fields'] ) as $f ) {
                if ( ! is_array( $f ) || ! isset( $types[ $f['type'] ?? '' ] ) ) {
                    continue;
                }
                $label = mb_substr( sanitize_text_field( $f['label'] ?? '' ), 0, 120 );
                if ( '' === $label ) {
                    continue;
                }
                $field = [
                    'id'          => self::unique( sanitize_key( $f['id'] ?? '' ) ?: self::slug( $label, 24 ), $field_ids ),
                    'type'        => $f['type'],
                    'label'       => $label,
                    'help'        => wp_kses( (string) ( $f['help'] ?? '' ), [ 'code' => [], 'strong' => [], 'em' => [] ] ),
                    'placeholder' => mb_substr( sanitize_text_field( $f['placeholder'] ?? '' ), 0, 120 ),
                ];
                switch ( $f['type'] ) {
                    case 'textarea':
                    case 'editor':
                    case 'code':
                        $field['rows'] = max( 2, min( 20, (int) ( $f['rows'] ?? 5 ) ) );
                        break;
                    case 'select':
                        $field['answers'] = self::parse_answers( is_array( $f['answers'] ?? '' ) ? self::answers_text( $f['answers'] ) : ( $f['answers'] ?? '' ) );
                        $field['note']    = ! empty( $f['note'] );
                        break;
                    case 'questions':
                        $field['questions'] = self::parse_questions( is_array( $f['questions'] ?? '' ) ? self::questions_text( $f['questions'] ) : ( $f['questions'] ?? '' ) );
                        break;
                    case 'table':
                        $field['columns'] = self::parse_columns( is_array( $f['columns'] ?? '' ) ? self::columns_text( $f['columns'] ) : ( $f['columns'] ?? '' ) );
                        break;
                    case 'checklist':
                        $field['items'] = self::parse_items( is_array( $f['items'] ?? '' ) ? self::items_text( $f['items'] ) : ( $f['items'] ?? '' ) );
                        break;
                    case 'note':
                        $field['standard'] = wp_kses_post( (string) ( $f['standard'] ?? '' ) );
                        break;
                }
                $section['fields'][] = $field;
            }
            $def['sections'][] = $section;
        }
        return $def;
    }

    /** "id = Question | Label in the report | answer; answer" lines → [ id, question, label, answers ]. */
    public static function parse_questions( $text ) {
        $out  = [];
        $used = [];
        foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
            [ $id, $rest ] = self::split_id( $line );
            $cols          = array_map( 'trim', explode( '|', $rest ) );
            $question      = mb_substr( sanitize_text_field( $cols[0] ?? '' ), 0, 160 );
            if ( '' === $question ) {
                continue;
            }
            $answers = self::parse_answers( str_replace( ';', "\n", $cols[2] ?? '' ) ) ?: [ 'yes' => __( 'Yes', 'cleanup-report-generator' ), 'no' => __( 'No', 'cleanup-report-generator' ) ];
            $out[]   = [ 'id' => self::unique( $id ?: self::slug( $question, 20 ), $used ), 'question' => $question, 'label' => mb_substr( sanitize_text_field( $cols[1] ?? '' ), 0, 120 ), 'answers' => $answers ];
            if ( count( $out ) >= self::LIMITS['options'] ) {
                break;
            }
        }
        return $out;
    }

    public static function questions_text( array $questions ) {
        return implode( "\n", array_map( static function ( $q ) {
            $answers = [];
            foreach ( $q['answers'] as $value => $label ) {
                $answers[] = $value . ' = ' . $label;
            }
            return $q['id'] . ' = ' . $q['question'] . ' | ' . $q['label'] . ' | ' . implode( '; ', $answers );
        }, $questions ) );
    }

    /** "Label | placeholder" lines → [ label, placeholder ]. */
    public static function parse_columns( $text ) {
        $out = [];
        foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
            $cols  = array_map( 'trim', explode( '|', $line ) );
            $label = mb_substr( sanitize_text_field( $cols[0] ?? '' ), 0, 60 );
            if ( '' !== $label ) {
                $out[] = [ 'label' => $label, 'placeholder' => mb_substr( sanitize_text_field( $cols[1] ?? '' ), 0, 60 ) ];
            }
            if ( count( $out ) >= self::LIMITS['columns'] ) {
                break;
            }
        }
        return $out;
    }

    public static function columns_text( array $columns ) {
        return implode( "\n", array_map( static function ( $c ) { return $c['label'] . ( '' !== $c['placeholder'] ? ' | ' . $c['placeholder'] : '' ); }, $columns ) );
    }

    /** "id = Label | good answer (yes, no or nothing) | hint" lines → checklist items. */
    public static function parse_items( $text ) {
        $out  = [];
        $used = [];
        foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
            [ $id, $rest ] = self::split_id( $line );
            $cols          = array_map( 'trim', explode( '|', $rest ) );
            $label         = mb_substr( sanitize_text_field( $cols[0] ?? '' ), 0, 160 );
            if ( '' === $label ) {
                continue;
            }
            $good  = strtolower( sanitize_text_field( $cols[1] ?? '' ) );
            $out[] = [ 'key' => self::unique( $id ?: self::slug( $label, 24 ), $used ), 'label' => $label, 'good' => in_array( $good, [ 'yes', 'no' ], true ) ? $good : '', 'hint' => mb_substr( sanitize_text_field( $cols[2] ?? '' ), 0, 160 ) ];
            if ( count( $out ) >= self::LIMITS['options'] ) {
                break;
            }
        }
        return $out;
    }

    public static function items_text( array $items ) {
        return implode( "\n", array_map( static function ( $i ) { return $i['key'] . ' = ' . $i['label'] . ' | ' . $i['good'] . ( '' !== $i['hint'] ? ' | ' . $i['hint'] : '' ); }, $items ) );
    }

    /** "id = rest" → [ id, rest ]; a line without "=" before the first "|" has no id. */
    private static function split_id( $line ) {
        $line = trim( (string) $line );
        $bar  = strpos( $line, '|' );
        $eq   = strpos( $line, '=' );
        if ( false !== $eq && ( false === $bar || $eq < $bar ) ) {
            return [ sanitize_key( trim( substr( $line, 0, $eq ) ) ), trim( substr( $line, $eq + 1 ) ) ];
        }
        return [ '', $line ];
    }

    /* ================================================================== reports of custom types */

    /** How many reports (any status but the bin and auto-drafts) use a type. */
    public static function report_count( $type ) {
        $q = new WP_Query( [
            'post_type'              => 'cleanup_report',
            'post_status'            => array_values( array_diff( get_post_stati(), [ 'auto-draft', 'inherit' ] ) ),
            'fields'                 => 'ids',
            'posts_per_page'         => 1,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'meta_query'             => [ [ 'key' => Cleanup_Report_Types::META, 'value' => $type ] ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin screen, one count per type
        ] );
        return (int) $q->found_posts;
    }

    /** A custom type's on_save: the report keeps the definition it was filled in with. */
    public static function store_snapshot( $post_id ) {
        $type   = Cleanup_Report_Types::of( $post_id );
        $stored = self::stored();
        if ( isset( $stored[ $type ] ) ) {
            update_post_meta( $post_id, self::SNAPSHOT, wp_slash( $stored[ $type ] ) );
        }
    }

    /**
     * The definition to draw a report with: an approved (or completed) report of a custom type uses the definition it
     * was saved with, so its PDF doesn't change when the type is edited; everything else uses the current one.
     */
    public static function definition_for_report( $post_id, $type, array $current ) {
        if ( empty( $current['custom'] ) || ! Cleanup_Report_Admin::is_final( $post_id ) ) {
            return $current;
        }
        $snapshot = get_post_meta( $post_id, self::SNAPSHOT, true );
        if ( ! is_array( $snapshot ) || empty( $snapshot['label'] ) ) {
            return $current;
        }
        $def             = Cleanup_Report_Types::complete( $type, self::runtime( $type, $snapshot ) );
        $def['sections'] = self::sections( $type, $snapshot );
        return $def;
    }

    /** This report's values of fields the type no longer has: meta key => [ label, value ]. */
    public static function orphans( $post ) {
        $type = Cleanup_Report_Types::of( $post->ID );
        $def  = Cleanup_Report_Types::get( $type );
        if ( empty( $def['custom'] ) ) {
            return [];
        }
        $current = [];
        foreach ( $def['sections'] as $section ) {
            foreach ( $section['fields'] as $f ) {
                $current = array_merge( $current, Cleanup_Report_Fields::meta_keys( $f ) );
            }
        }
        $labels   = [];
        $snapshot = get_post_meta( $post->ID, self::SNAPSHOT, true );
        foreach ( is_array( $snapshot ) ? self::sections( $type, $snapshot ) : [] as $section ) {
            foreach ( $section['fields'] as $f ) {
                foreach ( Cleanup_Report_Fields::meta_keys( $f ) as $k ) {
                    $labels[ $k ] = $f['label'];
                }
            }
        }
        $prefix = 'crg_t_' . $type . '_';
        $out    = [];
        foreach ( get_post_meta( $post->ID ) as $key => $values ) {
            if ( 0 === strpos( $key, $prefix ) && ! in_array( $key, $current, true ) && '' !== trim( (string) $values[0] ) && 'a:0:{}' !== $values[0] ) {
                $out[ $key ] = [ $labels[ $key ] ?? substr( $key, strlen( $prefix ) ), maybe_unserialize( $values[0] ) ];
            }
        }
        return $out;
    }

    public static function orphan_box( $post ) {
        if ( 'auto-draft' !== $post->post_status && self::orphans( $post ) ) {
            add_meta_box( 'crg_box_orphans', __( 'Fields no longer in this type', 'cleanup-report-generator' ), [ __CLASS__, 'render_orphans' ], 'cleanup_report', 'normal', 'low' );
        }
    }

    public static function render_orphans( $post ) {
        ?>
        <p class="description"><?php esc_html_e( 'These fields were removed from the report type after this report was filled in. Their values are kept and aren\'t shown in the report. Tick a field to remove its value from this report when you save.', 'cleanup-report-generator' ); ?></p>
        <table class="crg-table crg-orphans">
            <thead><tr><th><?php esc_html_e( 'Field', 'cleanup-report-generator' ); ?></th><th><?php esc_html_e( 'Value', 'cleanup-report-generator' ); ?></th><th><?php esc_html_e( 'Remove', 'cleanup-report-generator' ); ?></th></tr></thead>
            <tbody>
            <?php foreach ( self::orphans( $post ) as $key => [ $label, $value ] ) :
                $text = is_array( $value ) ? wp_json_encode( $value ) : wp_strip_all_tags( (string) $value ); ?>
                <tr>
                    <td><strong><?php echo esc_html( $label ); ?></strong></td>
                    <td><?php echo esc_html( wp_html_excerpt( (string) $text, 200, '…' ) ); ?></td>
                    <td><label><input type="checkbox" name="crg_remove_orphans[]" value="<?php echo esc_attr( $key ); ?>"> <span class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %s: field name */ __( 'Remove %s', 'cleanup-report-generator' ), $label ) ); ?></span></label></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /** Edit form save: removes the ticked orphaned values (same checks as the rest of the form). */
    public static function save_report( $post_id ) {
        if ( empty( $_POST['crg_remove_orphans'] ) || ! is_array( $_POST['crg_remove_orphans'] ) || ! isset( $_POST['crg_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['crg_nonce'] ) ), 'crg_save_' . $post_id ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) || Cleanup_Report_Access::locked( $post_id ) ) {
            return;
        }
        $orphans = self::orphans( get_post( $post_id ) );
        foreach ( array_map( 'sanitize_key', wp_unslash( $_POST['crg_remove_orphans'] ) ) as $key ) {
            if ( isset( $orphans[ $key ] ) ) {
                delete_post_meta( $post_id, $key );
            }
        }
    }

    /* ================================================================== admin screens */

    public static function menu() {
        add_submenu_page( 'edit.php?post_type=cleanup_report', __( 'Report types', 'cleanup-report-generator' ), __( 'Report types', 'cleanup-report-generator' ), 'manage_options', self::PAGE, [ __CLASS__, 'page' ] );
    }

    public static function assets( $hook ) {
        if ( 'cleanup_report_page_' . self::PAGE !== $hook ) {
            return;
        }
        wp_enqueue_style( 'crg-admin', CLEANUP_REPORT_URL . 'assets/admin.css', [ 'dashicons' ], CLEANUP_REPORT_VERSION . '.' . filemtime( CLEANUP_REPORT_PATH . 'assets/admin.css' ) );
        wp_enqueue_script( 'crg-type-builder', CLEANUP_REPORT_URL . 'assets/type-builder.js', [ 'jquery', 'wp-i18n' ], CLEANUP_REPORT_VERSION . '.' . filemtime( CLEANUP_REPORT_PATH . 'assets/type-builder.js' ), true );
        wp_set_script_translations( 'crg-type-builder', 'cleanup-report-generator', CLEANUP_REPORT_PATH . 'languages' );
    }

    public static function url( array $args = [] ) {
        return add_query_arg( $args, admin_url( 'edit.php?post_type=cleanup_report&page=' . self::PAGE ) );
    }

    public static function page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage report types.', 'cleanup-report-generator' ), '', [ 'response' => 403 ] );
        }
        $edit = sanitize_key( wp_unslash( $_GET['edit'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which type to show
        $from = sanitize_key( wp_unslash( $_GET['from'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which type to start from
        if ( '' !== $edit || isset( $_GET['new'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only
            self::editor( $edit, $from );
            return;
        }
        self::list_page();
    }

    private static function notice() {
        $done = sanitize_key( wp_unslash( $_GET['crg_done'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- notice only
        $messages = [
            'saved'    => __( 'Report type saved.', 'cleanup-report-generator' ),
            'enabled'  => __( 'Report type turned on: it can be picked for new reports.', 'cleanup-report-generator' ),
            'disabled' => __( 'Report type turned off: it can\'t be picked for new reports; its reports still open and print.', 'cleanup-report-generator' ),
            'deleted'  => __( 'Report type deleted.', 'cleanup-report-generator' ),
            'in_use'   => __( 'This report type is used by reports, so it can only be turned off, not deleted.', 'cleanup-report-generator' ),
            'invalid'  => __( 'Give the report type a name and at least one section with a field.', 'cleanup-report-generator' ),
        ];
        if ( isset( $messages[ $done ] ) ) {
            printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', in_array( $done, [ 'in_use', 'invalid' ], true ) ? 'error' : 'success', esc_html( $messages[ $done ] ) );
        }
    }

    private static function list_page() {
        $stored = self::stored();
        $all    = Cleanup_Report_Types::definitions();
        ?>
        <div class="wrap crg-types">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Report types', 'cleanup-report-generator' ); ?></h1>
            <a href="<?php echo esc_url( self::url( [ 'new' => 1 ] ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add report type', 'cleanup-report-generator' ); ?></a>
            <hr class="wp-header-end">
            <?php self::notice(); ?>
            <p class="crg-lead"><?php esc_html_e( 'The kinds of report your team can write. Built-in types come with the plugin; your own types use the fields you choose and the standard report layout.', 'cleanup-report-generator' ); ?></p>
            <table class="widefat striped crg-types-table">
                <thead><tr><th><?php esc_html_e( 'Report type', 'cleanup-report-generator' ); ?></th><th><?php esc_html_e( 'Sections', 'cleanup-report-generator' ); ?></th><th><?php esc_html_e( 'Reports', 'cleanup-report-generator' ); ?></th><th><?php esc_html_e( 'Status', 'cleanup-report-generator' ); ?></th><th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'cleanup-report-generator' ); ?></span></th></tr></thead>
                <tbody>
                <?php foreach ( $all as $key => $def ) :
                    $custom  = isset( $stored[ $key ] );
                    $count   = self::report_count( $key );
                    $nonce   = wp_create_nonce( 'crg_type_' . $key );
                    $act     = static function ( $do ) use ( $key, $nonce ) { return wp_nonce_url( admin_url( 'admin-post.php?action=crg_report_type_action&do=' . $do . '&type=' . $key ), 'crg_type_' . $key ); };
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo $custom ? '<a href="' . esc_url( self::url( [ 'edit' => $key ] ) ) . '">' . esc_html( $def['label'] ) . '</a>' : esc_html( $def['label'] ); ?></strong>
                            <span class="crg-muted"> · <?php echo $custom ? esc_html__( 'Your type', 'cleanup-report-generator' ) : esc_html__( 'Built in', 'cleanup-report-generator' ); ?></span>
                            <?php if ( '' !== (string) $def['about'] ) : ?><br><span class="crg-muted"><?php echo esc_html( $def['about'] ); ?></span><?php endif; ?>
                        </td>
                        <td data-label="<?php esc_attr_e( 'Sections', 'cleanup-report-generator' ); ?>"><?php echo $custom ? (int) count( $stored[ $key ]['sections'] ) : '—'; ?></td>
                        <td data-label="<?php esc_attr_e( 'Reports', 'cleanup-report-generator' ); ?>"><?php echo $count ? '<a href="' . esc_url( admin_url( 'edit.php?post_type=cleanup_report&crg_type=' . $key ) ) . '">' . (int) $count . '</a>' : '0'; ?></td>
                        <td><?php echo empty( $def['hidden'] ) ? '<span class="crg-status-badge crg-status-approved">' . esc_html__( 'On', 'cleanup-report-generator' ) . '</span>' : '<span class="crg-status-badge">' . esc_html__( 'Off', 'cleanup-report-generator' ) . '</span>'; ?></td>
                        <td class="crg-types-actions">
                            <?php if ( $custom ) : ?>
                                <a href="<?php echo esc_url( self::url( [ 'edit' => $key ] ) ); ?>"><?php esc_html_e( 'Edit', 'cleanup-report-generator' ); ?></a>
                                <a href="<?php echo esc_url( $act( empty( $def['hidden'] ) ? 'disable' : 'enable' ) ); ?>"><?php echo empty( $def['hidden'] ) ? esc_html__( 'Turn off', 'cleanup-report-generator' ) : esc_html__( 'Turn on', 'cleanup-report-generator' ); ?></a>
                                <?php if ( ! $count ) : ?><a class="crg-trash crg-type-delete" href="<?php echo esc_url( $act( 'delete' ) ); ?>" data-confirm="<?php echo esc_attr( sprintf( /* translators: %s: report type */ __( 'Delete the report type "%s"? This can\'t be undone.', 'cleanup-report-generator' ), $def['label'] ) ); ?>"><?php esc_html_e( 'Delete', 'cleanup-report-generator' ); ?></a><?php endif; ?>
                            <?php endif; ?>
                            <a href="<?php echo esc_url( self::url( [ 'new' => 1, 'from' => $key ] ) ); ?>"><?php esc_html_e( 'Duplicate', 'cleanup-report-generator' ); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /** A built-in type as a starting point: only its generic fields can be copied (the built-in reports' special ones can't). */
    private static function from_registry( $key ) {
        $def = Cleanup_Report_Types::get( $key );
        /* translators: %s: report type */
        $out     = [ 'label' => sprintf( __( '%s (copy)', 'cleanup-report-generator' ), $def['label'] ), 'about' => $def['about'], 'enabled' => true, 'sections' => [], 'skipped' => [] ];
        $allowed = self::field_types();
        $special = [
            'key_finding'     => __( 'Key finding', 'cleanup-report-generator' ),
            'severity_counts' => __( 'Issue counts', 'cleanup-report-generator' ),
            'plugin_check'    => __( 'Plugin check', 'cleanup-report-generator' ),
            'components'      => __( 'Vulnerable components', 'cleanup-report-generator' ),
            'blocklists'      => __( 'Blocklists', 'cleanup-report-generator' ),
            'defender'        => __( 'Defender checklist', 'cleanup-report-generator' ),
            'followups'       => __( 'Follow-ups', 'cleanup-report-generator' ),
            'scores'          => __( 'PageSpeed scores', 'cleanup-report-generator' ),
        ];
        foreach ( $def['sections'] as $skey => $section ) {
            if ( 'site' === $skey ) {
                continue;
            }
            $fields = [];
            foreach ( $section['fields'] as $f ) {
                $f = Cleanup_Report_Fields::defaults( $f );
                if ( isset( $allowed[ $f['type'] ] ) && '' === (string) $f['label'] ) {
                    $f['label'] = $section['title']; // e.g. a checklist that is its section's only content
                }
                if ( ! isset( $allowed[ $f['type'] ] ) || '' === (string) $f['label'] ) {
                    $out['skipped'][] = $f['label'] ?: ( $special[ $f['type'] ] ?? $f['type'] );
                    continue;
                }
                $copy = [ 'id' => '', 'type' => $f['type'], 'label' => $f['label'], 'help' => wp_strip_all_tags( (string) $f['help'] ), 'placeholder' => $f['placeholder'], 'rows' => $f['rows'] ];
                if ( 'questions' === $f['type'] ) {
                    $copy['questions'] = [];
                    foreach ( $f['questions'] as $qk => $q ) {
                        $copy['questions'][] = [ 'id' => self::slug( $qk, 20 ), 'question' => $q[0], 'label' => $q[1], 'answers' => $q[2] ];
                    }
                } elseif ( 'checklist' === $f['type'] ) {
                    $copy['items'] = array_map( static function ( $i ) { return [ 'key' => $i['key'], 'label' => $i['label'], 'good' => $i['good'] ?? '', 'hint' => $i['hint'] ?? '' ]; }, array_values( array_filter( Cleanup_Report_Fields::checklist_items( $f ), static function ( $i ) { return ! empty( $i['enabled'] ); } ) ) );
                } elseif ( 'note' === $f['type'] ) {
                    $copy['standard'] = is_callable( $f['standard'] ?? null ) ? (string) call_user_func( $f['standard'], get_post( 0 ) ?: new WP_Post( (object) [ 'ID' => 0 ] ) ) : '';
                } elseif ( 'select' === $f['type'] ) {
                    $copy['answers'] = $f['answers'] ?? [];
                    $copy['note']    = ! empty( $f['note'] );
                } elseif ( 'table' === $f['type'] ) {
                    $copy['columns'] = array_map( static function ( $c ) { return [ 'label' => $c[0], 'placeholder' => $c[1] ?? '' ]; }, array_values( $f['columns'] ?? [] ) );
                }
                $fields[] = $copy;
            }
            if ( $fields ) {
                $out['sections'][] = [ 'id' => '', 'title' => $section['title'], 'fields' => $fields ];
            }
        }
        return $out;
    }

    private static function editor( $key, $from ) {
        $stored = self::stored();
        $def    = $stored[ $key ] ?? null;
        if ( '' !== $key && ! $def ) {
            wp_die( esc_html__( 'Report type not found.', 'cleanup-report-generator' ), '', [ 'response' => 404, 'back_link' => true ] );
        }
        if ( ! $def ) {
            if ( isset( $stored[ $from ] ) ) {
                $def          = $stored[ $from ];
                /* translators: %s: report type */
                $def['label'] = sprintf( __( '%s (copy)', 'cleanup-report-generator' ), $def['label'] );
                $def['sections'] = array_map( static function ( $s ) { $s['id'] = ''; return $s; }, $def['sections'] ); // fresh keys for the copy
            } elseif ( '' !== $from && Cleanup_Report_Types::exists( $from ) ) {
                $def = self::from_registry( $from );
            } else {
                $def = [ 'label' => '', 'about' => '', 'enabled' => true, 'sections' => [ [ 'id' => '', 'title' => __( 'Details', 'cleanup-report-generator' ), 'fields' => [ [ 'id' => '', 'type' => 'editor', 'label' => __( 'Summary', 'cleanup-report-generator' ), 'help' => '' ] ] ] ] ];
            }
        }
        $def += [ 'about' => '', 'eyebrow' => '', 'prepared_by' => '', 'file' => '', 'enabled' => true ];
        $count = '' !== $key ? self::report_count( $key ) : 0;
        ?>
        <div class="wrap crg-types crg-type-editor">
            <h1><?php echo '' !== $key ? esc_html( sprintf( /* translators: %s: report type */ __( 'Edit report type: %s', 'cleanup-report-generator' ), $def['label'] ) ) : esc_html__( 'Add report type', 'cleanup-report-generator' ); ?></h1>
            <p><a href="<?php echo esc_url( self::url() ); ?>">← <?php esc_html_e( 'All report types', 'cleanup-report-generator' ); ?></a></p>
            <?php self::notice(); ?>
            <?php if ( ! empty( $def['skipped'] ) ) : ?>
                <div class="notice notice-info"><p><?php echo esc_html( sprintf( /* translators: %s: field names */ __( 'Copied from a built-in type. These fields are special to it and weren\'t copied: %s.', 'cleanup-report-generator' ), implode( ', ', $def['skipped'] ) ) ); ?></p></div>
            <?php endif; ?>
            <?php if ( $count ) : ?>
                <div class="notice notice-warning inline"><p><?php echo esc_html( sprintf( /* translators: %d: number of reports */ _n( '%d report uses this type. Renaming or reordering is safe. A removed field keeps its value in that report, listed on its edit screen; approved reports keep printing as they were approved.', '%d reports use this type. Renaming or reordering is safe. A removed field keeps its values in those reports, listed on their edit screens; approved reports keep printing as they were approved.', $count, 'cleanup-report-generator' ), $count ) ); ?></p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crg-type-form">
                <input type="hidden" name="action" value="crg_save_report_type">
                <input type="hidden" name="type" value="<?php echo esc_attr( $key ); ?>">
                <?php wp_nonce_field( 'crg_save_report_type' ); ?>
                <div class="crg-type-labels">
                    <div class="crg-field"><label for="crg-type-label"><?php esc_html_e( 'Name', 'cleanup-report-generator' ); ?></label><input type="text" id="crg-type-label" name="def[label]" value="<?php echo esc_attr( $def['label'] ); ?>" required maxlength="60" placeholder="<?php esc_attr_e( 'e.g. Website migration', 'cleanup-report-generator' ); ?>"><p class="description"><?php esc_html_e( 'Shown in the Reports menu ("Add … report"), the list and on the report\'s cover.', 'cleanup-report-generator' ); ?></p></div>
                    <div class="crg-field"><label for="crg-type-about"><?php esc_html_e( 'Description', 'cleanup-report-generator' ); ?></label><input type="text" id="crg-type-about" name="def[about]" value="<?php echo esc_attr( $def['about'] ); ?>" maxlength="200"><p class="description"><?php esc_html_e( 'One line on the "Add report" chooser.', 'cleanup-report-generator' ); ?></p></div>
                    <details class="crg-type-more">
                        <summary><?php esc_html_e( 'Report wording (optional)', 'cleanup-report-generator' ); ?></summary>
                        <div class="crg-cols crg-cols-3">
                            <div class="crg-field"><label for="crg-type-eyebrow"><?php esc_html_e( 'Cover label', 'cleanup-report-generator' ); ?></label><input type="text" id="crg-type-eyebrow" name="def[eyebrow]" value="<?php echo esc_attr( $def['eyebrow'] ); ?>" maxlength="60" placeholder="<?php esc_attr_e( '"<Name> report"', 'cleanup-report-generator' ); ?>"></div>
                            <div class="crg-field"><label for="crg-type-by"><?php esc_html_e( '"Prepared by" team', 'cleanup-report-generator' ); ?></label><input type="text" id="crg-type-by" name="def[prepared_by]" value="<?php echo esc_attr( $def['prepared_by'] ); ?>" maxlength="60" placeholder="<?php esc_attr_e( 'team', 'cleanup-report-generator' ); ?>"></div>
                            <div class="crg-field"><label for="crg-type-file"><?php esc_html_e( 'PDF file name part', 'cleanup-report-generator' ); ?></label><input type="text" id="crg-type-file" name="def[file]" value="<?php echo esc_attr( $def['file'] ); ?>" maxlength="40" placeholder="<?php esc_attr_e( 'name-report', 'cleanup-report-generator' ); ?>"></div>
                        </div>
                    </details>
                    <label class="crg-type-enabled"><input type="checkbox" name="def[enabled]" value="1" <?php checked( ! empty( $def['enabled'] ) ); ?>> <?php esc_html_e( 'On: staff can add reports of this type', 'cleanup-report-generator' ); ?></label>
                </div>

                <h2><?php esc_html_e( 'Sections and fields', 'cleanup-report-generator' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Every report starts with "Member & website" (who it\'s for, the website and the date). Add the sections that follow it, in the order of the report. Each section is a box on the edit screen and a numbered section in the PDF.', 'cleanup-report-generator' ); ?></p>
                <div class="crg-type-sections">
                    <?php foreach ( array_values( $def['sections'] ) as $i => $section ) { self::section_card( 's' . $i, $section ); } ?>
                </div>
                <template class="crg-type-section-template"><?php self::section_card( '__s__', [ 'id' => '', 'title' => '', 'fields' => [] ] ); ?></template>
                <template class="crg-type-field-template"><?php self::field_card( '__s__', '__f__', [ 'id' => '', 'type' => 'text', 'label' => '' ] ); ?></template>
                <p><button type="button" class="button crg-type-add-section"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e( 'Add section', 'cleanup-report-generator' ); ?></button></p>
                <div class="crg-settings-actions">
                    <?php submit_button( __( 'Save report type', 'cleanup-report-generator' ), 'primary', 'submit', false ); ?>
                    <?php if ( '' !== $key ) : ?>
                        <a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=crg_preview_report_type&type=' . $key ), 'crg_preview_type_' . $key ) ); ?>"><?php esc_html_e( 'Preview the report', 'cleanup-report-generator' ); ?></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php
    }

    private static function section_card( $si, array $section ) {
        $name = 'def[sections][' . $si . ']';
        ?>
        <div class="crg-type-section" data-index="<?php echo esc_attr( $si ); ?>">
            <div class="crg-type-section-head">
                <input type="hidden" name="<?php echo esc_attr( $name . '[id]' ); ?>" value="<?php echo esc_attr( $section['id'] ); ?>">
                <label class="screen-reader-text" for="<?php echo esc_attr( 'crg-sec-' . $si ); ?>"><?php esc_html_e( 'Section title', 'cleanup-report-generator' ); ?></label>
                <input type="text" id="<?php echo esc_attr( 'crg-sec-' . $si ); ?>" class="crg-type-section-title" name="<?php echo esc_attr( $name . '[title]' ); ?>" value="<?php echo esc_attr( $section['title'] ); ?>" placeholder="<?php esc_attr_e( 'Section title, e.g. Migration', 'cleanup-report-generator' ); ?>" maxlength="80">
                <span class="crg-type-tools">
                    <button type="button" class="button-link crg-type-up" aria-label="<?php esc_attr_e( 'Move section up', 'cleanup-report-generator' ); ?>"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
                    <button type="button" class="button-link crg-type-down" aria-label="<?php esc_attr_e( 'Move section down', 'cleanup-report-generator' ); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
                    <button type="button" class="button-link crg-type-remove" aria-label="<?php esc_attr_e( 'Remove section', 'cleanup-report-generator' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>
                </span>
            </div>
            <div class="crg-type-fields">
                <?php foreach ( array_values( $section['fields'] ) as $j => $f ) { self::field_card( $si, 'f' . $j, $f ); } ?>
            </div>
            <p class="crg-type-add">
                <label class="screen-reader-text" for="<?php echo esc_attr( 'crg-add-' . $si ); ?>"><?php esc_html_e( 'Field type to add', 'cleanup-report-generator' ); ?></label>
                <select id="<?php echo esc_attr( 'crg-add-' . $si ); ?>" class="crg-type-new-kind">
                    <?php foreach ( self::field_types() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
                </select>
                <button type="button" class="button crg-type-add-field"><?php esc_html_e( 'Add field', 'cleanup-report-generator' ); ?></button>
            </p>
        </div>
        <?php
    }

    private static function field_card( $si, $fi, array $f ) {
        $f    += [ 'help' => '', 'placeholder' => '', 'rows' => 5, 'answers' => [], 'note' => false, 'questions' => [], 'columns' => [], 'items' => [], 'standard' => '' ];
        $name  = 'def[sections][' . $si . '][fields][' . $fi . ']';
        $id    = 'crg-f-' . $si . '-' . $fi;
        $types = self::field_types();
        $opt   = static function ( $field, $label, $value, $help, $kinds, $rows = 3 ) use ( $name, $id ) {
            ?>
            <div class="crg-field crg-type-opt" data-kinds="<?php echo esc_attr( implode( ' ', $kinds ) ); ?>">
                <label for="<?php echo esc_attr( $id . '-' . $field ); ?>"><?php echo esc_html( $label ); ?></label>
                <textarea id="<?php echo esc_attr( $id . '-' . $field ); ?>" name="<?php echo esc_attr( $name . '[' . $field . ']' ); ?>" rows="<?php echo (int) $rows; ?>" class="code"><?php echo esc_textarea( $value ); ?></textarea>
                <p class="description"><?php echo esc_html( $help ); ?></p>
            </div>
            <?php
        };
        ?>
        <details class="crg-type-field" data-kind="<?php echo esc_attr( $f['type'] ); ?>"<?php echo '' === $f['label'] ? ' open' : ''; ?>>
            <summary>
                <span class="crg-type-kind"><?php echo esc_html( $types[ $f['type'] ] ?? $f['type'] ); ?></span>
                <span class="crg-type-field-title"><?php echo esc_html( $f['label'] ?: __( 'New field', 'cleanup-report-generator' ) ); ?></span>
                <span class="crg-type-tools">
                    <button type="button" class="button-link crg-type-up" aria-label="<?php esc_attr_e( 'Move field up', 'cleanup-report-generator' ); ?>"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
                    <button type="button" class="button-link crg-type-down" aria-label="<?php esc_attr_e( 'Move field down', 'cleanup-report-generator' ); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
                    <button type="button" class="button-link crg-type-remove" aria-label="<?php esc_attr_e( 'Remove field', 'cleanup-report-generator' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>
                </span>
            </summary>
            <div class="crg-type-field-body">
                <input type="hidden" name="<?php echo esc_attr( $name . '[id]' ); ?>" value="<?php echo esc_attr( $f['id'] ); ?>">
                <div class="crg-cols crg-cols-2">
                    <div class="crg-field"><label for="<?php echo esc_attr( $id . '-label' ); ?>"><?php esc_html_e( 'Label', 'cleanup-report-generator' ); ?></label><input type="text" id="<?php echo esc_attr( $id . '-label' ); ?>" class="crg-type-field-label" name="<?php echo esc_attr( $name . '[label]' ); ?>" value="<?php echo esc_attr( $f['label'] ); ?>" maxlength="120"></div>
                    <div class="crg-field"><label for="<?php echo esc_attr( $id . '-type' ); ?>"><?php esc_html_e( 'Kind', 'cleanup-report-generator' ); ?></label><select id="<?php echo esc_attr( $id . '-type' ); ?>" class="crg-type-field-kind" name="<?php echo esc_attr( $name . '[type]' ); ?>"><?php foreach ( $types as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $f['type'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="crg-field"><label for="<?php echo esc_attr( $id . '-help' ); ?>"><?php esc_html_e( 'Help for staff', 'cleanup-report-generator' ); ?></label><input type="text" id="<?php echo esc_attr( $id . '-help' ); ?>" name="<?php echo esc_attr( $name . '[help]' ); ?>" value="<?php echo esc_attr( $f['help'] ); ?>"></div>
                <div class="crg-field crg-type-opt" data-kinds="text url number textarea"><label for="<?php echo esc_attr( $id . '-ph' ); ?>"><?php esc_html_e( 'Placeholder', 'cleanup-report-generator' ); ?></label><input type="text" id="<?php echo esc_attr( $id . '-ph' ); ?>" name="<?php echo esc_attr( $name . '[placeholder]' ); ?>" value="<?php echo esc_attr( $f['placeholder'] ); ?>"></div>
                <div class="crg-field crg-type-opt" data-kinds="textarea editor code"><label for="<?php echo esc_attr( $id . '-rows' ); ?>"><?php esc_html_e( 'Height (lines)', 'cleanup-report-generator' ); ?></label><input type="number" id="<?php echo esc_attr( $id . '-rows' ); ?>" name="<?php echo esc_attr( $name . '[rows]' ); ?>" value="<?php echo (int) $f['rows']; ?>" min="2" max="20" class="small-text"></div>
                <?php
                $opt( 'answers', __( 'Answers', 'cleanup-report-generator' ), self::answers_text( (array) $f['answers'] ), __( 'One per line. Keep the part before "=" as it is once reports use it.', 'cleanup-report-generator' ), [ 'select' ] );
                ?>
                <label class="crg-type-opt" data-kinds="select"><input type="checkbox" name="<?php echo esc_attr( $name . '[note]' ); ?>" value="1" <?php checked( ! empty( $f['note'] ) ); ?>> <?php esc_html_e( 'With a note beside the answer', 'cleanup-report-generator' ); ?></label>
                <?php
                $opt( 'questions', __( 'Questions', 'cleanup-report-generator' ), self::questions_text( (array) $f['questions'] ), __( 'One per line: Question | Label in the report | answers separated by ";" (Yes and No when left out).', 'cleanup-report-generator' ), [ 'questions' ], 4 );
                $opt( 'columns', __( 'Columns', 'cleanup-report-generator' ), self::columns_text( (array) $f['columns'] ), __( 'One per line: Column name | placeholder (optional). Up to 8.', 'cleanup-report-generator' ), [ 'table' ] );
                $opt( 'items', __( 'Checklist items', 'cleanup-report-generator' ), self::items_text( (array) $f['items'] ), __( 'One per line: Item | good answer (yes, no or nothing) | hint for staff. The good answer shows green, the other red.', 'cleanup-report-generator' ), [ 'checklist' ], 5 );
                ?>
                <div class="crg-field crg-type-opt" data-kinds="note"><label for="<?php echo esc_attr( $id . '-std' ); ?>"><?php esc_html_e( 'Standard text', 'cleanup-report-generator' ); ?></label><textarea id="<?php echo esc_attr( $id . '-std' ); ?>" name="<?php echo esc_attr( $name . '[standard]' ); ?>" rows="4"><?php echo esc_textarea( $f['standard'] ); ?></textarea><p class="description"><?php esc_html_e( 'Used in the report when staff leave the field empty. Basic HTML (paragraphs, lists, links) is allowed.', 'cleanup-report-generator' ); ?></p></div>
            </div>
        </details>
        <?php
    }

    /* ================================================================== actions */

    /** Every action: its nonce is checked first (check_admin_referer() in the handler), then this. */
    private static function guard() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage report types.', 'cleanup-report-generator' ), '', [ 'response' => 403, 'back_link' => true ] );
        }
    }

    public static function handle_save() {
        check_admin_referer( 'crg_save_report_type' );
        self::guard();
        $stored = self::stored();
        $key    = sanitize_key( wp_unslash( $_POST['type'] ?? '' ) );
        $raw    = isset( $_POST['def'] ) && is_array( $_POST['def'] ) ? wp_unslash( $_POST['def'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_definition() cleans every part
        if ( '' !== $key && ! isset( $stored[ $key ] ) ) {
            $key = ''; // not one of ours: never overwrite a built-in or a code-defined type
        }
        // The edit form posts existing keys; a new type gets one from its name, never clashing with another type.
        $new_key = $key;
        if ( '' === $new_key ) {
            $used = array_fill_keys( array_keys( Cleanup_Report_Types::definitions() ), true ) + array_fill_keys( [ 'site', 'malware', 'optimization' ], true );
            $new_key = self::unique( self::slug( sanitize_text_field( $raw['label'] ?? '' ), 24 ), $used );
        }
        $def = self::sanitize_definition( $raw, $new_key );
        if ( '' === $def['label'] || ! array_filter( $def['sections'], static function ( $s ) { return ! empty( $s['fields'] ); } ) ) {
            wp_safe_redirect( self::url( '' !== $key ? [ 'edit' => $key, 'crg_done' => 'invalid' ] : [ 'new' => 1, 'crg_done' => 'invalid' ] ) );
            exit;
        }
        $stored[ $new_key ] = $def;
        update_option( self::OPTION, $stored, false ); // not autoloaded: only wp-admin, WP-Cron and the PDF read it
        Cleanup_Report_Types::flush();
        wp_safe_redirect( self::url( [ 'edit' => $new_key, 'crg_done' => 'saved' ] ) );
        exit;
    }

    public static function handle_action() {
        $key = sanitize_key( wp_unslash( $_GET['type'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is checked on the next line
        check_admin_referer( 'crg_type_' . $key );
        self::guard();
        $stored = self::stored();
        if ( ! isset( $stored[ $key ] ) ) {
            wp_safe_redirect( self::url() );
            exit;
        }
        $do = sanitize_key( wp_unslash( $_GET['do'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is checked on the next line
        if ( 'delete' === $do ) {
            if ( self::report_count( $key ) ) {
                wp_safe_redirect( self::url( [ 'crg_done' => 'in_use' ] ) );
                exit;
            }
            unset( $stored[ $key ] );
            $done = 'deleted';
        } else {
            $stored[ $key ]['enabled'] = 'enable' === $do;
            $done                      = 'enable' === $do ? 'enabled' : 'disabled';
        }
        update_option( self::OPTION, $stored, false );
        wp_safe_redirect( self::url( [ 'crg_done' => $done ] ) );
        exit;
    }

    /** "Preview the report": the type's report drawn with sample values, without creating a report. */
    public static function handle_preview() {
        $key = sanitize_key( wp_unslash( $_GET['type'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is checked on the next line
        check_admin_referer( 'crg_preview_type_' . $key );
        self::guard();
        if ( ! Cleanup_Report_Types::exists( $key ) ) {
            wp_die( esc_html__( 'Report type not found.', 'cleanup-report-generator' ), '', [ 'response' => 404 ] );
        }
        $def = Cleanup_Report_Types::get( $key );
        $m   = [ 'crg_client_name' => __( 'Sample member', 'cleanup-report-generator' ), 'crg_site_url' => 'https://example.com', 'crg_report_date' => wp_date( 'Y-m-d' ) ];
        foreach ( $def['sections'] as $skey => $section ) {
            if ( 'site' === $skey ) {
                continue;
            }
            foreach ( $section['fields'] as $f ) {
                $f = Cleanup_Report_Fields::defaults( $f );
                $m += self::sample( $f );
            }
        }
        $data = [
            'post_id' => 0, 'type' => $key, 'type_info' => $def, 'final' => false, 'watermark' => __( 'PREVIEW', 'cleanup-report-generator' ),
            'title' => $def['label'], 'client' => $m['crg_client_name'], 'site' => $m['crg_site_url'], 'date' => $m['crg_report_date'], 'request_link' => '',
            'brand' => Cleanup_Report_Render::brand(), 'screenshots' => [], 'opt' => [], 'generic' => Cleanup_Report_Render::generic_sections( 0, $def, $m ),
            'web' => [ 'edit' => self::url( [ 'edit' => $key ] ) ],
        ];
        status_header( 200 );
        nocache_headers();
        header( 'Content-Type: text/html; charset=utf-8' );
        echo Cleanup_Report_PDF::render_html( $data, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the template escapes its output
        exit;
    }

    /** Sample values for a field, for the preview: meta key => value. */
    private static function sample( array $f ) {
        $sample = __( 'Sample text', 'cleanup-report-generator' );
        switch ( $f['type'] ) {
            case 'select':
                return [ $f['key'] => (string) array_key_first( (array) $f['answers'] ) ];
            case 'questions':
                $out = [];
                foreach ( $f['questions'] as $qk => $q ) {
                    $out[ 'crg_' . $qk ] = (string) array_key_first( $q[2] );
                }
                return $out;
            case 'table':
                return [ $f['key'] => implode( ' | ', array_fill( 0, max( 1, count( $f['columns'] ?? [] ) ), $sample ) ) ];
            case 'date':
                return [ $f['key'] => wp_date( 'Y-m-d' ) ];
            case 'number':
                return [ $f['key'] => '3' ];
            case 'url':
                return [ $f['key'] => 'https://example.com/' ];
            case 'editor':
                return [ $f['key'] => '<p>' . $sample . '</p><ul><li>' . $sample . '</li></ul>' ];
            case 'checklist':
            case 'screenshots':
            case 'code':
            case 'note':
                return [];
            default:
                return [ $f['key'] => $sample ];
        }
    }
}
