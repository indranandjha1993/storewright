( function () {
	if ( ! window.wp || ! window.wp.data ) {
		return;
	}
	const data = window.wp.data;
	const button = '.wc-block-components-checkout-place-order-button';
	let press = null;

	function quote() {
		const cart = data.select( 'wc/store/cart' );
		const rates = cart.getShippingRates().flatMap( ( pack ) => pack.shipping_rates.filter( ( rate ) => rate.selected ).map( ( rate ) => rate.rate_id ) );
		return JSON.stringify( [ cart.getCartTotals().total_price, rates ] );
	}

	function busy() {
		const checkout = data.select( 'wc/store/checkout' );
		return data.select( 'wc/store/cart' ).isCustomerDataUpdating() || checkout.isCalculating() || ! checkout.isIdle();
	}

	function taken() {
		const checkout = data.select( 'wc/store/checkout' );
		return checkout.isBeforeProcessing() || checkout.isProcessing() || checkout.isAfterProcessing() || checkout.isComplete() || checkout.hasError();
	}

	function resend( pending ) {
		let tries = 0;
		const timer = window.setInterval( () => {
			tries++;
			if ( press !== pending || tries > 100 || taken() ) {
				window.clearInterval( timer );
				press = null;
				return;
			}
			const target = pending.target.isConnected ? pending.target : document.querySelector( button );
			if ( busy() || ! target || target.disabled ) {
				return;
			}
			window.clearInterval( timer );
			press = null;
			if ( quote() === pending.quote ) {
				document.dispatchEvent( new CustomEvent( 'storewright:place-order-resent', { detail: { pressed: pending.quote, now: quote() } } ) );
				target.click();
			}
		}, 100 );
	}

	document.addEventListener(
		'pointerdown',
		( event ) => {
			const target = event.button === 0 && event.target.closest && event.target.closest( button );
			press = target ? { target, quote: quote() } : null;
		},
		true
	);

	document.addEventListener(
		'pointerup',
		( event ) => {
			const pending = press;
			if ( ! pending ) {
				return;
			}
			const box = pending.target.getBoundingClientRect();
			if ( event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom ) {
				press = null;
				return;
			}
			// WooCommerce drops a click that lands while it is still calculating, so the press is judged by the store, not the click.
			window.setTimeout( () => press === pending && resend( pending ), 250 );
		},
		true
	);
} )();
