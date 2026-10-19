import { Button, CheckboxControl, Notice, SelectControl, Spinner, TextareaControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { adminLink } from '../../lib/data';
import { money, timeAgo } from '../../lib/format';
import { addressLines, personName } from '../../lib/wc';
import { addNote, loadNotes, loadOrder, setStatus } from './api';
import { Block } from './block';
import { NEXT_STEPS, statusLabel, statusTone } from './labels';
import { Shipments } from './shipments';

function Notes( { orderId } ) {
	const [ notes, setNotes ] = useState( null );
	const [ text, setText ] = useState( '' );
	const [ toCustomer, setToCustomer ] = useState( false );
	const [ busy, setBusy ] = useState( false );

	useEffect( () => {
		let current = true;
		setNotes( null );
		loadNotes( orderId ).then( ( loaded ) => current && setNotes( loaded ) );
		return () => {
			current = false;
		};
	}, [ orderId ] );

	const submit = ( event ) => {
		event.preventDefault();
		if ( text.trim() === '' ) {
			return;
		}
		setBusy( true );
		addNote( orderId, text.trim(), toCustomer )
			.then( ( note ) => {
				setNotes( [ note, ...( notes ?? [] ) ] );
				setText( '' );
				setToCustomer( false );
			} )
			.finally( () => setBusy( false ) );
	};

	return (
		<Block title={ __( 'Notes', 'storewright' ) }>
			<form className="sw-order__note-form" onSubmit={ submit }>
				<TextareaControl label={ __( 'Add a note', 'storewright' ) } hideLabelFromVision placeholder={ __( 'Add a note', 'storewright' ) } rows={ 2 } value={ text } onChange={ setText } />
				<div className="sw-order__note-actions">
					<CheckboxControl label={ __( 'Email it to the customer', 'storewright' ) } checked={ toCustomer } onChange={ setToCustomer } />
					<Button variant="secondary" size="compact" type="submit" isBusy={ busy } disabled={ busy || text.trim() === '' } accessibleWhenDisabled>
						{ __( 'Add note', 'storewright' ) }
					</Button>
				</div>
			</form>
			{ notes === null && <Spinner /> }
			{ notes && notes.length === 0 && <p className="sw-order__muted">{ __( 'No notes yet.', 'storewright' ) }</p> }
			<ol className="sw-order__notes">
				{ ( notes ?? [] ).map( ( note ) => (
					<li key={ note.id } className={ note.customer_note ? 'is-customer' : '' }>
						<div dangerouslySetInnerHTML={ { __html: note.note } } />
						<small>
							{ note.customer_note ? __( 'Sent to the customer', 'storewright' ) : note.author || __( 'System', 'storewright' ) } · { timeAgo( note.date_created_gmt + 'Z' ) }
						</small>
					</li>
				) ) }
			</ol>
		</Block>
	);
}

export function OrderPanel( { id, onClose, onChanged } ) {
	const [ order, setOrder ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ next, setNext ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	useEffect( () => {
		let current = true;
		setOrder( null );
		setError( null );
		setNext( '' );
		loadOrder( id )
			.then( ( loaded ) => current && setOrder( loaded ) )
			.catch( ( failure ) => current && setError( failure.message ) );
		return () => {
			current = false;
		};
	}, [ id ] );

	const move = ( status ) => {
		setBusy( true );
		setError( null );
		setStatus( id, status )
			.then( ( updated ) => {
				setOrder( updated );
				setNext( '' );
				onChanged( updated );
			} )
			.catch( ( failure ) => setError( failure.message ) )
			.finally( () => setBusy( false ) );
	};

	if ( ! order ) {
		return (
			<aside className="sw-panel sw-order" aria-label={ __( 'Order', 'storewright' ) }>
				{ error ? <Notice status="error" isDismissible={ false }>{ error }</Notice> : <Spinner /> }
			</aside>
		);
	}

	const customer = personName( order.billing, __( 'Guest', 'storewright' ) );
	const meta = ( key ) => order.meta_data.find( ( item ) => item.key.endsWith( key ) )?.value ?? '';
	const business = meta( 'storewright/business' );
	const gstin = meta( 'storewright/gstin' );
	const steps = NEXT_STEPS[ order.status ] ?? [];
	const primary = steps[ 0 ];

	return (
		<aside className="sw-panel sw-order" aria-label={ /* translators: %s: order number */ sprintf( __( 'Order #%s', 'storewright' ), order.number ) }>
			<header className="sw-panel__head">
				<div>
					<p className="sw-panel__eyebrow">{ timeAgo( order.date_created_gmt + 'Z' ) }</p>
					<h2 className="sw-panel__title">
						{ /* translators: %s: order number */ sprintf( __( 'Order #%s', 'storewright' ), order.number ) }
						<span className={ `sw-pill ${ statusTone( order.status ) }` }>{ statusLabel( order.status ) }</span>
					</h2>
				</div>
				<Button variant="tertiary" size="compact" onClick={ onClose }>
					{ __( 'Close', 'storewright' ) }
				</Button>
			</header>
			{ error && (
				<Notice status="error" onRemove={ () => setError( null ) }>
					{ error }
				</Notice>
			) }
			<div className="sw-order__actions">
				{ primary && (
					<Button variant="primary" isBusy={ busy } disabled={ busy } onClick={ () => move( primary ) }>
						{ /* translators: %s: order status */ sprintf( __( 'Mark as %s', 'storewright' ), statusLabel( primary ).toLowerCase() ) }
					</Button>
				) }
				{ steps.length > 1 && (
					<SelectControl
						label={ __( 'Move to', 'storewright' ) }
						hideLabelFromVision
						value={ next }
						options={ [ { label: __( 'Move to…', 'storewright' ), value: '' }, ...steps.map( ( step ) => ( { label: statusLabel( step ), value: step } ) ) ] }
						onChange={ ( value ) => {
							setNext( value );
							if ( value ) {
								move( value );
							}
						} }
					/>
				) }
				<a className="sw-link sw-order__advanced" href={ adminLink( `admin.php?page=wc-orders&action=edit&id=${ order.id }` ) }>
					{ __( 'Refund or edit in advanced', 'storewright' ) }
				</a>
			</div>
			<Block title={ __( 'Items', 'storewright' ) }>
				<ul className="sw-order__items">
					{ order.line_items.map( ( item ) => (
						<li key={ item.id }>
							<span className="sw-order__thumb">{ item.image?.src && <img src={ item.image.src } alt="" /> }</span>
							<span>
								<strong>{ item.name }</strong>
								<small>
									{ /* translators: 1: quantity, 2: unit price */ sprintf( __( '%1$s × %2$s', 'storewright' ), item.quantity, money( item.price, 2 ) ) }
									{ item.sku && ` · ${ item.sku }` }
								</small>
							</span>
							<span className="is-number">{ money( item.total, 2 ) }</span>
						</li>
					) ) }
				</ul>
				<dl className="sw-order__totals">
					<dt>{ __( 'Items', 'storewright' ) }</dt>
					<dd>{ money( order.line_items.reduce( ( sum, item ) => sum + Number( item.total ), 0 ), 2 ) }</dd>
					{ Number( order.discount_total ) > 0 && (
						<>
							<dt>{ __( 'Discount', 'storewright' ) }</dt>
							<dd>-{ money( order.discount_total, 2 ) }</dd>
						</>
					) }
					<dt>{ order.shipping_lines[ 0 ]?.method_title || __( 'Delivery', 'storewright' ) }</dt>
					<dd>{ money( order.shipping_total, 2 ) }</dd>
					{ Number( order.total_tax ) > 0 && (
						<>
							<dt>{ __( 'Tax', 'storewright' ) }</dt>
							<dd>{ money( order.total_tax, 2 ) }</dd>
						</>
					) }
					<dt className="is-total">{ __( 'Total', 'storewright' ) }</dt>
					<dd className="is-total">{ money( order.total, 2 ) }</dd>
				</dl>
			</Block>
			<Shipments order={ order } />
			<div className="sw-order__two">
				<Block title={ __( 'Customer', 'storewright' ) }>
					<p className="sw-order__strong">{ customer }</p>
					{ business && <p>{ business }</p> }
					{ gstin && <p className="sw-order__gstin">{ /* translators: %s: GST number */ sprintf( __( 'GSTIN %s', 'storewright' ), gstin ) }</p> }
					{ order.billing.email && <p><a className="sw-link" href={ `mailto:${ order.billing.email }` }>{ order.billing.email }</a></p> }
					{ order.billing.phone && <p><a className="sw-link" href={ `tel:${ order.billing.phone }` }>{ order.billing.phone }</a></p> }
					{ order.customer_note && <p className="sw-order__customer-note">{ order.customer_note }</p> }
				</Block>
				<Block title={ __( 'Deliver to', 'storewright' ) }>
					<p className="sw-order__strong">{ personName( order.shipping, customer ) }</p>
					{ addressLines( order.shipping.address_1 ? order.shipping : order.billing ).map( ( line ) => (
						<p key={ line }>{ line }</p>
					) ) }
				</Block>
			</div>
			<Block title={ __( 'Payment', 'storewright' ) }>
				<p className="sw-order__strong">{ order.payment_method_title || __( 'Not paid yet', 'storewright' ) }</p>
				{ order.date_paid_gmt ? (
					<p>{ /* translators: %s: relative time */ sprintf( __( 'Paid %s', 'storewright' ), timeAgo( order.date_paid_gmt + 'Z' ) ) }</p>
				) : (
					<p>{ __( 'Awaiting payment', 'storewright' ) }</p>
				) }
				{ order.transaction_id && <p className="sw-order__muted">{ order.transaction_id }</p> }
				{ order.storewright_invoice && (
					<p>
						<a className="sw-link" href={ order.storewright_invoice.url } target="_blank" rel="noreferrer">
							{ /* translators: %s: invoice number */ sprintf( __( 'Invoice %s', 'storewright' ), order.storewright_invoice.number ) }
						</a>
					</p>
				) }
			</Block>
			<Notes orderId={ order.id } />
		</aside>
	);
}
