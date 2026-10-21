( function ( $ ) {
	'use strict';

	var config = window.pvhAdmin || {};

	$( function () {
		// Report page: confirm before resetting the statistics.
		$( '.pvh-reset-form' ).on( 'submit', function ( event ) {
			if ( ! window.confirm( config.confirmReset ) ) {
				event.preventDefault();
			}
		} );

		var $preview = $( '#pvh-preview' );
		if ( ! $preview.length ) {
			return;
		}

		var layouts = [ 'grid', 'carousel', 'list' ];

		// Appearance fields drive CSS custom properties on the preview.
		function applyVar( $input ) {
			var name = $input.attr( 'data-pvh-var' );
			if ( ! name ) {
				return;
			}

			var value = $input.val();
			if ( 'font' === name ) {
				value = config.fonts && config.fonts[ value ] ? config.fonts[ value ] : 'inherit';
			} else if ( ! value ) {
				// Cleared color: fall back to the built-in one.
				value = $input.attr( 'data-default-color' ) || '';
			} else if ( $input.attr( 'data-pvh-unit' ) ) {
				value += $input.attr( 'data-pvh-unit' );
			}

			if ( value ) {
				$preview[ 0 ].style.setProperty( '--pvh-' + name, value );
			} else {
				$preview[ 0 ].style.removeProperty( '--pvh-' + name );
			}
		}

		// Main options toggle classes / text on the preview.
		function applyOption( $input ) {
			var value = $input.val();

			switch ( $input.attr( 'data-pvh-preview' ) ) {
				case 'title':
					$( '#pvh-preview-title' ).text( value || config.defaultTitle );
					break;
				case 'layout':
					$preview.removeClass( layouts.map( function ( name ) {
						return 'pvh-layout-' + name;
					} ).join( ' ' ) ).addClass( 'pvh-layout-' + value );
					break;
				case 'show_price':
					$preview.toggleClass( 'pvh-hide-price', 'yes' !== value );
					break;
				case 'badges':
					$preview.toggleClass( 'pvh-hide-badges', 'yes' !== value );
					break;
				case 'add_to_cart':
					$preview.toggleClass( 'pvh-hide-cart', 'yes' !== value );
					break;
			}
		}

		var $vars = $( '[data-pvh-var]' );
		var $options = $( '[data-pvh-preview]' );

		if ( $.fn.wpColorPicker ) {
			$( '.pvh-color-field' ).wpColorPicker( {
				change: function ( event, ui ) {
					var $input = $( event.target );
					$input.val( ui.color.toString() );
					applyVar( $input );
				},
				clear: function () {
					var $input = $( this ).closest( '.wp-picker-container' ).find( '.pvh-color-field' );
					window.setTimeout( function () {
						applyVar( $input );
					}, 0 );
				}
			} );
		}

		$vars.on( 'input change', function () {
			applyVar( $( this ) );
		} );
		$options.on( 'input change', function () {
			applyOption( $( this ) );
		} );

		$vars.each( function () {
			applyVar( $( this ) );
		} );
		$options.each( function () {
			applyOption( $( this ) );
		} );
	} );
}( window.jQuery ) );
