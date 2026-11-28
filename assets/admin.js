/* Cleanup Report Generator: report edit screen and Reports › Settings. */
( function ( $ ) {
	'use strict';

	/* ------------------------------------------------------------ shared */

	/** Bare domain → https://…; for the website URL also drop wp-admin paths, queries and the trailing slash. Mirrors CRG_Admin::normalize_url(). */
	function normalizeUrl( url, site ) {
		url = $.trim( url );
		if ( ! url ) {
			return '';
		}
		if ( ! /^[a-z][a-z0-9+.-]*:\/\//i.test( url ) ) {
			url = 'https://' + url.replace( /^\/+/, '' );
		}
		if ( site ) {
			url = url.replace( /[?#].*$/, '' )
				.replace( /\/(wp-admin|wp-login\.php|wp-content|wp-includes)(\/.*)?$/i, '' )
				.replace( /\/+$/, '' )
				.replace( /^([a-z]+:\/\/)([^/]+)/i, function ( m ) { return m.toLowerCase(); } );
		}
		return url;
	}

	function siteUrl() {
		return normalizeUrl( $( '#crg_site_url' ).val() || '', true );
	}

	/** {site}, {host}, {wp_admin} as the report will fill them in. Mirrors CRG_Checklists::fill(). */
	function fill( text ) {
		var site = siteUrl(), host = site.replace( /^[a-z]+:\/\//i, '' ).replace( /\/.*$/, '' );
		return String( text || '' )
			.split( '{site}' ).join( site || 'your website' )
			.split( '{host}' ).join( host || 'your website' )
			.split( '{wp_admin}' ).join( site ? site + '/wp-admin' : 'your WordPress dashboard' );
	}

	/** Media library picker: <button class="crg-media" data-target="selector"> fills that input with the image URL. */
	$( document ).on( 'click', '.crg-media', function ( e ) {
		e.preventDefault();
		if ( ! window.wp || ! wp.media ) {
			return;
		}
		var $btn = $( this ), $scope = $btn.closest( '.crg-shot, .crg-logo-field, td' );
		var $input = $scope.find( $btn.data( 'target' ) );
		var frame = wp.media( { title: 'Choose an image', button: { text: 'Use this image' }, multiple: false, library: { type: 'image' } } );
		frame.on( 'select', function () {
			var a = frame.state().get( 'selection' ).first().toJSON();
			$input.val( a.url ).trigger( 'change' );
			var $title = $scope.find( '.crg-shot-title' );
			if ( $title.length && ! $.trim( $title.val() ) ) {
				$title.val( a.caption || a.title || '' );
			}
		} );
		frame.open();
	} );

	function sortable( $list, items ) {
		if ( $.fn.sortable && $list.length ) {
			$list.sortable( { items: items, handle: '.crg-drag', axis: 'y', tolerance: 'pointer', placeholder: 'crg-sort-placeholder', forcePlaceholderSize: true } );
		}
	}

	/* ------------------------------------------------------------ report edit screen */

	function initReport() {
		var $form = $( 'form#post' );
		if ( ! $form.length || ! $( '#crg_box_site' ).length ) {
			return;
		}

		/* Section links: open the box if it's collapsed, then scroll to it. */
		$( '.crg-nav a' ).on( 'click', function ( e ) {
			var $box = $( $( this ).attr( 'href' ) );
			if ( $box.length ) {
				e.preventDefault();
				$box.removeClass( 'closed' );
				$( 'html, body' ).animate( { scrollTop: $box.offset().top - 40 }, 200 );
			}
		} );

		/* URLs: tidy on blur. Title: suggest one from the member or site while the title is empty. */
		var $title = $( '#title' ), titleAuto = ! $.trim( $title.val() );
		$title.on( 'input', function () { titleAuto = ! $.trim( this.value ); } );
		function suggestTitle() {
			if ( ! titleAuto ) {
				return;
			}
			var name = siteUrl().replace( /^[a-z]+:\/\//i, '' ) || $.trim( $( '#crg_client_name' ).val() );
			$title.val( name ? name + ' – malware cleanup' : '' );
			$( '#title-prompt-text' ).toggleClass( 'screen-reader-text', !! name );
		}
		$( '[data-crg-url]' ).on( 'blur change', function () {
			var v = normalizeUrl( this.value, 'site' === $( this ).data( 'crg-url' ) );
			if ( v !== this.value ) {
				this.value = v;
			}
			if ( 'site' === $( this ).data( 'crg-url' ) ) {
				suggestTitle();
				refreshChecklists();
				var host = siteUrl().replace( /^[a-z]+:\/\//i, '' ).replace( /\/.*$/, '' );
				$( '#crg_seo_note' ).closest( '.crg-field' ).find( '.crg-std-text li, .crg-std-text p' ).each( function () {
					$( this ).html( $( this ).html().replace( /site:[^\s<]+/, 'site:' + ( host || 'yourdomain.com' ) ) );
				} );
			}
		} );
		$( '#crg_client_name' ).on( 'input', suggestTitle );

		/* Screenshots */
		var $shots = $( '.crg-shots' ), next = parseInt( $shots.data( 'next' ), 10 ) || 0;
		sortable( $shots, '> .crg-shot' );
		$( '.crg-shot-add' ).on( 'click', function () {
			var html = $( '.crg-shot-template' ).html().split( '__i__' ).join( 'n' + ( next++ ) );
			$shots.append( html );
			$shots.find( '.crg-shot' ).last().find( '.crg-shot-title' ).trigger( 'focus' );
		} );
		$shots.on( 'click', '.crg-remove', function () {
			$( this ).closest( '.crg-shot' ).remove();
		} );
		$shots.on( 'change input', '.crg-shot-url', function () {
			var url = $.trim( this.value ), $thumb = $( this ).closest( '.crg-shot' ).find( '.crg-shot-thumb' );
			$thumb.html( /^https?:\/\//i.test( url ) ? $( '<img alt="">' ).attr( 'src', url ) : '<span class="dashicons dashicons-format-image"></span>' );
		} );

		initComponents();
		initPluginCheck();
		initChecklists();
		initDelist();

		/* Severity counts: "Vulnerable components" can be taken from the table. */
		var $useCount = $( '.crg-count-components' );
		function updateCountLink() {
			var n = componentCount();
			$useCount.prop( 'hidden', ! n || String( n ) === $( '#crg_summary_medium' ).val() ).text( 'use ' + n + ' from the table below' );
		}
		$( document ).on( 'crg:components', updateCountLink );
		$( '#crg_summary_medium' ).on( 'input', updateCountLink );
		$useCount.on( 'click', function () {
			$( '#crg_summary_medium' ).val( componentCount() ).trigger( 'input' );
		} );
		updateCountLink();

		/* SEO / Staying protected: copy the standard text into the editor to change it. */
		$( '.crg-use-std' ).on( 'click', function () {
			var id = $( this ).data( 'editor' ), html = $( this ).siblings( '.crg-std-text' ).html(), ed = window.tinymce && tinymce.get( id );
			if ( ed && ! ed.isHidden() ) {
				if ( $.trim( ed.getContent( { format: 'text' } ) ) && ! window.confirm( 'Replace what\'s in the editor with the standard text?' ) ) {
					return;
				}
				ed.setContent( html );
				ed.fire( 'change' );
			} else {
				$( '#' + id ).val( html ).trigger( 'change' );
			}
			$( this ).closest( 'details' ).prop( 'open', false );
		} );

		/* Approved report, Writer: everything read-only except the Review box (to mark it Completed). */
		if ( $( 'body' ).hasClass( 'crg-locked' ) ) {
			$( '#title, .postbox[id^="crg_box_"] :input' ).not( '#crg_box_export :input' ).prop( 'disabled', true );
			$( '.crg-shot-add, .crg-component-add, .crg-pc-run, .crg-set-all, .crg-use-std' ).prop( 'disabled', true );
			$( document ).on( 'tinymce-editor-init', function ( e, editor ) {
				editor.mode.set( 'readonly' );
			} );
			if ( window.tinymce ) {
				tinymce.editors.forEach( function ( ed ) { ed.mode.set( 'readonly' ); } );
			}
		}

		/* Save & review box (replaces WordPress's Publish box): the Save button says what it will do. */
		var $status = $( '#crg_review_status' ), $save = $( '#crg-save' );
		function syncSave() {
			var $opt = $status.find( 'option:selected' );
			if ( $opt.length ) {
				$save.val( $opt.data( 'label' ) );
				$( '#crg_post_status' ).val( $opt.data( 'post-status' ) );
				// The one-click "Submit for review" only while no other status is picked.
				$( '.crg-submit-review' ).prop( 'hidden', $status.val() !== $status.data( 'current' ) );
			}
		}
		$status.on( 'change', syncSave );
		syncSave();

		/* Writer: Submit for review in one click. */
		$( '.crg-submit-review' ).on( 'click', function () {
			$status.val( 'submitted' );
			syncSave();
			$save.trigger( 'click' );
		} );

		/* One save at a time (WordPress only does this for its own Publish box). */
		$form.on( 'submit', function () {
			setTimeout( function () { $( '#crg-save, .crg-submit-review' ).prop( 'disabled', true ); }, 0 );
		} );

		/* Unsaved changes: the PDF buttons use the saved version. */
		var dirty = false;
		$form.on( 'change input', ':input', function () {
			if ( ! dirty ) {
				dirty = true;
				$( '.crg-dirty' ).prop( 'hidden', false );
				$( '.crg-export-note' ).prop( 'hidden', true );
			}
		} );
		$( document ).on( 'tinymce-editor-init', function ( e, editor ) {
			editor.on( 'dirty change', function () { $form.trigger( 'input' ); } );
		} );
		$form.on( 'submit', function () { dirty = false; } );
	}

	/* ---------- components table (the textarea stays the saved value: "Name | Version | Issue | Severity" lines) */

	var $compText, $compRows;

	function componentCount() {
		return $compRows ? $compRows.find( 'tr' ).filter( function () { return $.trim( $( this ).find( '.c-name' ).val() ); } ).length : 0;
	}

	/** Typed severity → critical | high | medium | low ('' if not recognised). Mirrors CRG_Render::severity(). */
	function severity( text ) {
		var map = {
			critical: [ 'critical', 'crit', 'severe', 'urgent' ],
			high: [ 'high', 'hi' ],
			medium: [ 'medium', 'med', 'mid', 'moderate' ],
			low: [ 'low', 'lo', 'minor', 'info', 'informational' ]
		};
		text = $.trim( String( text || '' ) ).toLowerCase();
		for ( var level in map ) {
			if ( map[ level ].indexOf( text ) > -1 ) {
				return level;
			}
		}
		return '';
	}

	function componentRow( c ) {
		var sev = severity( c.sev ) || 'medium';
		var $tr = $( '<tr>' +
			'<td class="crg-col-drag"><span class="crg-drag dashicons dashicons-menu" title="Drag to reorder"></span></td>' +
			'<td><input type="text" class="c-name" placeholder="Slider Revolution" aria-label="Plugin or theme"></td>' +
			'<td><input type="text" class="c-version" placeholder="6.8.1" aria-label="Installed version"></td>' +
			'<td><input type="text" class="c-issue" placeholder="5 known vulnerabilities; update to 7.1.0+" aria-label="Issue"></td>' +
			'<td><select class="c-sev" aria-label="Severity"><option value="critical">Critical</option><option value="high">High</option><option value="medium">Medium</option><option value="low">Low</option></select></td>' +
			'<td class="crg-col-x"><button type="button" class="button-link crg-remove" title="Remove"><span class="dashicons dashicons-no-alt"></span><span class="screen-reader-text">Remove</span></button></td>' +
			'</tr>' );
		$tr.find( '.c-name' ).val( c.name || '' );
		$tr.find( '.c-version' ).val( c.version || '' );
		$tr.find( '.c-issue' ).val( c.issue || '' );
		$tr.find( '.c-sev' ).val( sev ).attr( 'data-sev', sev );
		return $tr;
	}

	function textToRows() {
		var unknown = [];
		$compRows.empty();
		$.each( ( $compText.val() || '' ).split( /\r?\n/ ), function ( i, line ) {
			var cols = $.map( line.split( '|' ), $.trim );
			if ( cols[ 0 ] ) {
				if ( cols[ 3 ] && ! severity( cols[ 3 ] ) ) {
					unknown.push( '"' + cols[ 3 ] + '" (' + cols[ 0 ] + ')' );
				}
				$compRows.append( componentRow( { name: cols[ 0 ], version: cols[ 1 ], issue: cols[ 2 ], sev: cols[ 3 ] } ) );
			}
		} );
		// Say so rather than silently changing a severity we don't recognise.
		$( '.crg-components-warn' ).prop( 'hidden', ! unknown.length ).find( 'p' ).text(
			unknown.length ? 'Not a severity, so set to Medium: ' + unknown.join( ', ' ) + '. Use critical, high, medium or low.' : ''
		);
		componentsChanged();
	}

	function rowsToText() {
		var clean = function ( v ) { return $.trim( String( v || '' ).replace( /[|\r\n]+/g, '/' ) ); };
		var lines = $compRows.find( 'tr' ).map( function () {
			var $r = $( this ), name = clean( $r.find( '.c-name' ).val() );
			return name ? [ name, clean( $r.find( '.c-version' ).val() ), clean( $r.find( '.c-issue' ).val() ), $r.find( '.c-sev' ).val() ].join( ' | ' ) : null;
		} ).get();
		$compText.val( lines.join( '\n' ) );
		componentsChanged();
	}

	function componentsChanged() {
		$( '.crg-components-empty' ).prop( 'hidden', $compRows.children().length > 0 );
		$compRows.closest( 'table' ).prop( 'hidden', ! $compRows.children().length );
		$( document ).trigger( 'crg:components' );
	}

	function initComponents() {
		$compText = $( '#crg_components' );
		$compRows = $( '.crg-components-rows' );
		if ( ! $compText.length ) {
			return;
		}
		var $textWrap = $( '.crg-components-text' ).prop( 'hidden', true ), asText = false;
		textToRows();
		sortable( $compRows, '> tr' );
		$compRows.on( 'sortupdate', rowsToText );
		$compRows.on( 'input change', ':input', function () {
			if ( $( this ).is( '.c-sev' ) ) {
				$( this ).attr( 'data-sev', this.value );
			}
			rowsToText();
		} );
		$compRows.on( 'click', '.crg-remove', function () {
			$( this ).closest( 'tr' ).remove();
			rowsToText();
		} );
		$( '.crg-component-add' ).on( 'click', function () {
			if ( asText ) {
				$( '.crg-components-toggle' ).trigger( 'click' );
			}
			$compRows.append( componentRow( { sev: 'medium' } ) );
			componentsChanged();
			$compRows.find( 'tr' ).last().find( '.c-name' ).trigger( 'focus' );
		} );
		$( '.crg-components-toggle' ).on( 'click', function () {
			asText = ! asText;
			if ( ! asText ) {
				textToRows();
			}
			$textWrap.prop( 'hidden', ! asText );
			$compRows.closest( 'table' ).prop( 'hidden', asText || ! $compRows.children().length );
			$( '.crg-components-empty' ).prop( 'hidden', asText || $compRows.children().length > 0 );
			$( this ).text( asText ? 'Back to table' : 'Edit as text' );
		} );
	}

	/* ---------- Check plugins & themes */

	var PC_STATUS = {
		closed: [ 'Closed', 'bad' ],
		abandoned: [ 'Not maintained', 'bad' ],
		vulnerable: [ 'Vulnerable', 'bad' ],
		outdated: [ 'Update available', 'warn' ],
		unknown: [ 'Unknown', 'none' ],
		ok: [ 'OK', 'good' ]
	};

	/** Add a <li> to a list editor (Reinstall / Replace), in the visual or the code tab. */
	function appendListItem( id, li ) {
		var ed = window.tinymce && tinymce.get( id ), $ta = $( '#' + id );
		var add = function ( html ) {
			html = $.trim( html || '' );
			return /<\/ul>\s*$/i.test( html ) ? html.replace( /<\/ul>\s*$/i, li + '</ul>' ) : html + '<ul>' + li + '</ul>';
		};
		if ( ed && ! ed.isHidden() ) {
			ed.setContent( add( ed.getContent() ) );
			ed.fire( 'change' );
		} else {
			$ta.val( add( $ta.val() ) ).trigger( 'change' );
		}
	}

	function esc( text ) {
		return $( '<div>' ).text( text || '' ).html();
	}

	function initPluginCheck() {
		var $box = $( '.crg-pc' );
		if ( ! $box.length ) {
			return;
		}
		var $out = $box.find( '.crg-pc-results' ), $msg = $box.find( '.crg-pc-msg' ), $spin = $box.find( '.spinner' );

		$box.find( '.crg-pc-copy' ).on( 'click', function () {
			var text = $box.find( '.crg-pc-cmd code' ).text(), $b = $( this );
			( navigator.clipboard ? navigator.clipboard.writeText( text ) : $.Deferred().reject() ).then(
				function () { $b.text( 'Copied' ); setTimeout( function () { $b.text( 'Copy' ); }, 1500 ); },
				function () { window.prompt( 'Copy this command:', text ); }
			);
		} );

		function inComponents( name ) {
			name = name.toLowerCase();
			return $compRows.find( '.c-name' ).filter( function () { return $.trim( this.value ).toLowerCase() === name; } ).length > 0;
		}
		function addComponent( r ) {
			if ( ! inComponents( r.name ) ) {
				$compRows.append( componentRow( { name: r.name, version: r.installed, issue: r.issue, sev: r.severity } ) );
				rowsToText();
			}
		}
		function addToList( r, list ) {
			var reason = 'replace' === list ? ' – ' + r.issue.charAt( 0 ).toLowerCase() + r.issue.slice( 1 ) : '';
			appendListItem( 'replace' === list ? 'crg_replace_list' : 'crg_reinstall_list', '<li>' + esc( r.name ) + ' <code>' + esc( r.slug ) + '</code>' + esc( reason ) + '</li>' );
		}

		function render( rows ) {
			var flagged = rows.filter( function ( r ) { return r.issue; } );
			var counts = {};
			rows.forEach( function ( r ) { counts[ r.status ] = ( counts[ r.status ] || 0 ) + 1; } );
			var summary = $.map( PC_STATUS, function ( s, key ) { return counts[ key ] ? counts[ key ] + ' ' + s[ 0 ].toLowerCase() : null; } ).join( ' · ' );
			var $table = $( '<table class="crg-table crg-pc-table"><thead><tr><th>Plugin / theme</th><th class="crg-pc-v">Version</th><th class="crg-pc-s">Status</th><th>Why</th><th class="crg-pc-a">Add to report</th></tr></thead><tbody></tbody></table>' );
			rows.forEach( function ( r ) {
				var s = PC_STATUS[ r.status ] || PC_STATUS.unknown;
				var $tr = $( '<tr>' ).addClass( 'is-' + s[ 1 ] );
				var $name = $( '<td>' ).append( $( '<strong>' ).text( r.name ) ).append( $( '<span class="crg-pc-slug">' ).text( r.slug + ( 'theme' === r.type ? ' · theme' : '' ) + ( 'premium' === r.source ? ' · premium' : '' ) ) );
				var ver = r.installed + ( r.latest && r.latest !== r.installed ? ' → ' + r.latest : '' );
				var $why = $( '<td class="crg-pc-why">' );
				( r.reasons.length ? r.reasons : [ 'premium' === r.source ? 'Marked as maintained in your plugin list' : 'Maintained, up to date, no known vulnerabilities' ] ).forEach( function ( t ) { $why.append( $( '<div>' ).text( t ) ); } );
				if ( r.errors.length ) {
					$why.append( $( '<div class="crg-pc-err">' ).text( r.errors.join( '; ' ) + '. Try again later.' ) );
				}
				if ( r.link ) {
					$why.append( $( '<a target="_blank" rel="noopener">' ).attr( 'href', r.link ).text( 'premium' === r.source ? 'Vendor' : 'WordPress.org' ) );
				}
				var $act = $( '<td class="crg-pc-a">' );
				var button = function ( label, fn ) {
					return $( '<button type="button" class="button button-small">' ).text( label ).on( 'click', function () {
						fn();
						$( this ).prop( 'disabled', true ).text( 'Added' );
					} );
				};
				if ( r.issue ) {
					$act.append( button( 'Components', function () { addComponent( r ); } ) );
				}
				if ( 'replace' === r.list ) {
					$act.append( button( 'Replace list', function () { addToList( r, 'replace' ); } ) );
				}
				if ( 'wporg' !== r.source && 'replace' !== r.list ) {
					$act.append( button( 'Reinstall list', function () { addToList( r, 'reinstall' ); } ) );
				}
				$tr.append( $name, $( '<td class="crg-pc-v">' ).text( ver || '—' ), $( '<td class="crg-pc-s">' ).append( $( '<span class="crg-pc-pill">' ).addClass( 'is-' + r.status ).text( s[ 0 ] ) ), $why, $act );
				$table.find( 'tbody' ).append( $tr );
			} );
			$out.empty();
			if ( flagged.length ) {
				$out.append( $( '<p class="crg-pc-all">' ).append(
					$( '<button type="button" class="button">' ).text( 'Add all ' + flagged.length + ' flagged to the report' ).on( 'click', function () {
						flagged.forEach( function ( r ) {
							addComponent( r );
							if ( 'replace' === r.list ) {
								addToList( r, 'replace' );
							}
						} );
						$out.find( '.crg-pc-a button' ).filter( function () { return /Components|Replace/.test( $( this ).text() ); } ).prop( 'disabled', true ).text( 'Added' );
						$( this ).prop( 'disabled', true ).text( 'Added to the components table and the Replace list' );
					} ),
					$( '<span class="description">' ).text( ' Components table, plus the Replace list for closed or abandoned items. Review the wording before sending.' )
				) );
			}
			$out.append( $table );
			$msg.text( rows.length + ' checked: ' + summary );
		}

		$box.find( '.crg-pc-run' ).on( 'click', function () {
			var $b = $( this ), list = $.trim( $( '#crg_plugin_list' ).val() );
			if ( ! list ) {
				$msg.text( 'Paste the command\'s output first.' );
				$( '#crg_plugin_list' ).trigger( 'focus' );
				return;
			}
			$b.prop( 'disabled', true );
			$spin.addClass( 'is-active' );
			$msg.text( 'Checking… (up to half a minute the first time)' );
			$.post( $box.data( 'ajax' ), { action: 'crg_check_plugins', nonce: $box.data( 'nonce' ), post_id: $box.data( 'post' ), list: list } )
				.done( function ( res ) {
					if ( res && res.success ) {
						render( res.data.rows );
					} else {
						$msg.text( ( res && res.data && res.data.message ) || 'The check failed.' );
					}
				} )
				.fail( function () { $msg.text( 'The check failed (network or server error). Try again.' ); } )
				.always( function () { $b.prop( 'disabled', false ); $spin.removeClass( 'is-active' ); } );
		} );
	}

	/* ---------- Defender and follow-up checklists */

	function refreshRow( $row ) {
		var status = $row.find( 'input[type=radio]:checked' ).val() || '';
		var previews = $row.data( 'previews' ) || {};
		$row.removeClass( 'is-applied is-pending is-in_progress is-required is-not_required is-fixed' );
		if ( status ) {
			$row.addClass( 'is-' + status );
		}
		$row.find( '.crg-check-note' ).attr( 'placeholder', fill( previews[ status ] ) ).prop( 'disabled', ! status );
	}

	function refreshCount( $list ) {
		var counts = {}, total = 0;
		$list.find( '.crg-check' ).each( function () {
			var s = $( this ).find( 'input[type=radio]:checked' ).val() || '';
			counts[ s ] = ( counts[ s ] || 0 ) + 1;
			total++;
		} );
		var text = 'defender' === $list.data( 'kind' )
			? ( counts.applied || 0 ) + ' applied · ' + ( counts.in_progress || 0 ) + ' pending · ' + ( counts.pending || 0 ) + ' for the member'
			: ( counts.not_required || 0 ) + ' already configured · ' + ( counts.fixed || 0 ) + ' done · ' + ( counts.required || 0 ) + ' for the member';
		$list.find( '.crg-checklist-count' ).text( total ? text + ( counts[ '' ] ? ' · ' + counts[ '' ] + ' left out' : '' ) : '' );
	}

	function refreshChecklists() {
		// "Open on site": the item's link on the report's website (hidden until a website URL is entered).
		var site = siteUrl();
		$( '.crg-check-link' ).each( function () {
			$( this ).attr( 'href', site ? fill( $( this ).data( 'link' ) ) : '#' ).prop( 'hidden', ! site );
		} );
		$( '.crg-checklist' ).each( function () {
			var $list = $( this );
			$list.find( '.crg-check' ).each( function () { refreshRow( $( this ) ); } );
			refreshCount( $list );
		} );
		// Security blocklists: VirusTotal's report for the website, to see which vendors still flag it.
		$( '.crg-vt' ).attr( 'href', site ? 'https://www.virustotal.com/gui/search/' + encodeURIComponent( site ) : '#' );
		$( '.crg-vt-wrap' ).prop( 'hidden', ! site );
	}

	/* Security blocklists: filter the vendor list and count the ticked ones. */
	function initDelist() {
		var $box = $( '.crg-delist' );
		if ( ! $box.length ) {
			return;
		}
		var count = function () {
			var n = $box.find( '.crg-delist-grid input:checked' ).length;
			$box.find( '.crg-delist-count' ).text( n ? n + ( 1 === n ? ' vendor ticked' : ' vendors ticked' ) : '' );
		};
		$box.on( 'input', '.crg-delist-filter', function () {
			var q = $.trim( $( this ).val() ).toLowerCase();
			$box.find( '.crg-delist-grid label' ).each( function () {
				var $l = $( this );
				$l.prop( 'hidden', q && -1 === $l.text().toLowerCase().indexOf( q ) && ! $l.find( 'input' ).prop( 'checked' ) );
			} );
		} ).on( 'keydown', '.crg-delist-filter', function ( e ) {
			if ( 'Enter' === e.key ) {
				e.preventDefault(); // don't submit the post form
			}
		} ).on( 'change', '.crg-delist-grid input', count );
		count();
	}

	function initChecklists() {
		/* Follow-ups have no "Leave out" button: clicking the selected status again (or Esc) clears it. */
		var clear = function ( $input ) {
			var $none = $input.closest( '.crg-seg' ).find( 'input[value=""]' );
			if ( $none.closest( 'label' ).is( '[hidden]' ) ) {
				$none.prop( 'checked', true ).trigger( 'change' );
				return true;
			}
			return false;
		};
		$( '.crg-checklist' ).on( 'mousedown', '.crg-seg label', function () {
			$( this ).data( 'was', $( this ).find( 'input' ).prop( 'checked' ) );
		} ).on( 'click', '.crg-seg label', function () {
			var $label = $( this );
			if ( $label.data( 'was' ) ) {
				// After the browser's own handling (cancelling a radio click would restore the old selection).
				setTimeout( function () { clear( $label.find( 'input' ) ); }, 0 );
			}
			$label.data( 'was', false );
		} ).on( 'keydown', '.crg-seg input', function ( e ) {
			if ( 'Escape' === e.key && clear( $( this ) ) ) {
				e.preventDefault();
			}
		} );
		$( '.crg-checklist' ).on( 'change', 'input[type=radio]', function () {
			refreshRow( $( this ).closest( '.crg-check' ) );
			refreshCount( $( this ).closest( '.crg-checklist' ) );
		} );
		$( '.crg-set-all' ).on( 'click', function () {
			var status = String( $( this ).data( 'status' ) || '' ), $list = $( this ).closest( '.crg-checklist' );
			$list.find( '.crg-check' ).each( function () {
				$( this ).find( 'input[type=radio]' ).filter( function () { return this.value === status; } ).prop( 'checked', true );
			} );
			refreshChecklists();
			$list.find( 'input[type=radio]' ).first().trigger( 'input' );
		} );
		refreshChecklists();
	}

	/* ------------------------------------------------------------ Reports › Settings */

	function initSettings() {
		var $wrap = $( '.crg-settings' );
		if ( ! $wrap.length ) {
			return;
		}
		if ( $.fn.wpColorPicker ) {
			$wrap.find( '.crg-color' ).wpColorPicker();
		}
		/* Branding: a colour preset fills the primary, status colours and cover gradient (saved with Save Changes). */
		$wrap.on( 'click', '.crg-preset', function () {
			var preset = $( this ).data( 'preset' ) || {};
			$.each( preset, function ( key, value ) {
				if ( 'glow' === key ) {
					$( '#crg_cover_glow' ).val( value );
					return;
				}
				var $input = $( 'primary' === key ? '#crg_brand_primary' : '#crg_color_' + key );
				if ( $input.length ) {
					$.fn.wpColorPicker && $input.data( 'a8cIris' ) ? $input.wpColorPicker( 'color', value ) : $input.val( value );
				}
			} );
			$wrap.find( '.crg-preset' ).removeClass( 'is-picked' );
			$( this ).addClass( 'is-picked' );
		} );
		$wrap.on( 'change input', '.crg-logo-url', function () {
			var url = $.trim( this.value ), $p = $( this ).siblings( '.crg-logo-preview' );
			$p.toggleClass( 'is-empty', ! url ).html( url ? $( '<img alt="">' ).attr( 'src', url ) : '' );
		} );

		/* Plugin list: filter rows as you type; confirm deletions. */
		$wrap.find( '.crg-pl-search' ).on( 'input', function () {
			var q = $.trim( this.value ).toLowerCase();
			$wrap.find( '.crg-pl-row' ).each( function () {
				$( this ).toggle( ! q || String( $( this ).data( 'search' ) ).indexOf( q ) > -1 );
			} );
		} );
		$wrap.find( '.crg-pl' ).closest( 'form' ).on( 'submit', function () {
			var n = $( this ).find( '.crg-pl-row input[name$="[delete]"]:checked' ).length;
			return ! n || window.confirm( 'Delete ' + n + ' item' + ( 1 === n ? '' : 's' ) + ' from the list? They come back if a report checks them again.' );
		} );

		var $items = $wrap.find( '.crg-items' ), n = $items.children().length;
		sortable( $items, '> .crg-item' );
		$wrap.find( '.crg-item-add' ).on( 'click', function () {
			var html = $wrap.find( '.crg-item-template' ).html().split( '__i__' ).join( 'new' + Date.now() + ( n++ ) );
			$items.find( '.crg-item[open]' ).prop( 'open', false );
			$items.append( html );
			$items.find( '.crg-item' ).last().find( '.crg-item-label' ).trigger( 'focus' );
		} );
		$items.on( 'click', '.crg-item-remove', function ( e ) {
			e.preventDefault();
			var $item = $( this ).closest( '.crg-item' ), label = $.trim( $item.find( '.crg-item-label' ).val() );
			if ( ! label || window.confirm( 'Delete "' + label + '"? Reports that already use it keep it.' ) ) {
				$item.remove();
			}
		} );
		$items.on( 'click', '.crg-switch', function ( e ) {
			e.stopPropagation(); // don't open/close the card
		} );
		$items.on( 'change', '.crg-switch input', function () {
			$( this ).closest( '.crg-item' ).toggleClass( 'is-off', ! this.checked );
		} );
		$items.on( 'input', '.crg-item-label', function () {
			$( this ).closest( '.crg-item' ).find( '.crg-item-title' ).text( $.trim( this.value ) || 'New item' );
		} );
		$items.on( 'change', 'input[type=radio][name$="[tone]"]', function () {
			var $item = $( this ).closest( '.crg-item' ), $tag = $item.find( 'summary .crg-tag' );
			if ( 'recommended' === this.value && ! $tag.length ) {
				$item.find( '.crg-item-title' ).after( '<span class="crg-tag">Recommended</span>' );
			} else if ( 'recommended' !== this.value ) {
				$tag.remove();
			}
		} );
		$wrap.find( 'form' ).on( 'submit', function () {
			var $empty = $items.find( '.crg-item-label' ).filter( function () { return ! $.trim( this.value ); } );
			if ( $empty.length ) {
				$empty.first().closest( '.crg-item' ).prop( 'open', true );
				$empty.first().trigger( 'focus' );
				window.alert( 'Give every item a name, or delete the ones you don\'t need.' );
				return false;
			}
		} );
		$wrap.on( 'click', '.crg-reset', function ( e ) {
			if ( ! window.confirm( $( this ).data( 'confirm' ) ) ) {
				e.preventDefault();
			}
		} );
	}

	$( function () {
		initReport();
		initSettings();
	} );
}( jQuery ) );
