import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { adminLink, consoleData } from './data';
import './style.scss';

const queues = [
	{ status: 'processing', label: __( 'To fulfil', 'storewright' ) },
	{ status: 'pending', label: __( 'Awaiting payment', 'storewright' ) },
	{ status: 'on-hold', label: __( 'On hold', 'storewright' ) },
];

function useOrderTotals() {
	const [ totals, setTotals ] = useState( null );
	const [ failed, setFailed ] = useState( false );
	useEffect( () => {
		apiFetch( { path: '/wc/v3/reports/orders/totals' } )
			.then( ( rows ) => setTotals( Object.fromEntries( rows.map( ( row ) => [ row.slug, row.total ] ) ) ) )
			.catch( () => setFailed( true ) );
	}, [] );
	return { totals, failed };
}

export const stage = () => {
	const { totals, failed } = useOrderTotals();
	return (
		<div className="sw-today">
			<p className="sw-today__store">{ consoleData.storeName }</p>
			<h1 className="sw-today__title">{ __( 'Today', 'storewright' ) }</h1>
			{ failed && <p role="alert">{ __( 'Order counts could not be loaded. Reload the page to try again.', 'storewright' ) }</p> }
			<ul className="sw-today__queues">
				{ queues.map( ( queue ) => (
					<li key={ queue.status }>
						<a className="sw-today__queue" href={ adminLink( `admin.php?page=wc-orders&status=wc-${ queue.status }` ) }>
							<span className="sw-today__count">{ totals ? totals[ queue.status ] ?? 0 : '–' }</span>
							<span className="sw-today__label">{ queue.label }</span>
						</a>
					</li>
				) ) }
			</ul>
		</div>
	);
};
