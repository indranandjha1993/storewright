const METRES_PER_DEGREE = 111320;

export const INDIA = { point: [ 22.5, 79 ], zoom: 5 };

function extentOf( shape ) {
	if ( shape.kind === 'polygon' ) {
		return shape.polygon;
	}
	const dLat = shape.radius_m / METRES_PER_DEGREE;
	const dLng = dLat / Math.cos( ( shape.lat * Math.PI ) / 180 );
	return [
		[ shape.lat - dLat, shape.lng - dLng ],
		[ shape.lat + dLat, shape.lng + dLng ],
	];
}

export function boundsOf( shapes ) {
	const points = shapes.filter( Boolean ).flatMap( extentOf );
	if ( points.length === 0 ) {
		return null;
	}
	const lats = points.map( ( point ) => point[ 0 ] );
	const lngs = points.map( ( point ) => point[ 1 ] );
	return [
		[ Math.min( ...lats ), Math.min( ...lngs ) ],
		[ Math.max( ...lats ), Math.max( ...lngs ) ],
	];
}

export function viewOf( shapes ) {
	const bounds = boundsOf( shapes );
	return bounds ? { bounds } : INDIA;
}
