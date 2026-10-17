( function () {
	const settings = window.storewrightSearch;
	const form = document.querySelector( '.sw-header__actions .wp-block-search' );
	const input = form && form.querySelector( '.wp-block-search__input' );
	if ( ! settings || ! input ) {
		return;
	}
	const panel = document.createElement( 'div' );
	panel.className = 'sw-suggest';
	panel.id = 'sw-suggest';
	panel.hidden = true;
	panel.setAttribute( 'role', 'listbox' );
	panel.setAttribute( 'aria-label', settings.label );
	form.appendChild( panel );
	input.setAttribute( 'aria-controls', panel.id );
	input.setAttribute( 'aria-autocomplete', 'list' );
	input.setAttribute( 'autocomplete', 'off' );

	let timer = null;
	let latest = '';

	function close() {
		panel.hidden = true;
		input.setAttribute( 'aria-expanded', 'false' );
	}

	function item( product ) {
		const link = document.createElement( 'a' );
		link.className = 'sw-suggest__item';
		link.href = product.permalink;
		link.setAttribute( 'role', 'option' );
		const image = document.createElement( 'span' );
		image.className = 'sw-suggest__image';
		if ( product.images && product.images[ 0 ] ) {
			const img = document.createElement( 'img' );
			img.src = product.images[ 0 ].thumbnail;
			img.alt = '';
			img.loading = 'lazy';
			image.appendChild( img );
		}
		const name = document.createElement( 'span' );
		name.className = 'sw-suggest__name';
		name.textContent = product.name.replace( /&#\d+;|&[a-z]+;/g, ( entity ) => {
			const t = document.createElement( 'textarea' );
			t.innerHTML = entity;
			return t.value;
		} );
		const price = document.createElement( 'span' );
		price.className = 'sw-suggest__price';
		const prices = product.prices || {};
		const minor = Number( prices.currency_minor_unit ?? 2 );
		price.textContent = `${ prices.currency_prefix ?? '' }${ ( Number( prices.price ) / 10 ** minor ).toFixed( minor ) }${ prices.currency_suffix ?? '' }`;
		link.append( image, name, price );
		return link;
	}

	function render( query, products ) {
		panel.replaceChildren();
		if ( products.length === 0 ) {
			const empty = document.createElement( 'p' );
			empty.className = 'sw-suggest__empty';
			empty.textContent = settings.empty;
			panel.appendChild( empty );
		} else {
			products.forEach( ( product ) => panel.appendChild( item( product ) ) );
		}
		const all = document.createElement( 'a' );
		all.className = 'sw-suggest__all';
		all.setAttribute( 'role', 'option' );
		all.href = `${ settings.results }?s=${ encodeURIComponent( query ) }&post_type=product`;
		all.textContent = settings.all.replace( '%s', query );
		panel.appendChild( all );
		panel.hidden = false;
		input.setAttribute( 'aria-expanded', 'true' );
	}

	input.addEventListener( 'input', () => {
		const query = input.value.trim();
		window.clearTimeout( timer );
		if ( query.length < 2 ) {
			close();
			return;
		}
		timer = window.setTimeout( () => {
			latest = query;
			fetch( `${ settings.endpoint }?search=${ encodeURIComponent( query ) }&per_page=5` )
				.then( ( response ) => ( response.ok ? response.json() : [] ) )
				.then( ( products ) => {
					if ( query === latest ) {
						render( query, products );
					}
				} )
				.catch( close );
		}, 200 );
	} );

	input.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'ArrowDown' && ! panel.hidden ) {
			event.preventDefault();
			const first = panel.querySelector( 'a' );
			if ( first ) {
				first.focus();
			}
		}
	} );

	panel.addEventListener( 'keydown', ( event ) => {
		const links = Array.from( panel.querySelectorAll( 'a' ) );
		const index = links.indexOf( document.activeElement );
		if ( event.key === 'ArrowDown' && index < links.length - 1 ) {
			event.preventDefault();
			links[ index + 1 ].focus();
		} else if ( event.key === 'ArrowUp' ) {
			event.preventDefault();
			( index > 0 ? links[ index - 1 ] : input ).focus();
		}
	} );

	document.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' && ! panel.hidden ) {
			close();
			input.focus();
		}
	} );

	document.addEventListener( 'click', ( event ) => {
		if ( ! form.contains( event.target ) ) {
			close();
		}
	} );
} )();
