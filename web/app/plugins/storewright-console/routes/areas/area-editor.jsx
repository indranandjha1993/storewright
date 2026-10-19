import { Button, FormTokenField, Notice, TextareaControl, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { MethodFields } from './method-fields';
import { ShapeFields } from './shape-fields';

function RegionField( { value, places, onChange } ) {
	const byLabel = new Map( [ ...places, ...value ].map( ( place ) => [ place.label, place ] ) );
	return (
		<FormTokenField
			label={ __( 'Regions', 'storewright' ) }
			value={ value.map( ( region ) => region.label ) }
			suggestions={ places.map( ( place ) => place.label ) }
			onChange={ ( labels ) => onChange( labels.map( ( label ) => byLabel.get( typeof label === 'string' ? label : label.value ) ).filter( Boolean ) ) }
			__experimentalShowHowTo={ false }
		/>
	);
}

function DeleteArea( { name, busy, onDelete } ) {
	const [ asking, setAsking ] = useState( false );
	if ( ! asking ) {
		return (
			<Button variant="tertiary" isDestructive onClick={ () => setAsking( true ) }>
				{ __( 'Delete area', 'storewright' ) }
			</Button>
		);
	}
	return (
		<div className="sw-areas__confirm" role="group" aria-label={ __( 'Confirm deleting', 'storewright' ) }>
			<span>{ sprintf( /* translators: %s: delivery area name */ __( 'Delete %s? Addresses it covers move on to the next area.', 'storewright' ), name ) }</span>
			<Button variant="primary" isDestructive isBusy={ busy } disabled={ busy } onClick={ onDelete }>
				{ __( 'Delete', 'storewright' ) }
			</Button>
			<Button variant="tertiary" onClick={ () => setAsking( false ) }>
				{ __( 'Keep it', 'storewright' ) }
			</Button>
		</div>
	);
}

export function AreaEditor( { draft, places, savedName, error, busy, onChange, onFind, onSave, onCancel, onDelete } ) {
	const fixed = draft.id === 0;
	const set = ( key ) => ( value ) => onChange( { ...draft, [ key ]: value } );
	const submit = ( event ) => {
		event.preventDefault();
		onSave();
	};
	return (
		<form className="sw-areas__editor" onSubmit={ submit } aria-labelledby="sw-areas-editor-title">
			<h2 id="sw-areas-editor-title" className="sw-areas__title">
				{ draft.id === null ? __( 'New delivery area', 'storewright' ) : savedName }
			</h2>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ fixed ? (
				<p className="sw-areas__hint">{ __( 'Everywhere else covers every address no other area covers. Only its delivery methods can change.', 'storewright' ) }</p>
			) : (
				<>
					<TextControl label={ __( 'Name', 'storewright' ) } help={ __( 'Only staff see this name.', 'storewright' ) } value={ draft.name } onChange={ set( 'name' ) } />
					<RegionField value={ draft.regions } places={ places } onChange={ set( 'regions' ) } />
					<TextareaControl
						label={ __( 'PIN codes', 'storewright' ) }
						help={ __( 'One per line: 560001 for one PIN code, 560* for every PIN code starting with 560, 560001-560099 for a range. Leave empty for every PIN code in the regions.', 'storewright' ) }
						rows={ 4 }
						value={ draft.pins }
						onChange={ set( 'pins' ) }
					/>
					<ShapeFields draft={ draft } onChange={ onChange } onFind={ onFind } />
				</>
			) }
			<MethodFields methods={ draft.methods } onChange={ set( 'methods' ) } />
			<div className="sw-areas__buttons">
				<Button variant="primary" type="submit" isBusy={ busy } disabled={ busy } accessibleWhenDisabled>
					{ __( 'Save', 'storewright' ) }
				</Button>
				<Button variant="tertiary" onClick={ onCancel }>
					{ __( 'Cancel', 'storewright' ) }
				</Button>
			</div>
			{ draft.id > 0 && <DeleteArea name={ savedName } busy={ busy } onDelete={ onDelete } /> }
		</form>
	);
}
