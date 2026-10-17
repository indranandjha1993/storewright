( function () {
	const settings = window.storewrightCheckoutPin;
	if ( ! settings || ! window.wp || ! window.wp.data ) {
		return;
	}
	const seen = {};

	function lookup( pin ) {
		return fetch( settings.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': settings.nonce },
			body: JSON.stringify( { pin } ),
		} ).then( ( response ) => ( response.ok ? response.json() : null ) );
	}

	function fill( kind, pin ) {
		if ( seen[ kind ] === pin ) {
			return;
		}
		seen[ kind ] = pin;
		lookup( pin ).then( ( answer ) => {
			if ( ! answer || answer.lookup !== 'found' ) {
				return;
			}
			const store = window.wp.data;
			const current = kind === 'shipping' ? store.select( 'wc/store/cart' ).getCustomerData().shippingAddress : store.select( 'wc/store/cart' ).getCustomerData().billingAddress;
			if ( ! current || current.postcode !== pin ) {
				return;
			}
			const next = { ...current, country: 'IN', state: answer.state || current.state, city: current.city || answer.district };
			const setter = kind === 'shipping' ? 'setShippingAddress' : 'setBillingAddress';
			store.dispatch( 'wc/store/cart' )[ setter ]( next );
		} );
	}

	document.addEventListener( 'input', ( event ) => {
		const input = event.target;
		if ( ! input || ! input.id || ! /^(shipping|billing)-postcode$/.test( input.id ) ) {
			return;
		}
		const pin = input.value.replace( /\D/g, '' );
		if ( /^[1-8][0-9]{5}$/.test( pin ) ) {
			window.setTimeout( () => fill( input.id.split( '-' )[ 0 ], pin ), 400 );
		}
	} );
} )();
