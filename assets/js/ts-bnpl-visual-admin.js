( function ( $, wp ) {
	'use strict';

	var root = document.querySelector( '.ts-bnpl-visual-admin' );
	var config = window.tsBnplVisualAdmin || {};

	if ( ! root ) {
		return;
	}

	function setTab( id ) {
		root.querySelectorAll( '[data-ts-bnpl-tab]' ).forEach( function ( tab ) {
			var active = tab.getAttribute( 'data-ts-bnpl-tab' ) === id;
			tab.classList.toggle( 'nav-tab-active', active );
			tab.setAttribute( 'aria-selected', active ? 'true' : 'false' );
		} );
		root.querySelectorAll( '[data-ts-bnpl-panel]' ).forEach( function ( panel ) {
			panel.hidden = panel.getAttribute( 'data-ts-bnpl-panel' ) !== id;
		} );
	}

	function renumber( list ) {
		list.querySelectorAll( ':scope > [data-ts-bnpl-row]' ).forEach( function ( row, index ) {
			var number = row.querySelector( '[data-ts-bnpl-row-number]' );
			if ( number ) {
				number.textContent = String( index + 1 );
			}
			row.querySelectorAll( '[name]' ).forEach( function ( field ) {
				field.name = field.name.replace( /\[(?:__INDEX__|\d+)\]/, '[' + index + ']' );
			} );
		} );
	}

	function addRow( list, template ) {
		if ( ! list || ! template || list.children.length >= 20 ) {
			return;
		}
		var holder = document.createElement( 'div' );
		holder.innerHTML = template.innerHTML.replaceAll( '__INDEX__', String( list.children.length ) ).trim();
		if ( holder.firstElementChild ) {
			list.appendChild( holder.firstElementChild );
			renumber( list );
		}
	}

	function listForRow( row ) {
		return row && row.parentElement && ( row.parentElement.matches( '[data-ts-bnpl-banner-list]' ) || row.parentElement.matches( '[data-ts-bnpl-provider-list]' ) )
			? row.parentElement
			: null;
	}

	root.addEventListener( 'click', function ( event ) {
		var tab = event.target.closest( '[data-ts-bnpl-tab]' );
		if ( tab ) {
			setTab( tab.getAttribute( 'data-ts-bnpl-tab' ) );
			return;
		}

		if ( event.target.closest( '[data-ts-bnpl-add-banner]' ) ) {
			addRow( root.querySelector( '[data-ts-bnpl-banner-list]' ), root.querySelector( '[data-ts-bnpl-banner-template]' ) );
			return;
		}
		if ( event.target.closest( '[data-ts-bnpl-add-provider]' ) ) {
			addRow( root.querySelector( '[data-ts-bnpl-provider-list]' ), root.querySelector( '[data-ts-bnpl-provider-template]' ) );
			return;
		}

		var row = event.target.closest( '[data-ts-bnpl-row]' );
		var list = listForRow( row );
		if ( row && list && event.target.closest( '[data-ts-bnpl-remove-row]' ) ) {
			if ( ! config.removeRow || window.confirm( config.removeRow ) ) {
				row.remove();
				renumber( list );
			}
			return;
		}
		if ( row && list && event.target.closest( '[data-ts-bnpl-move-up]' ) && row.previousElementSibling ) {
			list.insertBefore( row, row.previousElementSibling );
			renumber( list );
			return;
		}
		if ( row && list && event.target.closest( '[data-ts-bnpl-move-down]' ) && row.nextElementSibling ) {
			list.insertBefore( row.nextElementSibling, row );
			renumber( list );
			return;
		}

		var field = event.target.closest( '[data-ts-bnpl-media-field]' );
		if ( ! field ) {
			return;
		}
		if ( event.target.closest( '[data-ts-bnpl-clear-media]' ) ) {
			field.querySelector( '[data-ts-bnpl-media-id]' ).value = '0';
			field.querySelector( '[data-ts-bnpl-media-preview]' ).innerHTML = '';
			return;
		}
		if ( ! event.target.closest( '[data-ts-bnpl-select-media]' ) || ! wp || ! wp.media ) {
			return;
		}

		var kind = field.getAttribute( 'data-media-kind' ) || 'image';
		var requiredMime = {
			desktop_avif_id: 'image/avif',
			mobile_avif_id: 'image/avif',
			desktop_webp_id: 'image/webp',
			mobile_webp_id: 'image/webp'
		}[ kind ] || '';

		var frame = wp.media( {
			title: config.mediaTitle || 'Select media',
			button: { text: config.mediaButton || 'Use media' },
			library: { type: requiredMime || 'image' },
			multiple: false
		} );
		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			if ( requiredMime && attachment.mime !== requiredMime ) {
				window.alert( config.invalidMedia || 'The selected file type is not valid for this field.' );
				return;
			}
			field.querySelector( '[data-ts-bnpl-media-id]' ).value = String( attachment.id || 0 );
			var previewUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
			var preview = field.querySelector( '[data-ts-bnpl-media-preview]' );
			preview.innerHTML = '';
			if ( previewUrl ) {
				var image = document.createElement( 'img' );
				image.src = previewUrl;
				image.alt = '';
				preview.appendChild( image );
			}
		} );
		frame.open();
	} );

	[ '[data-ts-bnpl-banner-list]', '[data-ts-bnpl-provider-list]' ].forEach( function ( selector ) {
		var list = root.querySelector( selector );
		if ( list && $.fn.sortable ) {
			$( list ).sortable( {
				items: '> [data-ts-bnpl-row]',
				handle: '.ts-bnpl-visual-admin__row-head',
				update: function () { renumber( list ); }
			} );
		}
	} );
} )( window.jQuery, window.wp );
