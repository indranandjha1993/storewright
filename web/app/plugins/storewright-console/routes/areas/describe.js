import { __, sprintf } from '@wordpress/i18n';

export function whereText( area ) {
	if ( area.id === 0 ) {
		return __( 'Every address no other area covers', 'storewright' );
	}
	return area.regions.length ? area.regions.map( ( region ) => region.label ).join( ', ' ) : __( 'Anywhere', 'storewright' );
}

export function pinsText( area ) {
	return area.pins.length ? area.pins.join( ', ' ) : __( 'Any PIN code', 'storewright' );
}

export function shapeText( shape ) {
	if ( shape?.kind === 'radius' ) {
		/* translators: 1: distance in km, 2: latitude, 3: longitude */
		return sprintf( __( 'Within %1$s km of %2$s, %3$s', 'storewright' ), shape.radius_m / 1000, shape.lat.toFixed( 4 ), shape.lng.toFixed( 4 ) );
	}
	if ( shape?.kind === 'polygon' ) {
		/* translators: %d: number of corners */
		return sprintf( __( 'Inside a drawn area with %d corners', 'storewright' ), shape.polygon.length );
	}
	return __( 'No map shape', 'storewright' );
}

export function methodsText( area ) {
	const offered = area.methods.filter( ( method ) => method.enabled );
	if ( offered.length === 0 ) {
		return __( 'None, so these addresses are refused at checkout', 'storewright' );
	}
	return offered.map( ( method ) => `${ method.title }: ${ method.charge }` ).join( '; ' );
}
