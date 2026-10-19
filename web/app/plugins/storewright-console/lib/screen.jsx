import { __ } from '@wordpress/i18n';

export function Screen( { title, lead, actions, children, className = '', wide = false, split = null, aside = null } ) {
	const classes = [ 'sw-screen', ( wide || split ) && 'is-wide', split && `is-split is-split-${ split }`, split && aside && 'has-aside', className ].filter( Boolean ).join( ' ' );
	return (
		<div className={ classes }>
			<header className="sw-screen__head">
				<div className="sw-screen__heading">
					<h1 className="sw-screen__title">{ title }</h1>
					{ lead && <p className="sw-screen__lead">{ lead }</p> }
				</div>
				{ actions && <div className="sw-screen__actions">{ actions }</div> }
			</header>
			{ split ? (
				<div className="sw-screen__body">
					<div className="sw-screen__main">{ children }</div>
					{ aside && <div className="sw-screen__aside">{ aside }</div> }
				</div>
			) : (
				children
			) }
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
