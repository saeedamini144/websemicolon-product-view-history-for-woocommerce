( function () {
	'use strict';

	var config = window.pvhData;
	if ( ! config ) {
		return;
	}

	function post( action, data ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		Object.keys( data ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );

		return fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	function renderPlaceholders() {
		document.querySelectorAll( '.pvh-async' ).forEach( function ( el ) {
			post( 'product_view_history_render', {
				limit: el.getAttribute( 'data-limit' ),
				title: el.getAttribute( 'data-title' ),
				show_price: el.getAttribute( 'data-show-price' ),
				product_id: config.productId || 0
			} ).then( function ( result ) {
				if ( result && result.success && result.data && result.data.html ) {
					el.outerHTML = result.data.html;
				} else {
					el.remove();
				}
			} ).catch( function () {
				el.remove();
			} );
		} );
	}

	function init() {
		// Record the current product first so the cookie is up to date before rendering.
		var tracked = config.productId ?
			post( 'product_view_history_track', { product_id: config.productId } ).catch( function () {} ) :
			Promise.resolve();

		tracked.then( renderPlaceholders );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
