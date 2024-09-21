import lazySizes from 'lazysizes';
( () => {
	'use strict';
	// Lazyload for background images.
	const lazyloadBackground = ( element ) => {
		let bgValue = element.getAttribute( 'data-bg-image' );
		let property = 'background-image';
		if ( ! bgValue ) {
			bgValue = element.getAttribute( 'data-bg' );
			property = 'background';
		}

		if ( bgValue ) {
			const importantRegex = /\s*\!\s*important/i;
			const value = bgValue.replace( importantRegex, '' );
			const priority = value !== bgValue ? 'important' : '';
			element.style.setProperty( property, value, priority );
		}
	};

	document.addEventListener( 'lazybeforeunveil', function( e ) {
		// Lazy background image.
		lazyloadBackground( e.target );
	} );

	lazySizes.init();
} )();
