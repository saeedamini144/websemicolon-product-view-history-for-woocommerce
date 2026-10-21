( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var RangeControl = wp.components.RangeControl;
	var TextControl = wp.components.TextControl;
	var SelectControl = wp.components.SelectControl;

	var choices = [
		{ label: __( 'Use plugin setting', 'websemicolon-product-view-history-for-woocommerce' ), value: 'default' },
		{ label: __( 'Yes', 'websemicolon-product-view-history-for-woocommerce' ), value: 'yes' },
		{ label: __( 'No', 'websemicolon-product-view-history-for-woocommerce' ), value: 'no' }
	];

	var layoutChoices = [
		{ label: __( 'Use plugin setting', 'websemicolon-product-view-history-for-woocommerce' ), value: 'default' },
		{ label: __( 'Grid', 'websemicolon-product-view-history-for-woocommerce' ), value: 'grid' },
		{ label: __( 'Carousel (slider)', 'websemicolon-product-view-history-for-woocommerce' ), value: 'carousel' },
		{ label: __( 'List', 'websemicolon-product-view-history-for-woocommerce' ), value: 'list' }
	];

	wp.blocks.registerBlockType( 'websemicolon/product-view-history', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps( {
				style: {
					padding: '16px',
					border: '1px dashed #9ca3af',
					borderRadius: '8px',
					background: '#f9fafb'
				}
			} );

			var skeleton = [];
			for ( var i = 0; i < 4; i++ ) {
				skeleton.push( el( 'div', {
					key: i,
					style: { flex: '1', height: '72px', borderRadius: '6px', background: '#e5e7eb' }
				} ) );
			}

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Display settings', 'websemicolon-product-view-history-for-woocommerce' ) },
						el( RangeControl, {
							label: __( 'Number of products', 'websemicolon-product-view-history-for-woocommerce' ),
							help: __( '0 uses the plugin setting.', 'websemicolon-product-view-history-for-woocommerce' ),
							value: attributes.limit,
							min: 0,
							max: 10,
							onChange: function ( value ) {
								setAttributes( { limit: value || 0 } );
							}
						} ),
						el( TextControl, {
							label: __( 'Section title', 'websemicolon-product-view-history-for-woocommerce' ),
							help: __( 'Leave empty to use the plugin setting.', 'websemicolon-product-view-history-for-woocommerce' ),
							value: attributes.title,
							onChange: function ( value ) {
								setAttributes( { title: value } );
							}
						} ),
						el( SelectControl, {
							label: __( 'Layout', 'websemicolon-product-view-history-for-woocommerce' ),
							value: attributes.layout,
							options: layoutChoices,
							onChange: function ( value ) {
								setAttributes( { layout: value } );
							}
						} ),
						el( SelectControl, {
							label: __( 'Show "Add to cart" button', 'websemicolon-product-view-history-for-woocommerce' ),
							value: attributes.addToCart,
							options: choices,
							onChange: function ( value ) {
								setAttributes( { addToCart: value } );
							}
						} ),
						el( SelectControl, {
							label: __( 'Show product price', 'websemicolon-product-view-history-for-woocommerce' ),
							value: attributes.showPrice,
							options: choices,
							onChange: function ( value ) {
								setAttributes( { showPrice: value } );
							}
						} ),
						el( SelectControl, {
							label: __( 'Show change badges', 'websemicolon-product-view-history-for-woocommerce' ),
							value: attributes.badges,
							options: choices,
							onChange: function ( value ) {
								setAttributes( { badges: value } );
							}
						} )
					)
				),
				el( 'strong', { style: { display: 'block', marginBottom: '4px' } }, attributes.title || __( 'Recently Viewed Products', 'websemicolon-product-view-history-for-woocommerce' ) ),
				el( 'p', { style: { margin: '0 0 12px', color: '#4b5563' } }, __( 'Each visitor will see the products they recently viewed here.', 'websemicolon-product-view-history-for-woocommerce' ) ),
				el( 'div', { style: { display: 'flex', gap: '8px' } }, skeleton )
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp ) );
