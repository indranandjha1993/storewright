( function () {
	const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
	const { getSetting } = window.wc.wcSettings;
	const { createElement } = window.wp.element;
	const { decodeEntities } = window.wp.htmlEntities;

	const data = getSetting( 'stripe_data', {} );
	const title = decodeEntities( data.title || 'Stripe' );
	const Description = () => createElement( 'p', null, decodeEntities( data.description || '' ) );

	registerPaymentMethod( {
		name: 'stripe',
		label: createElement( 'span', null, title ),
		ariaLabel: title,
		content: createElement( Description ),
		edit: createElement( Description ),
		canMakePayment: () => true,
		supports: { features: data.supports || [ 'products' ] },
	} );
} )();
