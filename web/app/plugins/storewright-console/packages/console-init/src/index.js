import { store as bootStore } from '@wordpress/boot';
import { dispatch } from '@wordpress/data';
import { brush, home, mapMarker } from '@wordpress/icons';

const icons = { today: home, areas: mapMarker, brand: brush };

export async function init() {
	for ( const [ id, icon ] of Object.entries( icons ) ) {
		dispatch( bootStore ).updateMenuItem( id, { icon } );
	}
}
