import { __ } from '@wordpress/i18n';

export const STATUSES = [
	{ key: '', label: __( 'All', 'storewright' ) },
	{ key: 'processing', label: __( 'To fulfil', 'storewright' ), tone: 'is-good' },
	{ key: 'pending', label: __( 'Awaiting payment', 'storewright' ), tone: 'is-quiet' },
	{ key: 'on-hold', label: __( 'On hold', 'storewright' ), tone: 'is-quiet' },
	{ key: 'completed', label: __( 'Completed', 'storewright' ), tone: '' },
	{ key: 'cancelled', label: __( 'Cancelled', 'storewright' ), tone: 'is-bad' },
	{ key: 'refunded', label: __( 'Refunded', 'storewright' ), tone: 'is-bad' },
	{ key: 'failed', label: __( 'Failed', 'storewright' ), tone: 'is-bad' },
];

export const statusLabel = ( key ) => STATUSES.find( ( status ) => status.key === key )?.label ?? key;

export const statusTone = ( key ) => STATUSES.find( ( status ) => status.key === key )?.tone ?? '';

export const NEXT_STEPS = {
	pending: [ 'processing', 'on-hold', 'cancelled' ],
	'on-hold': [ 'processing', 'cancelled' ],
	processing: [ 'completed', 'on-hold', 'cancelled' ],
	completed: [ 'processing' ],
	cancelled: [ 'pending' ],
	failed: [ 'pending', 'cancelled' ],
	refunded: [],
};

const SHIPPING = {
	fulfilled: __( 'Shipped', 'storewright' ),
	partially_fulfilled: __( 'Part shipped', 'storewright' ),
};

export const shippingLabel = ( order ) => SHIPPING[ order.meta_data?.find( ( item ) => item.key === '_fulfillment_status' )?.value ] ?? '';

