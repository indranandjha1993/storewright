( function ( wp ) {
	const names = window.storewrightEditorBlocks || [];
	const el = wp.element.createElement;
	names.forEach( ( name ) => {
		if ( wp.blocks.getBlockType( name ) ) {
			return;
		}
		wp.blocks.registerBlockType( name, {
			edit: ( props ) => el( 'div', wp.blockEditor.useBlockProps(), el( wp.serverSideRender, { block: name, attributes: props.attributes, httpMethod: 'POST' } ) ),
			save: () => null,
		} );
	} );

	const preferences = wp.data && wp.data.select( 'core/preferences' );
	if ( preferences ) {
		[
			[ 'core/edit-site', 'welcomeGuide' ],
			[ 'core/edit-site', 'welcomeGuideStyles' ],
			[ 'core/edit-site', 'welcomeGuidePage' ],
			[ 'core/edit-site', 'welcomeGuideTemplate' ],
			[ 'core/edit-post', 'welcomeGuide' ],
		].forEach( ( [ scope, key ] ) => {
			if ( preferences.get( scope, key ) !== false ) {
				wp.data.dispatch( 'core/preferences' ).set( scope, key, false );
			}
		} );
	}
} )( window.wp );
