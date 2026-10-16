import { __ } from '@wordpress/i18n';
import { consoleData } from './data';

export function Screen( { title, eyebrow, lead, actions, children, className = '', wide = false } ) {
	return (
		<div className={ `sw-screen ${ wide ? 'is-wide' : '' } ${ className }`.trim() }>
			<header className="sw-screen__head">
				<div className="sw-screen__heading">
					<p className="sw-screen__eyebrow">{ eyebrow ?? consoleData.storeName }</p>
					<h1 className="sw-screen__title">{ title }</h1>
					{ lead && <p className="sw-screen__lead">{ lead }</p> }
				</div>
				{ actions && <div className="sw-screen__actions">{ actions }</div> }
			</header>
			{ children }
		</div>
	);
}

export function Card( { title, action, children, className = '', padded = true } ) {
	return (
		<section className={ `sw-card ${ padded ? '' : 'is-flush' } ${ className }`.trim() } aria-label={ title }>
			{ ( title || action ) && (
				<header className="sw-card__head">
					{ title && <h2 className="sw-card__title">{ title }</h2> }
					{ action }
				</header>
			) }
			{ children }
		</section>
	);
}

export function Empty( { children } ) {
	return <p className="sw-empty">{ children ?? __( 'Nothing here yet.', 'storewright' ) }</p>;
}
