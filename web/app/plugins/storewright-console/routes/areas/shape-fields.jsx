import { Button, RadioControl, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

function FindPin( { onFind } ) {
	const [ pin, setPin ] = useState( '' );
	const [ message, setMessage ] = useState( '' );
	const find = () => onFind( pin ).then( setMessage );
	return (
		<div className="sw-areas__row">
			<TextControl
				label={ __( 'Show a PIN code on the map', 'storewright' ) }
				value={ pin }
				onChange={ setPin }
				inputMode="numeric"
				maxLength={ 6 }
				help={ message }
				onKeyDown={ ( event ) => {
					if ( event.key === 'Enter' ) {
						event.preventDefault();
						find();
					}
				} }
			/>
			<Button variant="secondary" onClick={ find }>
				{ __( 'Show', 'storewright' ) }
			</Button>
		</div>
	);
}

export function ShapeFields( { draft, onChange, onFind } ) {
	const set = ( changes ) => onChange( { ...draft, ...changes } );
	return (
		<fieldset className="sw-areas__fieldset">
			<legend className="sw-areas__legend">{ __( 'Map shape', 'storewright' ) }</legend>
			<RadioControl
				label={ __( 'Limit the area on the map', 'storewright' ) }
				hideLabelFromVision
				selected={ draft.shapeKind }
				options={ [
					{ label: __( 'No shape, the regions and PIN codes decide', 'storewright' ), value: 'none' },
					{ label: __( 'A radius around a point', 'storewright' ), value: 'radius' },
					{ label: __( 'An area drawn on the map', 'storewright' ), value: 'polygon' },
				] }
				onChange={ ( shapeKind ) => set( { shapeKind } ) }
			/>
			{ draft.shapeKind !== 'none' && (
				<p className="sw-areas__hint">{ __( 'With a shape, the shopper has to be inside it: their shared location if they gave one, otherwise the centre of their PIN code.', 'storewright' ) }</p>
			) }
			{ draft.shapeKind === 'radius' && (
				<>
					<TextControl type="number" min="0.1" step="0.1" label={ __( 'Radius in km', 'storewright' ) } value={ draft.radiusKm } onChange={ ( radiusKm ) => set( { radiusKm } ) } />
					<p className="sw-areas__hint">
						{ draft.centre
							? sprintf( /* translators: 1: latitude, 2: longitude */ __( 'Centre at %1$s, %2$s. Click the map to move it.', 'storewright' ), draft.centre[ 0 ].toFixed( 4 ), draft.centre[ 1 ].toFixed( 4 ) )
							: __( 'Click the map to place the centre.', 'storewright' ) }
					</p>
				</>
			) }
			{ draft.shapeKind === 'polygon' && (
				<>
					<p className="sw-areas__hint">
						{ sprintf( /* translators: %d: number of corners */ __( '%d corners so far. Click the map to add a corner, drag a corner to move it.', 'storewright' ), draft.corners.length ) }
					</p>
					<div className="sw-areas__buttons">
						<Button variant="secondary" size="compact" disabled={ draft.corners.length === 0 } accessibleWhenDisabled onClick={ () => set( { corners: draft.corners.slice( 0, -1 ) } ) }>
							{ __( 'Undo last corner', 'storewright' ) }
						</Button>
						<Button variant="tertiary" size="compact" disabled={ draft.corners.length === 0 } accessibleWhenDisabled onClick={ () => set( { corners: [] } ) }>
							{ __( 'Start again', 'storewright' ) }
						</Button>
					</div>
				</>
			) }
			{ draft.shapeKind !== 'none' && <FindPin onFind={ onFind } /> }
		</fieldset>
	);
}
