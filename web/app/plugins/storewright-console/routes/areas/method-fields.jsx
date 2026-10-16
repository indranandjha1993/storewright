import { Button, TextControl, ToggleControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { newMethod } from './draft';

function MethodRow( { method, number, onChange, onRemove } ) {
	/* translators: %d: method number in the list */
	const name = sprintf( __( 'Delivery method %d', 'storewright' ), number );
	return (
		<div className="sw-areas__method" role="group" aria-label={ name }>
			<ToggleControl label={ __( 'Offered', 'storewright' ) } checked={ method.enabled } onChange={ ( enabled ) => onChange( { enabled } ) } />
			{ method.editable ? (
				<>
					<TextControl label={ __( 'Name shoppers see', 'storewright' ) } value={ method.title } onChange={ ( title ) => onChange( { title } ) } />
					{ method.type === 'flat_rate' ? (
						<TextControl
							type="number"
							min="0"
							step="any"
							label={ __( 'Charge', 'storewright' ) }
							help={ __( 'Leave empty or 0 to make it free.', 'storewright' ) }
							value={ method.cost }
							onChange={ ( cost ) => onChange( { cost } ) }
						/>
					) : (
						<TextControl
							type="number"
							min="0"
							step="any"
							label={ __( 'Free on orders of at least', 'storewright' ) }
							help={ __( 'Leave empty to make it free on every order.', 'storewright' ) }
							value={ method.minAmount }
							onChange={ ( minAmount ) => onChange( { minAmount } ) }
						/>
					) }
				</>
			) : (
				<p className="sw-areas__hint">
					{ sprintf( /* translators: 1: method name, 2: what it costs */ __( '%1$s, %2$s. Its other settings are changed in the advanced settings.', 'storewright' ), method.title, method.charge ) }
				</p>
			) }
			<Button variant="tertiary" size="compact" isDestructive onClick={ onRemove }>
				{ __( 'Remove', 'storewright' ) }
			</Button>
		</div>
	);
}

export function MethodFields( { methods, onChange } ) {
	const change = ( key, changes ) => onChange( methods.map( ( method ) => ( method.key === key ? { ...method, ...changes } : method ) ) );
	return (
		<fieldset className="sw-areas__fieldset">
			<legend className="sw-areas__legend">{ __( 'Delivery methods', 'storewright' ) }</legend>
			{ methods.length === 0 && <p className="sw-areas__hint">{ __( 'Without a method, addresses in this area are refused at checkout.', 'storewright' ) }</p> }
			{ methods.map( ( method, index ) => (
				<MethodRow
					key={ method.key }
					method={ method }
					number={ index + 1 }
					onChange={ ( changes ) => change( method.key, changes ) }
					onRemove={ () => onChange( methods.filter( ( other ) => other.key !== method.key ) ) }
				/>
			) ) }
			<div className="sw-areas__buttons">
				<Button variant="secondary" size="compact" onClick={ () => onChange( [ ...methods, newMethod( 'flat_rate' ) ] ) }>
					{ __( 'Add a delivery charge', 'storewright' ) }
				</Button>
				<Button variant="secondary" size="compact" onClick={ () => onChange( [ ...methods, newMethod( 'free_shipping' ) ] ) }>
					{ __( 'Add free delivery', 'storewright' ) }
				</Button>
			</div>
		</fieldset>
	);
}
