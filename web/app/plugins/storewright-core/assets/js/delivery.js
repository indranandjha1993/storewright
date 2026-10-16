( function () {
	const settings = window.storewrightDelivery;
	if ( ! settings ) {
		return;
	}

	const blocks = Array.from( document.querySelectorAll( '.sw-delivery' ) );

	function show( answer ) {
		blocks.forEach( ( block ) => {
			const result = block.querySelector( '[data-sw-delivery-result]' );
			result.textContent = answer.message;
			result.className = 'sw-delivery__result sw-delivery__result--' + answer.status;
			if ( answer.pin ) {
				block.querySelector( '[data-sw-delivery-label]' ).textContent = answer.pin;
				block.querySelector( 'input[name="storewright_pin"]' ).value = answer.pin;
			}
		} );
	}

	function ask( body ) {
		return fetch( settings.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': settings.nonce },
			body: JSON.stringify( body ),
		} )
			.then( ( response ) => response.json() )
			.then( show );
	}

	blocks.forEach( ( block ) => {
		const form = block.querySelector( '[data-sw-delivery-form]' );
		const locate = block.querySelector( '[data-sw-delivery-locate]' );

		form.addEventListener( 'submit', ( event ) => {
			event.preventDefault();
			ask( { pin: form.elements.storewright_pin.value } );
		} );

		if ( ! navigator.geolocation ) {
			return;
		}
		locate.hidden = false;
		locate.addEventListener( 'click', () => {
			show( { message: settings.locating, status: 'pending' } );
			navigator.geolocation.getCurrentPosition(
				( position ) => ask( { lat: position.coords.latitude, lng: position.coords.longitude } ),
				() => show( { message: settings.denied, status: 'unknown' } ),
				{ timeout: 10000, maximumAge: 600000 }
			);
		} );
	} );
} )();
