import apiFetch from '@wordpress/api-fetch';
import { Button, CheckboxControl, Notice, SelectControl, Spinner, TextControl, ToggleControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { consoleData } from '../../lib/data';
import { Card, Screen } from '../../lib/screen';
import './style.scss';

const toDraft = ( data ) => ( {
	store: { ...data.store },
	emails: { senderName: data.emails.senderName, senderEmail: data.emails.senderEmail, newOrderTo: data.emails.newOrderTo, enabled: data.emails.customer.filter( ( email ) => email.enabled ).map( ( email ) => email.id ) },
	checkout: { ...data.checkout },
} );

export const stage = () => {
	const [ data, setData ] = useState( null );
	const [ draft, setDraft ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		let current = true;
		apiFetch( { path: '/storewright/v1/settings' } ).then( ( loaded ) => {
			if ( current ) {
				setData( loaded );
				setDraft( toDraft( loaded ) );
			}
		} );
		return () => {
			current = false;
		};
	}, [] );

	if ( ! draft ) {
		return (
			<Screen title={ __( 'Settings', 'storewright' ) }>
				<Spinner />
			</Screen>
		);
	}

	const set = ( section, key ) => ( value ) => setDraft( { ...draft, [ section ]: { ...draft[ section ], [ key ]: value } } );
	const toggleEmail = ( id, on ) => set( 'emails', 'enabled' )( on ? [ ...draft.emails.enabled, id ] : draft.emails.enabled.filter( ( item ) => item !== id ) );
	const dirty = JSON.stringify( draft ) !== JSON.stringify( toDraft( data ) );

	const save = ( event ) => {
		event.preventDefault();
		setBusy( true );
		setNotice( null );
		apiFetch( { path: '/storewright/v1/settings', method: 'PUT', data: draft } )
			.then( ( saved ) => {
				setData( saved );
				setDraft( toDraft( saved ) );
				setNotice( { status: 'success', text: __( 'Settings saved.', 'storewright' ) } );
			} )
			.catch( ( failure ) => setNotice( { status: 'error', text: failure.message } ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<Screen title={ __( 'Settings', 'storewright' ) } lead={ __( 'The store details customers see on emails and invoices, who gets told about orders, and how people can pay.', 'storewright' ) }>
			<form className="sw-settings" onSubmit={ save } aria-label={ __( 'Settings', 'storewright' ) }>
				<Card title={ __( 'Store', 'storewright' ) }>
					<div className="sw-settings__grid">
						<TextControl __next40pxDefaultSize label={ __( 'Address', 'storewright' ) } value={ draft.store.address1 } onChange={ set( 'store', 'address1' ) } />
						<TextControl __next40pxDefaultSize label={ __( 'Area or landmark', 'storewright' ) } value={ draft.store.address2 } onChange={ set( 'store', 'address2' ) } />
						<TextControl __next40pxDefaultSize label={ __( 'City', 'storewright' ) } value={ draft.store.city } onChange={ set( 'store', 'city' ) } />
						<TextControl __next40pxDefaultSize label={ __( 'PIN code', 'storewright' ) } inputMode="numeric" maxLength={ 6 } value={ draft.store.postcode } onChange={ set( 'store', 'postcode' ) } />
						<SelectControl __next40pxDefaultSize label={ __( 'State', 'storewright' ) } value={ draft.store.state } options={ data.states.map( ( state ) => ( { label: state.name, value: state.code } ) ) } onChange={ set( 'store', 'state' ) } />
						<TextControl __next40pxDefaultSize label={ __( 'Phone', 'storewright' ) } type="tel" value={ draft.store.phone } onChange={ set( 'store', 'phone' ) } />
						<TextControl __next40pxDefaultSize label={ __( 'GSTIN', 'storewright' ) } help={ __( 'Printed on invoices. Leave empty if the business is not registered for GST.', 'storewright' ) } value={ draft.store.gstin } maxLength={ 15 } onChange={ set( 'store', 'gstin' ) } />
						<SelectControl
							__next40pxDefaultSize
							label={ __( 'Usual GST rate', 'storewright' ) }
							help={ __( 'Used for products without their own rate. Prices already include it.', 'storewright' ) }
							value={ draft.store.gstRate }
							options={ data.gstRates.map( ( rate ) => ( { label: `${ rate }%`, value: String( rate ) } ) ) }
							onChange={ set( 'store', 'gstRate' ) }
						/>
						<TextControl __next40pxDefaultSize label={ __( 'Invoice prefix', 'storewright' ) } help={ __( 'Invoices are numbered like INV/26-27/0001, starting again each April.', 'storewright' ) } value={ draft.store.prefix } maxLength={ 4 } onChange={ set( 'store', 'prefix' ) } />
					</div>
				</Card>
				<Card title={ __( 'Emails', 'storewright' ) }>
					<div className="sw-settings__grid">
						<TextControl __next40pxDefaultSize label={ __( 'Sender name', 'storewright' ) } value={ draft.emails.senderName } placeholder={ consoleData.storeName } onChange={ set( 'emails', 'senderName' ) } />
						<TextControl __next40pxDefaultSize label={ __( 'Sender address', 'storewright' ) } type="email" value={ draft.emails.senderEmail } onChange={ set( 'emails', 'senderEmail' ) } />
						<TextControl __next40pxDefaultSize className="is-wide" label={ __( 'Send new orders to', 'storewright' ) } help={ __( 'One or more addresses, separated by commas.', 'storewright' ) } value={ draft.emails.newOrderTo } onChange={ set( 'emails', 'newOrderTo' ) } />
					</div>
					<fieldset className="sw-settings__emails">
						<legend>{ __( 'Emails customers get', 'storewright' ) }</legend>
						{ data.emails.customer.map( ( email ) => (
							<CheckboxControl key={ email.id } label={ email.title } checked={ draft.emails.enabled.includes( email.id ) } onChange={ ( on ) => toggleEmail( email.id, on ) } />
						) ) }
					</fieldset>
				</Card>
				<Card title={ __( 'Checkout', 'storewright' ) }>
					<ToggleControl label={ __( 'Offer cash on delivery', 'storewright' ) } help={ __( 'Only for goods that ship, never for downloads.', 'storewright' ) } checked={ draft.checkout.cod } onChange={ set( 'checkout', 'cod' ) } />
					{ draft.checkout.cod && (
						<TextControl
							__next40pxDefaultSize
							className="sw-settings__limit"
							type="number"
							min={ 0 }
							step={ 100 }
							label={ __( 'Cash on delivery up to', 'storewright' ) }
							help={ __( 'Orders above this total have to be paid online.', 'storewright' ) }
							value={ String( draft.checkout.codLimit ) }
							onChange={ ( value ) => set( 'checkout', 'codLimit' )( Number( value ) ) }
						/>
					) }
					<ToggleControl label={ __( 'Let people check out without an account', 'storewright' ) } checked={ draft.checkout.guestCheckout } onChange={ set( 'checkout', 'guestCheckout' ) } />
					<ToggleControl label={ __( 'Offer to create an account at checkout', 'storewright' ) } checked={ draft.checkout.accountAtCheckout } onChange={ set( 'checkout', 'accountAtCheckout' ) } />
					<TextControl
						__next40pxDefaultSize
						className="sw-settings__days"
						type="number"
						min={ 0 }
						max={ 90 }
						label={ __( 'Returns accepted for', 'storewright' ) }
						help={ __( 'Days after an order is completed. 0 turns returns off.', 'storewright' ) }
						value={ String( draft.checkout.returnDays ) }
						onChange={ ( value ) => set( 'checkout', 'returnDays' )( Number( value ) ) }
					/>
				</Card>
				<footer className="sw-settings__save">
					{ notice && (
						<Notice status={ notice.status } onRemove={ () => setNotice( null ) }>
							{ notice.text }
						</Notice>
					) }
					<Button variant="primary" type="submit" isBusy={ busy } disabled={ busy || ! dirty } accessibleWhenDisabled>
						{ __( 'Save settings', 'storewright' ) }
					</Button>
				</footer>
			</form>
		</Screen>
	);
};
