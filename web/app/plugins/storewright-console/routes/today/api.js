import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { localDate } from '../../lib/format';

export const PERIODS = { today: 1, week: 7, month: 30 };

export function rangeOf( period, offset = 0 ) {
	const days = PERIODS[ period ] ?? 1;
	const end = new Date();
	end.setHours( 0, 0, 0, 0 );
	end.setDate( end.getDate() - days * offset );
	const start = new Date( end );
	start.setDate( end.getDate() - days + 1 );
	return { after: `${ localDate( start ) }T00:00:00`, before: `${ localDate( end ) }T23:59:59`, days };
}

const stats = ( range ) =>
	apiFetch( {
		path: addQueryArgs( '/wc-analytics/reports/revenue/stats', {
			after: range.after,
			before: range.before,
			interval: 'day',
			fields: [ 'total_sales', 'orders_count', 'net_revenue', 'refunds' ],
			per_page: 100,
		} ),
	} );

export function loadDashboard( period ) {
	const current = rangeOf( period );
	const previous = rangeOf( period, 1 );
	return Promise.all( [
		stats( current ),
		stats( previous ),
		apiFetch( { path: '/wc/v3/reports/orders/totals' } ),
		apiFetch( { path: addQueryArgs( '/wc-analytics/reports/orders', { per_page: 10, orderby: 'date', order: 'desc', extended_info: true } ) } ),
		apiFetch( { path: addQueryArgs( '/wc-analytics/reports/products', { per_page: 4, orderby: 'items_sold', order: 'desc', after: current.after, before: current.before, extended_info: true } ) } ),
		apiFetch( { path: '/wc-analytics/reports/stock/stats' } ),
		apiFetch( { path: addQueryArgs( '/wc-analytics/reports/stock', { type: 'lowstock', per_page: 5 } ) } ),
		apiFetch( { path: '/storewright/v1/payments/health' } ),
		apiFetch( { path: addQueryArgs( '/storewright/v1/insights', { after: current.after, before: current.before } ) } ),
	] ).then( ( [ now, before, totals, orders, products, stock, lowStock, payments, insights ] ) => ( {
		range: current,
		now,
		before,
		queues: Object.fromEntries( totals.map( ( row ) => [ row.slug, row.total ] ) ),
		orders: orders.filter( ( order ) => ! order.parent_id ).slice( 0, 6 ),
		products,
		stock: stock.totals,
		lowStock,
		payments,
		insights,
	} ) );
}
