import { Button, Notice, SearchControl, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { count, money, timeAgo } from '../../lib/format';
import { Empty, Screen } from '../../lib/screen';
import { initials, personName } from '../../lib/wc';
import { loadOrders, loadTotals, PER_PAGE } from './api';
import { shippingLabel, STATUSES, statusLabel, statusTone } from './labels';
import { OrderPanel } from './order-panel';
import './style.scss';

function Filters( { status, totals, onChange } ) {
	return (
		<nav className="sw-orders__filters" aria-label={ __( 'Order status', 'storewright' ) }>
			{ STATUSES.map( ( item ) => (
				<button key={ item.key } type="button" className={ `sw-chip ${ item.key === status ? 'is-active' : '' }` } aria-pressed={ item.key === status } onClick={ () => onChange( item.key ) }>
					{ item.label }
					{ item.key !== '' && totals && <span className="sw-orders__count">{ count( totals[ item.key ] ?? 0 ) }</span> }
				</button>
			) ) }
		</nav>
	);
}

function Rows( { orders, selected, onSelect } ) {
	return (
		<table className="sw-table sw-orders__table">
			<thead>
				<tr>
					<th scope="col">{ __( 'Order', 'storewright' ) }</th>
					<th scope="col">{ __( 'Customer', 'storewright' ) }</th>
					<th scope="col" className="is-extra">{ __( 'Items', 'storewright' ) }</th>
					<th scope="col" className="is-extra">{ __( 'Payment', 'storewright' ) }</th>
					<th scope="col">{ __( 'Status', 'storewright' ) }</th>
					<th scope="col" className="is-number">{ __( 'Total', 'storewright' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ orders.map( ( order ) => {
					const name = personName( order.billing, __( 'Guest', 'storewright' ) );
					return (
						<tr key={ order.id } className={ order.id === selected ? 'is-selected' : '' }>
							<td>
								<button type="button" className="sw-orders__open" onClick={ () => onSelect( order.id ) }>
									#{ order.number }
								</button>
								<small>{ timeAgo( order.date_created_gmt + 'Z' ) }</small>
							</td>
							<td>
								<span className="sw-orders__customer">
									<span className="sw-avatar" aria-hidden="true">{ initials( name ) }</span>
									<span>
										{ name }
										<small>{ [ order.shipping.city || order.billing.city, order.shipping.postcode || order.billing.postcode ].filter( Boolean ).join( ' ' ) }</small>
									</span>
								</span>
							</td>
							<td className="is-extra">{ order.line_items.map( ( item ) => ( item.quantity > 1 ? `${ item.quantity } × ${ item.name }` : item.name ) ).join( ', ' ) }</td>
							<td className="is-extra">
								{ order.payment_method_title || __( 'Unpaid', 'storewright' ) }
								<small>{ order.shipping_lines[ 0 ]?.method_title }</small>
							</td>
							<td>
								<span className={ `sw-pill ${ statusTone( order.status ) }` }>{ statusLabel( order.status ) }</span>
								{ shippingLabel( order ) && <small>{ shippingLabel( order ) }</small> }
							</td>
							<td className="is-number">{ money( order.total, 2 ) }</td>
						</tr>
					);
				} ) }
			</tbody>
		</table>
	);
}

export const stage = () => {
	const params = Object.fromEntries( new URLSearchParams( window.location.search ) );
	const [ status, setStatus ] = useState( STATUSES.some( ( item ) => item.key === params.status ) ? params.status : '' );
	const [ search, setSearch ] = useState( '' );
	const [ query, setQuery ] = useState( '' );
	const [ pageNumber, setPageNumber ] = useState( 1 );
	const [ result, setResult ] = useState( null );
	const [ totals, setTotals ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ selected, setSelected ] = useState( Number( params.order ) || null );
	const [ version, setVersion ] = useState( 0 );

	useEffect( () => {
		const timer = setTimeout( () => {
			setQuery( search.trim() );
			setPageNumber( 1 );
		}, 300 );
		return () => clearTimeout( timer );
	}, [ search ] );

	useEffect( () => {
		let current = true;
		setError( null );
		loadOrders( { status, search: query, pageNumber } )
			.then( ( loaded ) => current && setResult( loaded ) )
			.catch( ( failure ) => current && setError( failure.message ) );
		loadTotals().then( ( loaded ) => current && setTotals( loaded ) );
		return () => {
			current = false;
		};
	}, [ status, query, pageNumber, version ] );

	const changed = ( updated ) => {
		setResult( ( current ) => current && { ...current, items: current.items.map( ( order ) => ( order.id === updated.id ? updated : order ) ) } );
		setVersion( ( v ) => v + 1 );
	};

	const from = result ? ( pageNumber - 1 ) * PER_PAGE + 1 : 0;
	const to = result ? Math.min( pageNumber * PER_PAGE, result.total ) : 0;

	return (
		<Screen
			title={ __( 'Orders', 'storewright' ) }
			className="sw-orders"
			split="detail"
			aside={ selected && <OrderPanel id={ selected } onClose={ () => setSelected( null ) } onChanged={ changed } /> }
			actions={ <SearchControl label={ __( 'Search orders', 'storewright' ) } placeholder={ __( 'Order number, name, email or phone', 'storewright' ) } value={ search } onChange={ setSearch } size="compact" /> }
		>
			<Filters
				status={ status }
				totals={ totals }
				onChange={ ( next ) => {
					setStatus( next );
					setPageNumber( 1 );
				} }
			/>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<div className="sw-list">
					{ ! result && ! error && <Spinner /> }
					{ result && result.items.length === 0 && <Empty>{ query ? __( 'No orders match that search.', 'storewright' ) : __( 'No orders here yet.', 'storewright' ) }</Empty> }
					{ result && result.items.length > 0 && (
						<>
							<Rows orders={ result.items } selected={ selected } onSelect={ setSelected } />
							<footer className="sw-pager">
								<span>{ /* translators: 1: first row, 2: last row, 3: total */ sprintf( __( '%1$s to %2$s of %3$s', 'storewright' ), from, to, count( result.total ) ) }</span>
								<Button variant="tertiary" size="compact" disabled={ pageNumber <= 1 } accessibleWhenDisabled onClick={ () => setPageNumber( pageNumber - 1 ) }>
									{ __( 'Previous', 'storewright' ) }
								</Button>
								<Button variant="tertiary" size="compact" disabled={ pageNumber >= result.pages } accessibleWhenDisabled onClick={ () => setPageNumber( pageNumber + 1 ) }>
									{ __( 'Next', 'storewright' ) }
								</Button>
							</footer>
						</>
					) }
			</div>
		</Screen>
	);
};
