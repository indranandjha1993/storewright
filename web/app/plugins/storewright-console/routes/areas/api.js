import apiFetch from '@wordpress/api-fetch';

const base = '/storewright/v1/areas';

export const loadAreas = () => apiFetch( { path: base } );

export const createArea = ( data ) => apiFetch( { path: base, method: 'POST', data } );

export const updateArea = ( id, data ) => apiFetch( { path: `${ base }/${ id }`, method: 'PUT', data } );

export const deleteArea = ( id ) => apiFetch( { path: `${ base }/${ id }`, method: 'DELETE' } );

export const reorderAreas = ( ids ) => apiFetch( { path: `${ base }/order`, method: 'PUT', data: { ids } } );

export const testAddress = ( data ) => apiFetch( { path: `${ base }/test`, method: 'POST', data } );
