import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

export async function page( path, query ) {
	const response = await apiFetch( { path: addQueryArgs( path, query ), parse: false } );
	return {
		items: await response.json(),
		total: Number( response.headers.get( 'X-WP-Total' ) ) || 0,
		pages: Number( response.headers.get( 'X-WP-TotalPages' ) ) || 1,
	};
}

export const personName = ( person, fallback ) => [ person?.first_name, person?.last_name ].filter( Boolean ).join( ' ' ) || person?.email || fallback;

export const initials = ( name ) =>
	String( name )
		.split( /[\s@._-]+/ )
		.filter( Boolean )
		.slice( 0, 2 )
		.map( ( part ) => part[ 0 ].toUpperCase() )
		.join( '' );

export const addressLines = ( address ) =>
	[ address?.address_1, address?.address_2, [ address?.city, address?.state ].filter( Boolean ).join( ', ' ), [ address?.postcode, address?.country ].filter( Boolean ).join( ' ' ) ].filter( Boolean );
