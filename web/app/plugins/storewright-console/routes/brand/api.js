import apiFetch from '@wordpress/api-fetch';

export const loadBrand = () => apiFetch( { path: '/storewright/v1/brand' } );

export const saveBrand = ( data ) => apiFetch( { path: '/storewright/v1/brand', method: 'PUT', data } );

export function uploadImage( file ) {
	const body = new FormData();
	body.append( 'file', file, file.name );
	body.append( 'title', file.name.replace( /\.[^.]+$/, '' ) );
	return apiFetch( { path: '/wp/v2/media', method: 'POST', body } );
}
