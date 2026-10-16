import { copyFileSync, mkdirSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

const families = [ 'inter', 'manrope', 'space-grotesk', 'fraunces', 'playfair-display', 'source-serif-4' ];
const target = 'web/app/themes/storewright/assets/fonts';

mkdirSync( target, { recursive: true } );
for ( const family of families ) {
	const source = join( 'node_modules', '@fontsource-variable', family );
	for ( const subset of [ 'latin', 'latin-ext' ] ) {
		const file = `${ family }-${ subset }-wght-normal.woff2`;
		copyFileSync( join( source, 'files', file ), join( target, file ) );
	}
	const licence = readdirSync( source ).find( ( name ) => /^licen[cs]e/i.test( name ) );
	if ( licence ) {
		copyFileSync( join( source, licence ), join( target, `${ family }-LICENSE.txt` ) );
	}
}
console.log( `${ families.length } font families copied to ${ target }` );
