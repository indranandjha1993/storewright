import { store as bootStore } from '@wordpress/boot';
import { dispatch } from '@wordpress/data';
import { brush, cog, home, mapMarker, pages, payment, people, receipt, tag } from '@wordpress/icons';

const icons = { today: home, orders: receipt, products: tag, customers: people, payments: payment, areas: mapMarker, content: pages, brand: brush, settings: cog };

export async function init() {
	for ( const [ id, icon ] of Object.entries( icons ) ) {
		dispatch( bootStore ).updateMenuItem( id, { icon } );
	}
}
