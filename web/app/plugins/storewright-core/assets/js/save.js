( function () {
	const settings = window.storewrightSave;
	if ( ! settings ) {
		return;
	}
	document.querySelectorAll( '[data-sw-save]' ).forEach( ( button ) => {
		button.addEventListener( 'click', () => {
			button.disabled = true;
			fetch( settings.endpoint + button.dataset.swSave, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': settings.nonce },
			} )
				.then( ( response ) => ( response.ok ? response.json() : Promise.reject( response ) ) )
				.then( ( answer ) => {
					button.setAttribute( 'aria-pressed', answer.saved ? 'true' : 'false' );
					button.querySelector( 'span' ).textContent = answer.saved ? settings.saved : settings.save;
				} )
				.finally( () => {
					button.disabled = false;
				} );
		} );
	} );
} )();
