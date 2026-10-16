import { __ } from '@wordpress/i18n';
import { paletteOf } from './colour';

export function Preview( { settings, fonts, name, logo } ) {
	const p = paletteOf( settings );
	const family = ( slug ) => fonts.find( ( font ) => font.slug === slug )?.family ?? 'inherit';
	const style = {
		'--p-base': p.base,
		'--p-surface': p.surface,
		'--p-contrast': p.contrast,
		'--p-muted': p.muted,
		'--p-line': p.line,
		'--p-accent': p.accent,
		'--p-accent-hover': p.accentHover,
		'--p-accent-soft': p.accentSoft,
		'--p-on-accent': p.onAccent,
		'--p-sale': p.sale,
		'--p-on-sale': p.onSale,
		'--p-radius': p.radius,
		'--p-radius-small': p.small,
		'--p-radius-button': p.button,
		'--p-font-body': family( settings.bodyFont ),
		'--p-font-heading': family( settings.headingFont ),
	};
	return (
		<div className="sw-brand__preview" style={ style } aria-label={ __( 'Preview of the store', 'storewright' ) }>
			<div className="sw-brand__preview-header">
				{ logo ? <img src={ logo } alt="" /> : <strong>{ name || __( 'Your store', 'storewright' ) }</strong> }
				<span>{ __( 'Shop', 'storewright' ) }</span>
				<span>{ __( 'Account', 'storewright' ) }</span>
			</div>
			<div className="sw-brand__preview-hero">
				<h2>{ __( 'Made well, delivered to your door', 'storewright' ) }</h2>
				<p>{ __( 'Everyday things for home and work, with tracked delivery and simple returns.', 'storewright' ) }</p>
				<span className="sw-brand__preview-button">{ __( 'Shop everything', 'storewright' ) }</span>
			</div>
			<div className="sw-brand__preview-cards">
				{ [ 'Stoneware Mug', 'Everyday Tee' ].map( ( product, index ) => (
					<div className="sw-brand__preview-card" key={ product }>
						<div className="sw-brand__preview-image">{ index === 1 && <em>{ __( 'Sale', 'storewright' ) }</em> }</div>
						<a href="#preview" onClick={ ( event ) => event.preventDefault() }>{ product }</a>
						<small>{ index === 1 ? '₹649.00' : '₹449.00' }</small>
					</div>
				) ) }
			</div>
			<p className="sw-brand__preview-note">{ __( 'Links, buttons, badges and the admin follow these choices. Emails use the colours and the closest safe typeface.', 'storewright' ) }</p>
		</div>
	);
}
