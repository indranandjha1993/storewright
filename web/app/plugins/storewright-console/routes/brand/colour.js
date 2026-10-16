const channels = ( hex ) => {
	let h = hex.replace( '#', '' ).toLowerCase();
	if ( h.length === 3 ) {
		h = h.replace( /./g, ( c ) => c + c );
	}
	return [ 0, 2, 4 ].map( ( i ) => parseInt( h.slice( i, i + 2 ), 16 ) );
};

const hex = ( rgb ) => '#' + rgb.map( ( c ) => Math.round( c ).toString( 16 ).padStart( 2, '0' ) ).join( '' );

export const isHex = ( value ) => /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test( value );

export const mix = ( a, b, amount ) => hex( channels( a ).map( ( c, i ) => c * ( 1 - amount ) + channels( b )[ i ] * amount ) );

const luminance = ( colour ) => {
	const [ r, g, b ] = channels( colour ).map( ( c ) => {
		const v = c / 255;
		return v <= 0.03928 ? v / 12.92 : ( ( v + 0.055 ) / 1.055 ) ** 2.4;
	} );
	return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

export const contrast = ( a, b ) => {
	const [ la, lb ] = [ luminance( a ), luminance( b ) ];
	return ( Math.max( la, lb ) + 0.05 ) / ( Math.min( la, lb ) + 0.05 );
};

const readableOn = ( background ) => ( contrast( background, '#ffffff' ) >= contrast( background, '#15171a' ) ? '#ffffff' : '#15171a' );

const RADII = {
	sharp: { radius: '2px', small: '2px', button: '2px' },
	soft: { radius: '10px', small: '8px', button: '10px' },
	round: { radius: '20px', small: '12px', button: '999px' },
};

export function paletteOf( settings ) {
	const { accent, background: base, text, sale } = settings;
	const dark = luminance( base ) <= 0.5;
	return {
		base,
		surface: dark ? mix( base, '#ffffff', 0.06 ) : '#ffffff',
		contrast: text,
		muted: mix( text, base, 0.38 ),
		line: mix( text, base, 0.88 ),
		accent,
		accentHover: dark ? mix( accent, '#ffffff', 0.12 ) : mix( accent, '#000000', 0.12 ),
		accentSoft: mix( accent, base, 0.86 ),
		onAccent: readableOn( accent ),
		sale,
		onSale: readableOn( sale ),
		...( RADII[ settings.radius ] ?? RADII.soft ),
	};
}
