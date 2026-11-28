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

	/** Opens or closes a field's options. */
	function setOpen( $field, open ) {
		$field.toggleClass( 'is-open', open );
		$field.find( '.crg-type-toggle' ).attr( 'aria-expanded', open ? 'true' : 'false' );
		$field.find( '.crg-type-field-body' ).prop( 'hidden', ! open );
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
		var $live = $form.find( '.crg-type-live' );
		var dirty = false;

		/** Tells screen reader users what just happened. */
		function say( text ) {
			$live.text( '' );
			window.setTimeout( function () { $live.text( text ); }, 50 );
		}

		/** The first item can't move up and the last can't move down: those buttons are disabled, not hidden. */
		function refreshArrows() {
			$form.find( '.crg-type-sections, .crg-type-fields' ).each( function () {
				var $items = $( this ).children( '.crg-type-section, .crg-type-field' );
				$items.each( function ( i ) {
					var $tools = $( this ).find( '> .crg-type-section-head .crg-type-tools, > .crg-type-field-head .crg-type-tools' ).first();
					$tools.find( '.crg-type-up' ).prop( 'disabled', 0 === i );
					$tools.find( '.crg-type-down' ).prop( 'disabled', i === $items.length - 1 );
				} );
			} );
		}

		$form.on( 'input change', function () { dirty = true; } );
		$form.on( 'submit', function () { dirty = false; } );
		$( window ).on( 'beforeunload', function () { return dirty ? true : undefined; } );

		// Fields start closed (without JavaScript every field shows open); a new or unnamed one starts open.
		$form.find( '.crg-type-sections .crg-type-field' ).each( function () {
			showOptions( $( this ) );
			setOpen( $( this ), !! $( this ).data( 'open' ) );
		} );
		refreshArrows();

		$form.on( 'click', '.crg-type-toggle', function () {
			var $field = $( this ).closest( '.crg-type-field' );
			setOpen( $field, ! $field.hasClass( 'is-open' ) );
		} );
		$form.on( 'change', '.crg-type-field-kind', function () {
			showOptions( $( this ).closest( '.crg-type-field' ) );
		} );
		$form.on( 'input', '.crg-type-field-label', function () {
			$( this ).closest( '.crg-type-field' ).find( '.crg-type-field-title' ).text( $( this ).val() || __( 'New field', 'cleanup-report-generator' ) );
		} );

		$form.on( 'click', '.crg-type-add-section', function () {
			var $section = fromTemplate( '.crg-type-section-template', { __s__: 'n' + ( ++added ) } );
			$form.find( '.crg-type-sections' ).append( $section );
			refreshArrows();
			$section.find( '.crg-type-section-title' ).trigger( 'focus' );
			dirty = true;
		} );

		$form.on( 'click', '.crg-type-add-field', function () {
			var $section = $( this ).closest( '.crg-type-section' );
			var $field   = fromTemplate( '.crg-type-field-template', { __s__: $section.data( 'index' ), __f__: 'n' + ( ++added ) } );
			$field.find( '.crg-type-field-kind' ).val( $section.find( '.crg-type-new-kind' ).val() );
			$section.find( '.crg-type-fields' ).append( $field );
			showOptions( $field );
			setOpen( $field, true );
			refreshArrows();
			$field.find( '.crg-type-field-label' ).trigger( 'focus' );
			dirty = true;
		} );

		$form.on( 'click', '.crg-type-up, .crg-type-down, .crg-type-remove', function () {
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
				refreshArrows();
				( $next.length ? $next.find( 'button:not(:disabled), input' ).first() : $form.find( '.crg-type-add-section' ) ).trigger( 'focus' );
				say( isField ? __( 'Field removed.', 'cleanup-report-generator' ) : __( 'Section removed.', 'cleanup-report-generator' ) );
			} else {
				var up = $button.hasClass( 'crg-type-up' );
				if ( up && $item.prev().length ) {
					$item.insertBefore( $item.prev() );
				} else if ( ! up && $item.next().length ) {
					$item.insertAfter( $item.next() );
				} else {
					return;
				}
				refreshArrows();
				// Keep focus on the button; at the top or bottom it's disabled, so move to the other arrow.
				( $button.prop( 'disabled' ) ? $button.siblings( up ? '.crg-type-down' : '.crg-type-up' ) : $button ).trigger( 'focus' );
				say( isField ? ( up ? __( 'Field moved up.', 'cleanup-report-generator' ) : __( 'Field moved down.', 'cleanup-report-generator' ) ) : ( up ? __( 'Section moved up.', 'cleanup-report-generator' ) : __( 'Section moved down.', 'cleanup-report-generator' ) ) );
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
