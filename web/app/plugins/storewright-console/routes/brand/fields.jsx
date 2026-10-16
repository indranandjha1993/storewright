import { Button, RadioControl, SelectControl, TextControl } from '@wordpress/components';
import { useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { isHex } from './colour';

export function ColourField( { label, value, help, onChange } ) {
	const valid = isHex( value );
	return (
		<div className="sw-brand__colour">
			<input
				type="color"
				className="sw-brand__swatch"
				aria-label={ /* translators: %s: colour name */ sprintf( __( '%s colour picker', 'storewright' ), label ) }
				value={ valid && value.length === 7 ? value.toLowerCase() : '#000000' }
				onChange={ ( event ) => onChange( event.target.value ) }
			/>
			<TextControl label={ label } value={ value } help={ help } onChange={ ( next ) => onChange( next.trim() ) } className={ valid ? undefined : 'is-invalid' } />
		</div>
	);
}

export function FontField( { label, value, fonts, onChange } ) {
	const chosen = fonts.find( ( font ) => font.slug === value );
	return (
		<div className="sw-brand__font">
			<SelectControl
				label={ label }
				value={ value }
				options={ fonts.map( ( font ) => ( { label: font.name, value: font.slug } ) ) }
				onChange={ onChange }
			/>
			<p className="sw-brand__specimen" style={ { fontFamily: chosen?.family } } aria-hidden="true">
				{ __( 'Fresh stoneware, hand finished in small batches. 0123456789', 'storewright' ) }
			</p>
		</div>
	);
}

export function RadiusField( { value, onChange } ) {
	return (
		<RadioControl
			label={ __( 'Corners', 'storewright' ) }
			selected={ value }
			options={ [
				{ label: __( 'Sharp', 'storewright' ), value: 'sharp' },
				{ label: __( 'Soft', 'storewright' ), value: 'soft' },
				{ label: __( 'Round, with pill buttons', 'storewright' ), value: 'round' },
			] }
			onChange={ onChange }
		/>
	);
}

export function LogoField( { logo, busy, onUpload, onRemove } ) {
	const input = useRef();
	return (
		<div className="sw-brand__logo">
			<span className="sw-brand__label">{ __( 'Logo', 'storewright' ) }</span>
			{ logo.url ? <img className="sw-brand__logo-image" src={ logo.url } alt={ __( 'Current logo', 'storewright' ) } /> : <p className="sw-brand__hint">{ __( 'Without a logo the store shows its name in the heading typeface.', 'storewright' ) }</p> }
			<input
				ref={ input }
				type="file"
				accept="image/png,image/jpeg,image/svg+xml,image/webp"
				hidden
				aria-label={ __( 'Logo file', 'storewright' ) }
				onChange={ ( event ) => {
					const [ file ] = event.target.files;
					if ( file ) {
						onUpload( file );
					}
					event.target.value = '';
				} }
			/>
			<div className="sw-brand__buttons">
				<Button variant="secondary" size="compact" isBusy={ busy } disabled={ busy } onClick={ () => input.current.click() }>
					{ logo.url ? __( 'Replace logo', 'storewright' ) : __( 'Upload a logo', 'storewright' ) }
				</Button>
				{ logo.url && (
					<Button variant="tertiary" size="compact" isDestructive disabled={ busy } onClick={ onRemove }>
						{ __( 'Remove', 'storewright' ) }
					</Button>
				) }
			</div>
			<p className="sw-brand__hint">{ __( 'PNG or SVG with a transparent background works best, about 400 pixels wide.', 'storewright' ) }</p>
		</div>
	);
}
