( function () {
	const addresses = window.storewrightAddresses;
	if ( ! Array.isArray( addresses ) || addresses.length < 2 || ! window.wp || ! window.wp.data ) {
		return;
	}
	const { __ } = window.wp.i18n;
	const store = window.wp.data;

	function choose( address ) {
		const current = store.select( 'wc/store/cart' ).getCustomerData().shippingAddress;
		const next = { ...current };
		[ 'first_name', 'last_name', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone' ].forEach( ( key ) => {
			next[ key ] = address[ key ] ?? '';
		} );
		store.dispatch( 'wc/store/cart' ).setShippingAddress( next );
	}

	function current() {
		const shipping = store.select( 'wc/store/cart' ).getCustomerData().shippingAddress;
		return addresses.findIndex( ( address ) => address.postcode === shipping.postcode && address.address_1 === shipping.address_1 );
	}

	function render() {
		const form = document.querySelector( '#shipping-fields .wc-block-components-checkout-step__content' );
		if ( ! form ) {
			return;
		}
		let chooser = form.querySelector( ':scope > .sw-saved-addresses' );
		if ( ! chooser ) {
			chooser = document.createElement( 'fieldset' );
			chooser.className = 'sw-saved-addresses';
			const legend = document.createElement( 'legend' );
			legend.textContent = __( 'Deliver to a saved address', 'storewright' );
			chooser.appendChild( legend );
			addresses.forEach( ( address, index ) => {
				const button = document.createElement( 'button' );
				button.type = 'button';
				button.className = 'sw-saved-address';
				button.dataset.index = String( index );
				button.innerHTML = '<strong></strong><span></span><em></em>';
				button.querySelector( 'strong' ).textContent = [ address.first_name, address.last_name ].filter( Boolean ).join( ' ' );
				button.querySelector( 'span' ).textContent = [ address.address_1, address.city, address.postcode ].filter( Boolean ).join( ', ' );
				button.querySelector( 'em' ).textContent = address.badge;
				button.classList.add( `is-${ address.status }` );
				button.addEventListener( 'click', () => choose( address ) );
				chooser.appendChild( button );
			} );
			form.prepend( chooser );
		}
		const selected = String( current() );
		chooser.querySelectorAll( '.sw-saved-address' ).forEach( ( button ) => {
			const on = button.dataset.index === selected;
			if ( button.getAttribute( 'aria-pressed' ) !== String( on ) ) {
				button.setAttribute( 'aria-pressed', String( on ) );
			}
		} );
	}

	let queued = false;
	const schedule = () => {
		if ( ! queued ) {
			queued = true;
			window.requestAnimationFrame( () => {
				queued = false;
				render();
			} );
		}
	};
	store.subscribe( schedule );
	new MutationObserver( ( records ) => {
		if ( records.some( ( record ) => ! ( record.target instanceof Element && record.target.closest( '.sw-saved-addresses' ) ) ) ) {
			schedule();
		}
	} ).observe( document.body, { childList: true, subtree: true } );
} )();
