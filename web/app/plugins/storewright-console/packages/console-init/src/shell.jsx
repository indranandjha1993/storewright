import { createPortal, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { consoleData } from '../../../lib/data';

const SIDEBAR = '.boot-sidebar__scrollable';

export function Shell() {
	const [ host, setHost ] = useState( null );

	useEffect( () => {
		const find = () => setHost( ( current ) => current ?? document.querySelector( SIDEBAR ) );
		find();
		const watcher = new MutationObserver( find );
		watcher.observe( document.getElementById( 'storewright-app' ), { childList: true, subtree: true } );
		return () => watcher.disconnect();
	}, [] );

	if ( ! host ) {
		return null;
	}

	return createPortal(
		<>
			<a className="sw-shell__brand" href={ consoleData.homeUrl }>
				{ consoleData.logo ? <img className="sw-shell__logo" src={ consoleData.logo } alt={ consoleData.storeName } /> : <span className="sw-shell__name">{ consoleData.storeName }</span> }
				<span className="sw-shell__role">{ __( 'Console', 'storewright' ) }</span>
			</a>
			<div className="sw-shell__foot">
				<span className="sw-shell__user">{ consoleData.user }</span>
				<a href={ consoleData.logoutUrl }>{ __( 'Sign out', 'storewright' ) }</a>
			</div>
		</>,
		host
	);
}
