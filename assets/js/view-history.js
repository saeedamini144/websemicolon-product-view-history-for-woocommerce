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

	/**
	 * Previous / next buttons for the carousel layout. The track scrolls natively
	 * (touch, trackpad, keyboard); the buttons only move it by a page.
	 */
	function initCarousel( root ) {
		var track = root.querySelector( '.pvh-product-grid' );
		var prev = root.querySelector( '.pvh-carousel-prev' );
		var next = root.querySelector( '.pvh-carousel-next' );
		if ( ! track || ! prev || ! next ) {
			return;
		}

		var isRtl = 'rtl' === window.getComputedStyle( track ).direction;

		function update() {
			var position = Math.abs( track.scrollLeft );
			var max = track.scrollWidth - track.clientWidth - 1;
			prev.hidden = position <= 1;
			next.hidden = position >= max;
		}

		function go( direction ) {
			track.scrollBy( {
				left: direction * ( isRtl ? -1 : 1 ) * track.clientWidth * 0.9,
				behavior: 'smooth'
			} );
		}

		prev.addEventListener( 'click', function () {
			go( -1 );
		} );
		next.addEventListener( 'click', function () {
			go( 1 );
		} );
		track.addEventListener( 'scroll', update, { passive: true } );
		window.addEventListener( 'resize', update );
		update();
	}

	function renderPlaceholders() {
		document.querySelectorAll( '.pvh-async' ).forEach( function ( el ) {
			post( 'product_view_history_render', {
				limit: el.getAttribute( 'data-limit' ),
				title: el.getAttribute( 'data-title' ),
				show_price: el.getAttribute( 'data-show-price' ),
				badges: el.getAttribute( 'data-badges' ),
				layout: el.getAttribute( 'data-layout' ),
				add_to_cart: el.getAttribute( 'data-add-to-cart' ),
				product_id: config.productId || 0,
				page_url: window.location.href
			} ).then( function ( result ) {
				if ( result && result.success && result.data && result.data.html ) {
					var holder = document.createElement( 'div' );
					holder.innerHTML = result.data.html;
					var node = holder.firstElementChild;
					el.replaceWith( node );
					initCarousel( node );
				} else {
					el.remove();
				}
			} ).catch( function () {
				el.remove();
			} );
		} );
	}

	function init() {
		// Record the current product first so the history is up to date before rendering.
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
