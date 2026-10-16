import { Button, Notice, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { adminLink } from '../../lib/data';
import { Card, Empty, Screen } from '../../lib/screen';
import { count, localDate, money, shortDate, timeAgo } from '../../lib/format';
import { loadDashboard } from './api';
import './style.scss';

const periods = [
	{ key: 'today', label: __( 'Today', 'storewright' ) },
	{ key: 'week', label: __( '7 days', 'storewright' ) },
	{ key: 'month', label: __( '30 days', 'storewright' ) },
];

const queues = [
	{ status: 'processing', label: __( 'To fulfil', 'storewright' ) },
	{ status: 'pending', label: __( 'Awaiting payment', 'storewright' ) },
	{ status: 'on-hold', label: __( 'On hold', 'storewright' ) },
];

const orderStatus = {
	processing: __( 'To fulfil', 'storewright' ),
	pending: __( 'Awaiting payment', 'storewright' ),
	'on-hold': __( 'On hold', 'storewright' ),
	completed: __( 'Completed', 'storewright' ),
	cancelled: __( 'Cancelled', 'storewright' ),
	refunded: __( 'Refunded', 'storewright' ),
	failed: __( 'Failed', 'storewright' ),
};

const gatewayName = { razorpay: 'Razorpay', stripe: 'Stripe' };

const modeLabel = {
	sandbox: __( 'Test mode', 'storewright' ),
	live: __( 'Live', 'storewright' ),
	off: __( 'Not set up', 'storewright' ),
	refused: __( 'Live keys refused outside production', 'storewright' ),
};

function Change( { now, before } ) {
	if ( ! before ) {
		return <span className="sw-stat__note">{ __( 'No earlier period to compare', 'storewright' ) }</span>;
	}
	const delta = Math.round( ( ( now - before ) / before ) * 100 );
	const tone = delta > 0 ? 'is-up' : delta < 0 ? 'is-down' : '';
	/* translators: %s: signed percentage */
	return <span className={ `sw-stat__note ${ tone }` }>{ sprintf( __( '%s%% on the period before', 'storewright' ), delta > 0 ? `+${ delta }` : String( delta ) ) }</span>;
}

function Kpi( { label, value, now, before } ) {
	return (
		<div className="sw-stat">
			<span className="sw-stat__label">{ label }</span>
			<strong className="sw-stat__value">{ value }</strong>
			<Change now={ now } before={ before } />
		</div>
	);
}

function Chart( { range, intervals } ) {
	const sales = Object.fromEntries( intervals.map( ( bar ) => [ bar.interval, bar.subtotals.total_sales ] ) );
	const bars = [];
	const day = new Date( range.after );
	for ( let i = 0; i < range.days; i++ ) {
		const key = localDate( day );
		bars.push( { key, date: new Date( day ), total: sales[ key ] ?? 0 } );
		day.setDate( day.getDate() + 1 );
	}
	const peak = Math.max( 1, ...bars.map( ( bar ) => bar.total ) );
	return (
		<div className="sw-dash__chart" role="img" aria-label={ __( 'Sales by day', 'storewright' ) }>
			{ bars.map( ( bar ) => (
				<div className="sw-dash__bar" key={ bar.key } title={ `${ shortDate( bar.date ) }: ${ money( bar.total ) }` }>
					<span className="sw-dash__bar-fill" style={ { height: `${ Math.max( 2, ( bar.total / peak ) * 100 ) }%` } } />
					{ bars.length <= 7 && <span className="sw-dash__bar-label">{ shortDate( bar.date ) }</span> }
				</div>
			) ) }
		</div>
	);
}

const orderLink = ( id ) => adminLink( `admin.php?page=wc-orders&action=edit&id=${ id }` );

function Orders( { orders } ) {
	if ( orders.length === 0 ) {
		return <Empty>{ __( 'No orders yet. The first one shows up here.', 'storewright' ) }</Empty>;
	}
	return (
		<table className="sw-table">
			<thead>
				<tr>
					<th scope="col">{ __( 'Order', 'storewright' ) }</th>
					<th scope="col">{ __( 'Customer', 'storewright' ) }</th>
					<th scope="col">{ __( 'Items', 'storewright' ) }</th>
					<th scope="col">{ __( 'Status', 'storewright' ) }</th>
					<th scope="col" className="is-number">{ __( 'Total', 'storewright' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ orders.map( ( order ) => {
					const customer = order.extended_info?.customer ?? {};
					const name = [ customer.first_name, customer.last_name ].filter( Boolean ).join( ' ' ) || customer.email || __( 'Guest', 'storewright' );
					const initials = name.split( /[\s@]+/ ).slice( 0, 2 ).map( ( part ) => part[ 0 ] ?? '' ).join( '' ).toUpperCase();
					return (
						<tr key={ order.order_id }>
							<td>
								<a className="sw-link" href={ orderLink( order.order_id ) }>#{ order.order_id }</a>
								<small>{ timeAgo( order.date_created_gmt + 'Z' ) }</small>
							</td>
							<td>
								<span className="sw-dash__customer">
									<span className="sw-avatar" aria-hidden="true">{ initials }</span>
									{ name }
								</span>
							</td>
							<td>{ ( order.extended_info?.products ?? [] ).map( ( p ) => ( Number( p.quantity ) > 1 ? `${ p.quantity } × ${ p.name }` : p.name ) ).join( ', ' ) }</td>
							<td>
								<span className={ `sw-pill ${ [ 'pending', 'on-hold' ].includes( order.status ) ? 'is-quiet' : [ 'cancelled', 'failed', 'refunded' ].includes( order.status ) ? 'is-bad' : 'is-good' }` }>{ orderStatus[ order.status ] ?? order.status }</span>
							</td>
							<td className="is-number">{ money( order.total_sales, 2 ) }</td>
						</tr>
					);
				} ) }
			</tbody>
		</table>
	);
}

function Payments( { payments } ) {
	return (
		<ul className="sw-dash__list">
			{ payments.gateways.map( ( gateway ) => {
				const trouble = gateway.failed > 0 || gateway.stuck > 0 || gateway.mode === 'refused';
				return (
					<li key={ gateway.gateway } className={ trouble ? 'is-trouble' : undefined }>
						<strong>{ gatewayName[ gateway.gateway ] }</strong>
						<span>{ modeLabel[ gateway.mode ] ?? gateway.mode }</span>
						{ gateway.mode !== 'off' && (
							<small>
								{ gateway.last
									? /* translators: 1: number of events, 2: relative time */
									  sprintf( __( '%1$s events in 24 h, last %2$s', 'storewright' ), count( gateway.day ), timeAgo( gateway.last ) )
									: __( 'No webhook received yet', 'storewright' ) }
								{ gateway.failed > 0 && ' · ' + sprintf( /* translators: %s: number of events */ __( '%s failed', 'storewright' ), count( gateway.failed ) ) }
								{ gateway.stuck > 0 && ' · ' + sprintf( /* translators: %s: number of events */ __( '%s waiting', 'storewright' ), count( gateway.stuck ) ) }
							</small>
						) }
					</li>
				);
			} ) }
			<li>
				<strong>{ __( 'Cash on delivery', 'storewright' ) }</strong>
				<span>{ payments.cashOnDelivery ? __( 'On', 'storewright' ) : __( 'Off', 'storewright' ) }</span>
			</li>
		</ul>
	);
}

function Stock( { stock, lowStock } ) {
	return (
		<>
			<p className="sw-dash__figures">
				{ /* translators: 1: low stock count, 2: out of stock count */ }
				{ sprintf( __( '%1$s running low, %2$s out of stock', 'storewright' ), count( stock.lowstock ), count( stock.outofstock ) ) }
			</p>
			{ lowStock.length > 0 && (
				<ul className="sw-dash__list">
					{ lowStock.map( ( product ) => (
						<li key={ product.id }>
							<a href={ adminLink( `post.php?post=${ product.parent_id || product.id }&action=edit` ) }>{ product.name }</a>
							{ /* translators: %s: units left */ }
							<span>{ sprintf( __( '%s left', 'storewright' ), count( product.stock_quantity ) ) }</span>
						</li>
					) ) }
				</ul>
			) }
		</>
	);
}

function Products( { products } ) {
	if ( products.length === 0 ) {
		return <Empty>{ __( 'Nothing sold in this period yet.', 'storewright' ) }</Empty>;
	}
	return (
		<ol className="sw-dash__products">
			{ products.map( ( product ) => (
				<li key={ product.product_id }>
					<span className="sw-dash__thumb" dangerouslySetInnerHTML={ { __html: product.extended_info?.image ?? '' } } />
					<span className="sw-dash__product-name">{ product.extended_info?.name }</span>
					<span className="sw-dash__product-sold">
						{ /* translators: %s: units sold */ }
						{ sprintf( __( '%s sold', 'storewright' ), count( product.items_sold ) ) }
					</span>
					<span className="is-number">{ money( product.net_revenue ) }</span>
				</li>
			) ) }
		</ol>
	);
}

export const stage = () => {
	const [ period, setPeriod ] = useState( 'today' );
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		let current = true;
		setError( null );
		loadDashboard( period )
			.then( ( loaded ) => current && setData( loaded ) )
			.catch( ( failure ) => current && setError( failure.message || __( 'The numbers could not be loaded. Reload the page to try again.', 'storewright' ) ) );
		return () => {
			current = false;
		};
	}, [ period ] );

	const switcher = (
		<div className="sw-dash__periods" role="group" aria-label={ __( 'Period', 'storewright' ) }>
			{ periods.map( ( item ) => (
				<Button key={ item.key } variant={ item.key === period ? 'primary' : 'secondary' } size="compact" aria-pressed={ item.key === period } onClick={ () => setPeriod( item.key ) }>
					{ item.label }
				</Button>
			) ) }
		</div>
	);

	return (
		<Screen title={ __( 'Today', 'storewright' ) } actions={ switcher } className="sw-dash">
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ ! data && ! error && <Spinner /> }
			{ data && (
				<>
					<div className="sw-dash__kpis">
						<Kpi label={ __( 'Sales', 'storewright' ) } value={ money( data.now.totals.total_sales ) } now={ data.now.totals.total_sales } before={ data.before.totals.total_sales } />
						<Kpi label={ __( 'Orders', 'storewright' ) } value={ count( data.now.totals.orders_count ) } now={ data.now.totals.orders_count } before={ data.before.totals.orders_count } />
						<Kpi
							label={ __( 'Average order', 'storewright' ) }
							value={ money( data.now.totals.orders_count ? data.now.totals.total_sales / data.now.totals.orders_count : 0 ) }
							now={ data.now.totals.orders_count ? data.now.totals.total_sales / data.now.totals.orders_count : 0 }
							before={ data.before.totals.orders_count ? data.before.totals.total_sales / data.before.totals.orders_count : 0 }
						/>
						<Kpi label={ __( 'Refunds', 'storewright' ) } value={ money( data.now.totals.refunds ?? 0 ) } now={ data.now.totals.refunds ?? 0 } before={ data.before.totals.refunds ?? 0 } />
					</div>
					{ data.range.days > 1 && <Chart range={ data.range } intervals={ data.now.intervals } /> }
					<ul className="sw-dash__queues">
						{ queues.map( ( queue ) => (
							<li key={ queue.status }>
								<a className="sw-dash__queue" href={ adminLink( `admin.php?page=wc-orders&status=wc-${ queue.status }` ) }>
									<span className="sw-dash__count">{ count( data.queues[ queue.status ] ?? 0 ) }</span>
									<span className="sw-dash__label">{ queue.label }</span>
								</a>
							</li>
						) ) }
					</ul>
					<div className="sw-dash__grid">
						<Card
							title={ __( 'Recent orders', 'storewright' ) }
							className="is-wide"
							padded={ false }
							action={
								<a className="sw-link" href={ adminLink( 'admin.php?page=wc-orders' ) }>
									{ __( 'All orders', 'storewright' ) }
								</a>
							}
						>
							<Orders orders={ data.orders } />
						</Card>
						<Card title={ __( 'Payments', 'storewright' ) }>
							<Payments payments={ data.payments } />
						</Card>
						<Card
							title={ __( 'Stock', 'storewright' ) }
							action={
								<a className="sw-link" href={ adminLink( 'edit.php?post_type=product&stock_status=outofstock' ) }>
									{ __( 'Out of stock', 'storewright' ) }
								</a>
							}
						>
							<Stock stock={ data.stock } lowStock={ data.lowStock } />
						</Card>
						<Card title={ __( 'Best sellers', 'storewright' ) }>
							<Products products={ data.products } />
						</Card>
					</div>
				</>
			) }
		</Screen>
	);
};
