import { cpSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

const target = 'web/app/plugins/storewright-core/assets/vendor/leaflet';
const source = join( 'node_modules', 'leaflet', 'dist' );

mkdirSync( target, { recursive: true } );
for ( const file of [ 'leaflet.js', 'leaflet.css', 'images' ] ) {
	cpSync( join( source, file ), join( target, file ), { recursive: true } );
}
console.log( `leaflet copied to ${ target }` );
