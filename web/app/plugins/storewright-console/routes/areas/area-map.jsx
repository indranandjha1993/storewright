import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useEffect, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { consoleData } from '../../lib/data';

const DRAFT_STYLE = { color: '#d63638', weight: 2, dashArray: '6 4', fillOpacity: 0.12, interactive: false };

const handle = () => L.divIcon( { className: 'sw-areas__handle', iconSize: [ 14, 14 ] } );

function outline( shape, style ) {
	if ( shape?.kind === 'radius' ) {
		return L.circle( [ shape.lat, shape.lng ], { ...style, radius: shape.radius_m } );
	}
	if ( shape?.kind === 'polygon' ) {
		return L.polygon( shape.polygon, style );
	}
	return null;
}

function drawDraft( group, draft, handlers ) {
	if ( draft.shapeKind === 'radius' && draft.centre ) {
		L.circle( draft.centre, { ...DRAFT_STYLE, radius: Number( draft.radiusKm ) * 1000 || 1 } ).addTo( group );
		L.marker( draft.centre, { icon: handle(), interactive: false } ).addTo( group );
	}
	if ( draft.shapeKind !== 'polygon' || draft.corners.length === 0 ) {
		return;
	}
	const line = draft.corners.length > 2 ? L.polygon( draft.corners, DRAFT_STYLE ) : L.polyline( draft.corners, DRAFT_STYLE );
	line.addTo( group );
	draft.corners.forEach( ( corner, index ) => {
		L.marker( corner, {
			icon: handle(),
			draggable: true,
			/* translators: %d: corner number */
			title: sprintf( __( 'Corner %d', 'storewright' ), index + 1 ),
		} )
			.on( 'drag', ( event ) => {
				const corners = draft.corners.map( ( point ) => L.latLng( point ) );
				corners[ index ] = event.target.getLatLng();
				line.setLatLngs( corners );
			} )
			.on( 'dragend', ( event ) => {
				const { lat, lng } = event.target.getLatLng();
				handlers.current.onMoveCorner( index, [ lat, lng ] );
			} )
			.addTo( group );
	} );
}

export function AreaMap( { shapes, draft, marker, view, onClick, onMoveCorner } ) {
	const element = useRef();
	const map = useRef();
	const layers = useRef();
	const handlers = useRef();
	handlers.current = { onClick, onMoveCorner };

	useEffect( () => {
		const instance = L.map( element.current );
		L.tileLayer( consoleData.mapTiles.url, { attribution: consoleData.mapTiles.attribution, maxZoom: 19 } ).addTo( instance );
		layers.current = L.layerGroup().addTo( instance );
		instance.on( 'click', ( event ) => handlers.current.onClick( [ event.latlng.lat, event.latlng.lng ] ) );
		const resized = new ResizeObserver( () => instance.invalidateSize() );
		resized.observe( element.current );
		map.current = instance;
		return () => {
			resized.disconnect();
			instance.remove();
		};
	}, [] );

	useEffect( () => {
		if ( view?.bounds ) {
			map.current.fitBounds( view.bounds, { padding: [ 32, 32 ], maxZoom: 14 } );
		} else if ( view ) {
			map.current.setView( view.point, view.zoom );
		}
	}, [ view ] );

	useEffect( () => {
		const group = layers.current;
		group.clearLayers();
		shapes.forEach( ( item ) => {
			outline( item.shape, { color: item.colour, weight: item.faint ? 1 : 2, fillOpacity: item.faint ? 0.04 : 0.14, interactive: false } )?.addTo( group );
		} );
		if ( draft ) {
			drawDraft( group, draft, handlers );
		}
		if ( marker ) {
			L.circleMarker( marker, { radius: 7, color: '#15171a', weight: 2, fillColor: '#ffffff', fillOpacity: 1, interactive: false } ).addTo( group );
		}
	}, [ shapes, draft, marker ] );

	return <div ref={ element } className="sw-areas__map" aria-label={ __( 'Delivery area map', 'storewright' ) } />;
}
