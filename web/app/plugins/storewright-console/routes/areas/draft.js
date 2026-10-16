import { __ } from '@wordpress/i18n';

let lastKey = 0;

const nextKey = () => `new-${ ++lastKey }`;

export function newMethod( type ) {
	return {
		key: nextKey(),
		id: 0,
		type,
		title: type === 'flat_rate' ? __( 'Delivery', 'storewright' ) : __( 'Free delivery', 'storewright' ),
		cost: '',
		minAmount: '',
		enabled: true,
		editable: true,
	};
}

export function newDraft( places ) {
	return {
		id: null,
		name: '',
		regions: places.slice( 0, 1 ),
		pins: '',
		shapeKind: 'none',
		centre: null,
		radiusKm: '5',
		corners: [],
		methods: [ newMethod( 'flat_rate' ) ],
	};
}

export function draftOf( area ) {
	const { shape } = area;
	return {
		id: area.id,
		name: area.name,
		regions: area.regions,
		pins: area.pins.join( '\n' ),
		shapeKind: shape?.kind ?? 'none',
		centre: shape?.kind === 'radius' ? [ shape.lat, shape.lng ] : null,
		radiusKm: shape?.kind === 'radius' ? String( shape.radius_m / 1000 ) : '5',
		corners: shape?.kind === 'polygon' ? shape.polygon : [],
		methods: area.methods.map( ( method ) => ( { ...method, key: `method-${ method.id }` } ) ),
	};
}

export function shapeOf( draft ) {
	if ( draft.shapeKind === 'radius' && draft.centre ) {
		return { kind: 'radius', lat: draft.centre[ 0 ], lng: draft.centre[ 1 ], radius_m: Number( draft.radiusKm ) * 1000 };
	}
	if ( draft.shapeKind === 'polygon' && draft.corners.length > 0 ) {
		return { kind: 'polygon', polygon: draft.corners };
	}
	return null;
}

export function problemWith( draft ) {
	if ( draft.shapeKind === 'radius' && ! draft.centre ) {
		return __( 'Click the map to place the centre of the radius.', 'storewright' );
	}
	if ( draft.shapeKind === 'radius' && ! ( Number( draft.radiusKm ) > 0 ) ) {
		return __( 'The radius must be more than 0 km.', 'storewright' );
	}
	if ( draft.shapeKind === 'polygon' && draft.corners.length < 3 ) {
		return __( 'Click the map at least three times to draw the area.', 'storewright' );
	}
	return null;
}

export function payloadOf( draft ) {
	return {
		name: draft.name,
		regions: draft.regions.map( ( { type, code } ) => ( { type, code } ) ),
		pins: draft.pins,
		shape: shapeOf( draft ),
		methods: draft.methods.map( ( method ) =>
			method.editable
				? { id: method.id, type: method.type, title: method.title, cost: method.cost, minAmount: method.minAmount, enabled: method.enabled }
				: { id: method.id, enabled: method.enabled }
		),
	};
}
