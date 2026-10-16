import { page } from '../../lib/wc';

export const PER_PAGE = 20;

export const loadCustomers = ( { search, pageNumber } ) =>
	page( '/wc/v3/customers', { per_page: PER_PAGE, page: pageNumber, search: search || undefined, orderby: 'registered_date', order: 'desc', role: 'customer' } );

export const loadCustomerOrders = ( id ) => page( '/wc/v3/orders', { per_page: 10, customer: id, orderby: 'date', order: 'desc' } );
