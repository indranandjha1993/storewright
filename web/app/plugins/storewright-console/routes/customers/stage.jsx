import { Button, Notice, SearchControl, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { adminLink, consoleLink } from '../../lib/data';
import { count, money, shortDate, timeAgo } from '../../lib/format';
import { Empty, Screen } from '../../lib/screen';
import { addressLines, initials, personName } from '../../lib/wc';
import { loadCustomerOrders, loadCustomers, PER_PAGE } from './api';
import './style.scss';

function Detail( { customer, onClose } ) {
	const [ orders, setOrders ] = useState( null );
	useEffect( () => {
		let current = true;
		setOrders( null );
		loadCustomerOrders( customer.id ).then( ( loaded ) => current && setOrders( loaded ) );
		return () => {
			current = false;
		};
	}, [ customer.id ] );
	const name = personName( customer, customer.username );
	const spent = orders ? orders.items.reduce( ( sum, order ) => sum + ( [ 'completed', 'processing' ].includes( order.status ) ? Number( order.total ) : 0 ), 0 ) : 0;
	return (
		<aside className="sw-panel sw-customer" aria-label={ name }>
			<header className="sw-customer__head">
				<span className="sw-avatar is-large" aria-hidden="true">{ initials( name ) }</span>
				<div>
					<h2 className="sw-customer__title">{ name }</h2>
					<p className="sw-customer__muted">
						{ /* translators: %s: date */ sprintf( __( 'Customer since %s', 'storewright' ), shortDate( customer.date_created_gmt + 'Z' ) ) }
					</p>
				</div>
				<Button variant="tertiary" size="compact" onClick={ onClose }>
					{ __( 'Close', 'storewright' ) }
				</Button>
			</header>
			<div className="sw-customer__stats">
				<div className="sw-stat">
					<span className="sw-stat__label">{ __( 'Orders', 'storewright' ) }</span>
					<strong className="sw-stat__value">{ orders ? count( orders.total ) : '–' }</strong>
				</div>
				<div className="sw-stat">
					<span className="sw-stat__label">{ __( 'Spent, last 10', 'storewright' ) }</span>
					<strong className="sw-stat__value">{ orders ? money( spent ) : '–' }</strong>
				</div>
			</div>
			<section className="sw-panel__block" aria-label={ __( 'Contact', 'storewright' ) }>
				<h3 className="sw-panel__block-title">{ __( 'Contact', 'storewright' ) }</h3>
				<p><a className="sw-link" href={ `mailto:${ customer.email }` }>{ customer.email }</a></p>
				{ customer.billing?.phone && <p><a className="sw-link" href={ `tel:${ customer.billing.phone }` }>{ customer.billing.phone }</a></p> }
			</section>
			<section className="sw-panel__block" aria-label={ __( 'Delivery address', 'storewright' ) }>
				<h3 className="sw-panel__block-title">{ __( 'Delivery address', 'storewright' ) }</h3>
				{ addressLines( customer.shipping?.address_1 ? customer.shipping : customer.billing ).map( ( line ) => (
					<p key={ line }>{ line }</p>
				) ) }
				{ ! customer.shipping?.address_1 && ! customer.billing?.address_1 && <p className="sw-customer__muted">{ __( 'No address saved yet.', 'storewright' ) }</p> }
			</section>
			<section className="sw-panel__block" aria-label={ __( 'Recent orders', 'storewright' ) }>
				<h3 className="sw-panel__block-title">{ __( 'Recent orders', 'storewright' ) }</h3>
				{ orders === null && <Spinner /> }
				{ orders && orders.items.length === 0 && <p className="sw-customer__muted">{ __( 'No orders yet.', 'storewright' ) }</p> }
				<ul className="sw-customer__orders">
					{ ( orders?.items ?? [] ).map( ( order ) => (
						<li key={ order.id }>
							<a className="sw-link" href={ consoleLink( '/orders', `order=${ order.id }` ) }>#{ order.number }</a>
							<span>{ timeAgo( order.date_created_gmt + 'Z' ) }</span>
							<span className="is-number">{ money( order.total, 2 ) }</span>
						</li>
					) ) }
				</ul>
			</section>
			<a className="sw-link" href={ adminLink( `user-edit.php?user_id=${ customer.id }` ) }>
				{ __( 'Edit account in advanced', 'storewright' ) }
			</a>
		</aside>
	);
}

export const stage = () => {
	const [ search, setSearch ] = useState( '' );
	const [ query, setQuery ] = useState( '' );
	const [ pageNumber, setPageNumber ] = useState( 1 );
	const [ result, setResult ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ selected, setSelected ] = useState( null );

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
		loadCustomers( { search: query, pageNumber } )
			.then( ( loaded ) => current && setResult( loaded ) )
			.catch( ( failure ) => current && setError( failure.message ) );
		return () => {
			current = false;
		};
	}, [ query, pageNumber ] );

	const from = result ? ( pageNumber - 1 ) * PER_PAGE + 1 : 0;
	const to = result ? Math.min( pageNumber * PER_PAGE, result.total ) : 0;

	return (
		<Screen
			title={ __( 'Customers', 'storewright' ) }
			className="sw-customers"
			split="detail"
			aside={ selected && <Detail customer={ selected } onClose={ () => setSelected( null ) } /> }
			actions={ <SearchControl __next40pxDefaultSize label={ __( 'Search customers', 'storewright' ) } placeholder={ __( 'Name, email or phone', 'storewright' ) } value={ search } onChange={ setSearch } /> }
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<div className="sw-list">
					{ ! result && ! error && <Spinner /> }
					{ result && result.items.length === 0 && <Empty>{ __( 'No customers match.', 'storewright' ) }</Empty> }
					{ result && result.items.length > 0 && (
						<>
							<table className="sw-table sw-customers__table">
								<thead>
									<tr>
										<th scope="col">{ __( 'Customer', 'storewright' ) }</th>
										<th scope="col">{ __( 'Where', 'storewright' ) }</th>
										<th scope="col">{ __( 'Since', 'storewright' ) }</th>
										<th scope="col">{ __( 'Buying', 'storewright' ) }</th>
									</tr>
								</thead>
								<tbody>
									{ result.items.map( ( customer ) => {
										const name = personName( customer, customer.username );
										return (
											<tr key={ customer.id } className={ selected?.id === customer.id ? 'is-selected' : '' }>
												<td>
													<button type="button" className="sw-customers__open" onClick={ () => setSelected( customer ) }>
														<span className="sw-avatar" aria-hidden="true">{ initials( name ) }</span>
														<span>
															{ name }
															<small>{ customer.email }</small>
														</span>
													</button>
												</td>
												<td>{ [ customer.shipping?.city || customer.billing?.city, customer.shipping?.state || customer.billing?.state ].filter( Boolean ).join( ', ' ) }</td>
												<td>{ shortDate( customer.date_created_gmt + 'Z' ) }</td>
												<td>
													<span className={ `sw-pill ${ customer.is_paying_customer ? 'is-good' : 'is-quiet' }` }>{ customer.is_paying_customer ? __( 'Has ordered', 'storewright' ) : __( 'Not yet', 'storewright' ) }</span>
												</td>
											</tr>
										);
									} ) }
								</tbody>
							</table>
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
