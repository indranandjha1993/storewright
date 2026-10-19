import { store as bootStore } from '@wordpress/boot';
import { dispatch } from '@wordpress/data';
import { createElement, createRoot } from '@wordpress/element';
import { brush, cog, home, mapMarker, pages, payment, people, receipt, tag, undo } from '@wordpress/icons';
import { Shell } from './shell';
import { adminLink } from '../../../lib/data';

const icons = { today: home, orders: receipt, returns: undo, products: tag, customers: people, payments: payment, areas: mapMarker, content: pages, brand: brush, settings: cog };

export async function init() {
	for ( const [ id, icon ] of Object.entries( icons ) ) {
		dispatch( bootStore ).updateMenuItem( id, { icon } );
	}
	dispatch( bootStore ).setDashboardLink( adminLink( 'edit.php?post_type=product' ) );
	createRoot( document.createElement( 'div' ) ).render( createElement( Shell ) );
}
