import apiFetch from '@wordpress/api-fetch';
import { page } from '../../lib/wc';

export const PER_PAGE = 20;

export const loadOrders = ( { status, search, pageNumber } ) =>
	page( '/wc/v3/orders', { per_page: PER_PAGE, page: pageNumber, status: status || 'any', search: search || undefined, orderby: 'date', order: 'desc' } );

export const loadTotals = () => apiFetch( { path: '/wc/v3/reports/orders/totals' } ).then( ( rows ) => Object.fromEntries( rows.map( ( row ) => [ row.slug, row.total ] ) ) );

export const loadOrder = ( id ) => apiFetch( { path: `/wc/v3/orders/${ id }` } );

export const loadNotes = ( id ) => apiFetch( { path: `/wc/v3/orders/${ id }/notes` } );

export const addNote = ( id, note, customerNote ) => apiFetch( { path: `/wc/v3/orders/${ id }/notes`, method: 'POST', data: { note, customer_note: customerNote } } );

export const setStatus = ( id, status ) => apiFetch( { path: `/wc/v3/orders/${ id }`, method: 'PUT', data: { status } } );

export const loadFulfilments = ( id ) => apiFetch( { path: `/wc/v3/orders/${ id }/fulfillments` } );

export const createFulfilment = ( id, data ) => apiFetch( { path: `/wc/v3/orders/${ id }/fulfillments`, method: 'POST', data } );

export const deleteFulfilment = ( orderId, id ) => apiFetch( { path: `/wc/v3/orders/${ orderId }/fulfillments/${ id }?notify_customer=false`, method: 'DELETE' } );
