( function () {
	var form = document.querySelector( '.sw-brand__form' );
	var preview = document.querySelector( '[data-preview]' );
	if ( ! form || ! preview ) {
		return;
	}
	var RADII = {
		sharp: [ '2px', '2px', '2px' ],
		soft: [ '10px', '8px', '10px' ],
		round: [ '20px', '12px', '999px' ],
	};

	function channels( hex ) {
		var h = hex.replace( '#', '' ).toLowerCase();
		if ( h.length === 3 ) {
			h = h.replace( /./g, function ( c ) {
				return c + c;
			} );
		}
		return [ 0, 2, 4 ].map( function ( i ) {
			return parseInt( h.slice( i, i + 2 ), 16 );
		} );
	}

	function hex( rgb ) {
		return '#' + rgb.map( function ( c ) {
			return Math.round( c ).toString( 16 ).padStart( 2, '0' );
		} ).join( '' );
	}

	function isHex( value ) {
		return /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test( value );
	}

	function mix( a, b, amount ) {
		var cb = channels( b );
		return hex( channels( a ).map( function ( c, i ) {
			return c * ( 1 - amount ) + cb[ i ] * amount;
		} ) );
	}

	function luminance( colour ) {
		var parts = channels( colour ).map( function ( c ) {
			var v = c / 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
		} );
		return 0.2126 * parts[ 0 ] + 0.7152 * parts[ 1 ] + 0.0722 * parts[ 2 ];
	}

	function contrast( a, b ) {
		var la = luminance( a );
		var lb = luminance( b );
		return ( Math.max( la, lb ) + 0.05 ) / ( Math.min( la, lb ) + 0.05 );
	}

	function readableOn( background ) {
		return contrast( background, '#ffffff' ) >= contrast( background, '#15171a' ) ? '#ffffff' : '#15171a';
	}

	function colour( name ) {
		var value = form.querySelector( '[data-colour="' + name + '"]' ).value.trim();
		return isHex( value ) ? value : null;
	}

	function family( name ) {
		var select = form.querySelector( '[data-font="' + name + '"]' );
		return select.options[ select.selectedIndex ].getAttribute( 'data-family' );
	}

	function paint() {
		var accent = colour( 'accent' );
		var base = colour( 'background' );
		var text = colour( 'text' );
		var sale = colour( 'sale' );
		if ( ! accent || ! base || ! text || ! sale ) {
			return;
		}
		var dark = luminance( base ) <= 0.5;
		var radius = form.querySelector( '[data-radius]:checked' );
		var radii = RADII[ radius ? radius.value : 'soft' ];
		var vars = {
			base: base,
			surface: dark ? mix( base, '#ffffff', 0.06 ) : '#ffffff',
			contrast: text,
			muted: mix( text, base, 0.38 ),
			line: mix( text, base, 0.88 ),
			accent: accent,
			'accent-hover': dark ? mix( accent, '#ffffff', 0.12 ) : mix( accent, '#000000', 0.12 ),
			'accent-soft': mix( accent, base, 0.86 ),
			'on-accent': readableOn( accent ),
			sale: sale,
			'on-sale': readableOn( sale ),
			radius: radii[ 0 ],
			'radius-small': radii[ 1 ],
			'radius-button': radii[ 2 ],
			'font-body': family( 'bodyFont' ),
			'font-heading': family( 'headingFont' ),
		};
		Object.keys( vars ).forEach( function ( key ) {
			preview.style.setProperty( '--p-' + key, vars[ key ] );
		} );
	}

	form.querySelectorAll( '[data-colour]' ).forEach( function ( input ) {
		var swatch = form.querySelector( '[data-swatch="' + input.getAttribute( 'data-colour' ) + '"]' );
		input.addEventListener( 'input', function () {
			var value = input.value.trim();
			if ( isHex( value ) && value.length === 7 ) {
				swatch.value = value.toLowerCase();
			}
			paint();
		} );
		swatch.addEventListener( 'input', function () {
			input.value = swatch.value;
			paint();
		} );
	} );
	form.querySelectorAll( '[data-font]' ).forEach( function ( select ) {
		select.addEventListener( 'change', function () {
			form.querySelector( '[data-specimen="' + select.getAttribute( 'data-font' ) + '"]' ).style.fontFamily = family( select.getAttribute( 'data-font' ) );
			paint();
		} );
	} );
	form.querySelectorAll( '[data-radius]' ).forEach( function ( radio ) {
		radio.addEventListener( 'change', paint );
	} );
	var name = form.querySelector( '[data-preview-name]' );
	var title = preview.querySelector( '[data-preview-title]' );
	if ( name && title ) {
		name.addEventListener( 'input', function () {
			title.textContent = name.value || title.getAttribute( 'data-empty' ) || name.value;
		} );
	}
} )();
