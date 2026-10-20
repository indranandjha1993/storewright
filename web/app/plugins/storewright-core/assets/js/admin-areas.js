( function () {
	var data = window.storewrightAreas;
	var element = document.getElementById( 'sw-areas-map' );
	if ( ! data || ! element || ! window.L ) {
		return;
	}
	var L = window.L;
	var INDIA = { point: [ 22.5, 79 ], zoom: 5 };
	var DRAFT_STYLE = { color: '#d63638', weight: 2, dashArray: '6 4', fillOpacity: 0.12, interactive: false };
	var map = L.map( element );
	L.tileLayer( data.tiles.url, { attribution: data.tiles.attribution, maxZoom: 19 } ).addTo( map );
	var layers = L.layerGroup().addTo( map );
	new ResizeObserver( function () {
		map.invalidateSize();
	} ).observe( element );

	function outline( shape, style ) {
		if ( shape.kind === 'radius' ) {
			return L.circle( [ shape.lat, shape.lng ], Object.assign( { radius: shape.radius_m }, style ) );
		}
		return L.polygon( shape.polygon, style );
	}

	function extent( shape ) {
		if ( shape.kind === 'polygon' ) {
			return shape.polygon;
		}
		var dLat = shape.radius_m / 111320;
		var dLng = dLat / Math.cos( ( shape.lat * Math.PI ) / 180 );
		return [ [ shape.lat - dLat, shape.lng - dLng ], [ shape.lat + dLat, shape.lng + dLng ] ];
	}

	function fit( shapes ) {
		var points = [];
		shapes.forEach( function ( shape ) {
			points = points.concat( extent( shape ) );
		} );
		if ( points.length === 0 ) {
			map.setView( INDIA.point, INDIA.zoom );
			return;
		}
		map.fitBounds( L.latLngBounds( points ), { padding: [ 32, 32 ], maxZoom: 14 } );
	}

	function handle() {
		return L.divIcon( { className: 'sw-areas__handle', iconSize: [ 14, 14 ] } );
	}

	data.shapes.forEach( function ( item ) {
		outline( item.shape, { color: item.colour, weight: item.faint ? 1 : 2, fillOpacity: item.faint ? 0.04 : 0.14, interactive: false } ).addTo( map );
	} );

	if ( data.mode === 'list' ) {
		fit( data.shapes.map( function ( item ) {
			return item.shape;
		} ) );
		if ( data.marker ) {
			L.circleMarker( data.marker, { radius: 7, color: '#15171a', weight: 2, fillColor: '#ffffff', fillOpacity: 1, interactive: false } ).addTo( map );
			map.setView( data.marker, Math.max( map.getZoom(), 11 ) );
		}
		map.on( 'click', function ( event ) {
			window.location.href = data.listUrl + '&test_lat=' + event.latlng.lat.toFixed( 6 ) + '&test_lng=' + event.latlng.lng.toFixed( 6 );
		} );
		return;
	}

	var form = document.querySelector( '.sw-areas__editor' );
	var draft = data.draft;
	var fields = {
		lat: form.querySelector( '[name="shape_lat"]' ),
		lng: form.querySelector( '[name="shape_lng"]' ),
		corners: form.querySelector( '[name="shape_corners"]' ),
		radius: form.querySelector( '[name="radius_km"]' ),
		hint: form.querySelector( '[data-shape-hint]' ),
	};
	var strings = window.storewrightAreasText || {};

	function kind() {
		var checked = form.querySelector( '[name="shape_kind"]:checked' );
		return checked ? checked.value : 'none';
	}

	function show() {
		var current = kind();
		form.querySelectorAll( '[data-shape-only]' ).forEach( function ( node ) {
			var only = node.getAttribute( 'data-shape-only' );
			node.hidden = only === 'any' ? current === 'none' : only !== current;
		} );
		form.querySelector( '[data-shape-note]' ).hidden = current === 'none';
	}

	function text( key, values ) {
		var template = strings[ key ] || '';
		return template.replace( /%(\d)/g, function ( _, index ) {
			return values[ Number( index ) - 1 ];
		} );
	}

	function describe() {
		var current = kind();
		if ( current === 'radius' ) {
			fields.hint.textContent = draft.centre ? text( 'centre', [ draft.centre[ 0 ].toFixed( 4 ), draft.centre[ 1 ].toFixed( 4 ) ] ) : strings.placeCentre;
		} else if ( current === 'polygon' ) {
			fields.hint.textContent = text( 'corners', [ draft.corners.length ] );
		} else {
			fields.hint.textContent = '';
		}
	}

	function write() {
		fields.lat.value = draft.centre ? draft.centre[ 0 ] : '';
		fields.lng.value = draft.centre ? draft.centre[ 1 ] : '';
		fields.corners.value = JSON.stringify( draft.corners );
	}

	function draw() {
		layers.clearLayers();
		var current = kind();
		if ( current === 'radius' && draft.centre ) {
			L.circle( draft.centre, Object.assign( { radius: Number( fields.radius.value ) * 1000 || 1 }, DRAFT_STYLE ) ).addTo( layers );
			L.marker( draft.centre, { icon: handle(), interactive: false } ).addTo( layers );
		}
		if ( current === 'polygon' && draft.corners.length > 0 ) {
			var line = draft.corners.length > 2 ? L.polygon( draft.corners, DRAFT_STYLE ) : L.polyline( draft.corners, DRAFT_STYLE );
			line.addTo( layers );
			draft.corners.forEach( function ( corner, index ) {
				L.marker( corner, { icon: handle(), draggable: true, title: text( 'corner', [ index + 1 ] ) } )
					.on( 'drag', function ( event ) {
						var corners = draft.corners.map( function ( point ) {
							return L.latLng( point );
						} );
						corners[ index ] = event.target.getLatLng();
						line.setLatLngs( corners );
					} )
					.on( 'dragend', function ( event ) {
						var at = event.target.getLatLng();
						draft.corners[ index ] = [ at.lat, at.lng ];
						update();
					} )
					.addTo( layers );
			} );
		}
	}

	function update() {
		write();
		draw();
		describe();
		show();
	}

	map.on( 'click', function ( event ) {
		var point = [ event.latlng.lat, event.latlng.lng ];
		if ( kind() === 'radius' ) {
			draft.centre = point;
		} else if ( kind() === 'polygon' ) {
			draft.corners.push( point );
		} else {
			return;
		}
		update();
	} );
	form.querySelectorAll( '[name="shape_kind"]' ).forEach( function ( radio ) {
		radio.addEventListener( 'change', update );
	} );
	fields.radius.addEventListener( 'input', draw );
	form.querySelector( '[data-undo-corner]' ).addEventListener( 'click', function () {
		draft.corners.pop();
		update();
	} );
	form.querySelector( '[data-clear-corners]' ).addEventListener( 'click', function () {
		draft.corners = [];
		update();
	} );
	form.querySelector( '[data-find-pin]' ).addEventListener( 'click', function () {
		var pin = form.querySelector( '#sw-area-find' ).value;
		var note = form.querySelector( '[data-find-note]' );
		window.wp.apiFetch( { path: '/storewright/v1/areas/test', method: 'POST', data: { pin: pin } } )
			.then( function ( result ) {
				if ( ! result.point ) {
					note.textContent = result.message;
					return;
				}
				map.setView( result.point, 13 );
				note.textContent = text( 'showing', [ result.pin, result.district ] );
			} )
			.catch( function ( failure ) {
				note.textContent = failure.message;
			} );
	} );
	form.querySelector( '#sw-area-find' ).addEventListener( 'keydown', function ( event ) {
		if ( event.key === 'Enter' ) {
			event.preventDefault();
			form.querySelector( '[data-find-pin]' ).click();
		}
	} );

	var rows = form.querySelector( '[data-method-rows]' );
	var next = rows.children.length;
	function noMethods() {
		form.querySelector( '[data-no-methods]' ).hidden = rows.children.length > 0;
	}
	form.querySelectorAll( '[data-add-method]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var template = form.querySelector( '[data-method-template="' + button.getAttribute( 'data-add-method' ) + '"]' );
			var html = template.innerHTML.split( '__index__' ).join( String( next ) ).split( '__number__' ).join( String( rows.children.length + 1 ) );
			next++;
			rows.insertAdjacentHTML( 'beforeend', html );
			noMethods();
		} );
	} );
	rows.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-remove-method]' );
		if ( button ) {
			button.closest( '[data-method-row]' ).remove();
			noMethods();
		}
	} );

	var saved = data.shapes.map( function ( item ) {
		return item.shape;
	} );
	if ( draft.centre ) {
		saved = [ { kind: 'radius', lat: draft.centre[ 0 ], lng: draft.centre[ 1 ], radius_m: Number( fields.radius.value ) * 1000 || 1000 } ];
	} else if ( draft.corners.length > 0 ) {
		saved = [ { kind: 'polygon', polygon: draft.corners } ];
	}
	fit( saved );
	update();
} )();
