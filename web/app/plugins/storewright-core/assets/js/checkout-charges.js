( function () {
	var data = window.wp && window.wp.data;
	var checkout = window.wc && window.wc.blocksCheckout;
	if ( ! data || ! checkout || ! checkout.extensionCartUpdate ) {
		return;
	}
	var QUIET = 1500;
	var sent = null;
	var pending = null;
	var lastActivity = 0;

	document.addEventListener( 'input', function () {
		lastActivity = Date.now();
	}, true );

	function busy() {
		var cart = data.select( 'wc/store/cart' );
		var flow = data.select( 'wc/store/checkout' );
		if ( cart.isCustomerDataUpdating() || flow.isCalculating() || ! flow.isIdle() ) {
			lastActivity = Date.now();
			return true;
		}
		return Date.now() - lastActivity < QUIET;
	}

	// The checkout keeps name, street and phone local until the order is placed, so the cart answer is put back under what was typed.
	function send( method ) {
		pending = method;
		var tries = 0;
		var timer = window.setInterval( function () {
			tries++;
			if ( pending !== method || tries > 200 ) {
				window.clearInterval( timer );
				return;
			}
			if ( busy() ) {
				return;
			}
			window.clearInterval( timer );
			pending = null;
			sent = method;
			var customer = data.select( 'wc/store/cart' ).getCustomerData();
			var typed = { shipping: Object.assign( {}, customer.shippingAddress ), billing: Object.assign( {}, customer.billingAddress ) };
			checkout.extensionCartUpdate( { namespace: 'storewright', data: { payment_method: method }, overwriteDirtyCustomerData: false } ).then( function () {
				data.dispatch( 'wc/store/cart' ).setShippingAddress( typed.shipping );
				data.dispatch( 'wc/store/cart' ).setBillingAddress( typed.billing );
			} );
		}, 100 );
	}

	data.subscribe( function () {
		var store = data.select( 'wc/store/payment' );
		if ( ! store || ! store.getActivePaymentMethod ) {
			return;
		}
		var method = store.getActivePaymentMethod();
		if ( ! method || method === sent || method === pending ) {
			return;
		}
		if ( sent === null && method !== 'cod' ) {
			sent = method;
			return;
		}
		send( method );
	} );
} )();
