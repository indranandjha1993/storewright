import apiFetch from '@wordpress/api-fetch';
import { page } from '../../lib/wc';

export const PER_PAGE = 20;

export const loadProducts = ( { search, stock, pageNumber } ) =>
	page( '/wc/v3/products', { per_page: PER_PAGE, page: pageNumber, search: search || undefined, stock_status: stock || undefined, status: 'any', orderby: 'date', order: 'desc' } );

export const saveProduct = ( id, data ) => apiFetch( { path: `/wc/v3/products/${ id }`, method: 'PUT', data } );
