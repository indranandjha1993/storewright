( function () {
	if ( ! window.wp || ! window.wp.data ) {
		return;
	}
	const { __, sprintf } = window.wp.i18n;
	const select = window.wp.data.select( 'wc/store/cart' );
	if ( ! select ) {
		return;
	}
	const anchors = [
		{ selector: '.wc-block-cart__totals-title', where: 'afterend' },
		{ selector: '.wc-block-checkout__sidebar .wc-block-components-order-summary', where: 'beforebegin' },
	];

	function money( amount, cart ) {
		const t = cart.totals || {};
		const value = amount.toFixed( Number( t.currency_minor_unit ?? 2 ) ).replace( '.', t.currency_decimal_separator ?? '.' );
		return `${ t.currency_prefix ?? '' }${ value }${ t.currency_suffix ?? '' }`;
	}

	function message( cart ) {
		const info = cart && cart.extensions && cart.extensions.storewright;
		if ( ! info || info.freeDeliveryFrom === null || ! cart.items || cart.items.length === 0 ) {
			return null;
		}
		const done = info.remaining <= 0;
		return {
			done,
			progress: `${ Math.min( 100, Math.round( ( ( info.freeDeliveryFrom - info.remaining ) / info.freeDeliveryFrom ) * 100 ) ) }%`,
			/* translators: %s: amount still to add */
			text: done ? __( 'Your order qualifies for free delivery.', 'storewright' ) : sprintf( __( 'Add %s more for free delivery.', 'storewright' ), money( info.remaining, cart ) ),
		};
	}

	function render() {
		const state = message( select.getCartData() );
		anchors.forEach( ( anchor ) => {
			document.querySelectorAll( anchor.selector ).forEach( ( node ) => {
				const sibling = anchor.where === 'afterend' ? node.nextElementSibling : node.previousElementSibling;
				let bar = sibling && sibling.classList.contains( 'sw-free-delivery' ) ? sibling : null;
				if ( ! state ) {
					if ( bar ) {
						bar.remove();
					}
					return;
				}
				if ( ! bar ) {
					bar = document.createElement( 'div' );
					bar.className = 'sw-free-delivery';
					bar.setAttribute( 'role', 'status' );
					bar.innerHTML = '<p class="sw-free-delivery__text"></p><span class="sw-free-delivery__track"><span class="sw-free-delivery__fill"></span></span>';
					node.insertAdjacentElement( anchor.where, bar );
				}
				const text = bar.querySelector( '.sw-free-delivery__text' );
				const fill = bar.querySelector( '.sw-free-delivery__fill' );
				if ( text.textContent !== state.text ) {
					text.textContent = state.text;
				}
				if ( fill.style.width !== state.progress ) {
					fill.style.width = state.progress;
				}
				if ( bar.classList.contains( 'is-done' ) !== state.done ) {
					bar.classList.toggle( 'is-done', state.done );
				}
			} );
		} );
	}

	let queued = false;
	const schedule = () => {
		if ( queued ) {
			return;
		}
		queued = true;
		window.requestAnimationFrame( () => {
			queued = false;
			render();
		} );
	};

	let last = null;
	window.wp.data.subscribe( () => {
		const cart = select.getCartData();
		if ( cart !== last ) {
			last = cart;
			schedule();
		}
	} );
	new MutationObserver( ( records ) => {
		if ( records.some( ( record ) => ! ( record.target instanceof Element && record.target.closest( '.sw-free-delivery' ) ) ) ) {
			schedule();
		}
	} ).observe( document.body, { childList: true, subtree: true } );
} )();
