/* Handover: Reports › Report types (the list and the type editor). */
( function ( $ ) {
	'use strict';

	var __ = ( window.wp && wp.i18n && wp.i18n.__ ) || function ( text ) { return text; };
	var added = 0; // new sections and fields get unique form names: n1, n2 …

	/** Shows the options that belong to the field's kind (answers for a choice, columns for a table …). */
	function showOptions( $field ) {
		var kind = $field.find( '.crg-type-field-kind' ).val();
		$field.attr( 'data-kind', kind );
		$field.find( '.crg-type-kind' ).text( $field.find( '.crg-type-field-kind option:selected' ).text() );
		$field.find( '.crg-type-opt' ).each( function () {
			var show = -1 !== ( ' ' + $( this ).data( 'kinds' ) + ' ' ).indexOf( ' ' + kind + ' ' );
			$( this ).prop( 'hidden', ! show ).find( 'input, textarea, select' ).prop( 'disabled', ! show );
		} );
	}

	/** A template's HTML with its placeholders replaced. */
	function fromTemplate( selector, replace ) {
		var html = $( selector ).html();
		$.each( replace, function ( from, to ) { html = html.split( from ).join( to ); } );
		return $( $.parseHTML( $.trim( html ) ) );
	}

	function initEditor() {
		var $form = $( '.crg-type-form' );
		if ( ! $form.length ) {
			return;
		}
		var dirty = false;
		$form.on( 'input change', function () { dirty = true; } );
		$form.on( 'submit', function () { dirty = false; } );
		$( window ).on( 'beforeunload', function () { return dirty ? true : undefined; } );

		$form.find( '.crg-type-field' ).each( function () { showOptions( $( this ) ); } );

		$form.on( 'change', '.crg-type-field-kind', function () {
			showOptions( $( this ).closest( '.crg-type-field' ) );
		} );
		$form.on( 'input', '.crg-type-field-label', function () {
			$( this ).closest( '.crg-type-field' ).find( '.crg-type-field-title' ).text( $( this ).val() || __( 'New field', 'cleanup-report-generator' ) );
		} );

		$form.on( 'click', '.crg-type-add-section', function () {
			var $section = fromTemplate( '.crg-type-section-template', { __s__: 'n' + ( ++added ) } );
			$form.find( '.crg-type-sections' ).append( $section );
			$section.find( '.crg-type-section-title' ).trigger( 'focus' );
			dirty = true;
		} );

		$form.on( 'click', '.crg-type-add-field', function () {
			var $section = $( this ).closest( '.crg-type-section' );
			var $field   = fromTemplate( '.crg-type-field-template', { __s__: $section.data( 'index' ), __f__: 'n' + ( ++added ) } );
			$field.find( '.crg-type-field-kind' ).val( $section.find( '.crg-type-new-kind' ).val() );
			$section.find( '.crg-type-fields' ).append( $field );
			showOptions( $field );
			$field.prop( 'open', true ).find( '.crg-type-field-label' ).trigger( 'focus' );
			dirty = true;
		} );

		// Buttons in a field's summary line move or remove it; they mustn't also open or close it.
		$form.on( 'click', '.crg-type-up, .crg-type-down, .crg-type-remove', function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			var $button = $( this );
			var $item   = $button.closest( '.crg-type-field' ).length ? $button.closest( '.crg-type-field' ) : $button.closest( '.crg-type-section' );
			var isField = $item.hasClass( 'crg-type-field' );
			if ( $button.hasClass( 'crg-type-remove' ) ) {
				var named = isField ? $item.find( '.crg-type-field-label' ).val() : $item.find( '.crg-type-section-title' ).val();
				var ask   = isField ? __( 'Remove this field? Reports that use it keep their value until it is removed from them.', 'cleanup-report-generator' ) : __( 'Remove this section and its fields? Reports that use them keep their values until they are removed from them.', 'cleanup-report-generator' );
				if ( named && ! window.confirm( ask ) ) {
					return;
				}
				var $next = $item.next().length ? $item.next() : $item.prev();
				$item.remove();
				( $next.length ? $next.find( 'button, input' ).first() : $form.find( isField ? '.crg-type-add-field' : '.crg-type-add-section' ).first() ).trigger( 'focus' );
			} else if ( $button.hasClass( 'crg-type-up' ) && $item.prev().length ) {
				$item.insertBefore( $item.prev() );
				$button.trigger( 'focus' );
			} else if ( $button.hasClass( 'crg-type-down' ) && $item.next().length ) {
				$item.insertAfter( $item.next() );
				$button.trigger( 'focus' );
			}
			dirty = true;
		} );
	}

	$( function () {
		initEditor();
		$( '.crg-type-delete' ).on( 'click', function ( e ) {
			if ( ! window.confirm( $( this ).data( 'confirm' ) ) ) {
				e.preventDefault();
			}
		} );
	} );
}( jQuery ) );
