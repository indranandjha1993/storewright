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
			<TextControl __next40pxDefaultSize label={ label } value={ value } help={ help } onChange={ ( next ) => onChange( next.trim() ) } className={ valid ? undefined : 'is-invalid' } />
		</div>
	);
}

export function FontField( { label, value, fonts, onChange } ) {
	const chosen = fonts.find( ( font ) => font.slug === value );
	return (
		<div className="sw-brand__font">
			<SelectControl __next40pxDefaultSize
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

export function ImageField( { label, image, busy, hint, empty, onUpload, onRemove } ) {
	const input = useRef();
	return (
		<div className="sw-brand__logo">
			<span className="sw-brand__label">{ label }</span>
			{ image.url ? <img className="sw-brand__logo-image" src={ image.url } alt={ /* translators: %s: field label */ sprintf( __( 'Current %s', 'storewright' ), label.toLowerCase() ) } /> : <p className="sw-brand__hint">{ empty }</p> }
			<input
				ref={ input }
				type="file"
				accept="image/png,image/jpeg,image/svg+xml,image/webp"
				hidden
				aria-label={ /* translators: %s: field label */ sprintf( __( '%s file', 'storewright' ), label ) }
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
					{ image.url ? __( 'Replace', 'storewright' ) : __( 'Upload', 'storewright' ) }
				</Button>
				{ image.url && (
					<Button variant="tertiary" size="compact" isDestructive disabled={ busy } onClick={ onRemove }>
						{ __( 'Remove', 'storewright' ) }
					</Button>
				) }
			</div>
			<p className="sw-brand__hint">{ hint }</p>
		</div>
	);
}
