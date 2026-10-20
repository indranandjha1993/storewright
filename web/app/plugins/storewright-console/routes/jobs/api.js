import apiFetch from '@wordpress/api-fetch';

export const loadJobs = () => apiFetch( { path: '/storewright/v1/jobs' } );

export const retryJob = ( id ) => apiFetch( { path: `/storewright/v1/jobs/${ id }/retry`, method: 'POST' } );

export const dismissJob = ( id ) => apiFetch( { path: `/storewright/v1/jobs/${ id }/dismiss`, method: 'POST' } );
