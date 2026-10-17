import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

export const loadHealth = () => apiFetch( { path: '/storewright/v1/payments/health' } );

export const loadUnsettled = () => apiFetch( { path: '/storewright/v1/payments/unsettled' } );

export const checkOrder = ( id ) => apiFetch( { path: `/storewright/v1/payments/unsettled/${ id }`, method: 'POST' } );

export const loadEvents = ( { gateway, status, page } ) => apiFetch( { path: addQueryArgs( '/storewright/v1/payments/events', { gateway, status, page } ) } );
