import { Button, CheckboxControl, Notice, SelectControl, Spinner, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { consoleData } from '../../lib/data';
import { timeAgo } from '../../lib/format';
import { createFulfilment, deleteFulfilment, loadFulfilments } from './api';
import { Block } from './block';

const OTHER = 'other';
const carriers = consoleData.carriers ?? [];

const meta = ( fulfilment, key ) => fulfilment.meta_data.find( ( item ) => item.key === key )?.value ?? '';

const carrierName = ( fulfilment ) => {
	const key = meta( fulfilment, '_shipment_provider' );
	if ( ! key || key === OTHER ) {
		return meta( fulfilment, '_provider_name' );
	}
	return carriers.find( ( carrier ) => carrier.key === key )?.name ?? key;
};

const shippedQuantities = ( fulfilments ) => {
	const shipped = {};
	fulfilments.forEach( ( fulfilment ) =>
		( meta( fulfilment, '_items' ) || [] ).forEach( ( item ) => {
			shipped[ item.item_id ] = ( shipped[ item.item_id ] ?? 0 ) + Number( item.qty );
		} )
	);
	return shipped;
};

function Shipment( { fulfilment, order, onRemove } ) {
	const [ busy, setBusy ] = useState( false );
	const name = carrierName( fulfilment );
	const number = meta( fulfilment, '_tracking_number' );
	const link = meta( fulfilment, '_tracking_url' );
	const online = ! name && ! number && ! link;
	const items = ( meta( fulfilment, '_items' ) || [] ).map( ( item ) => {
		const line = order.line_items.find( ( row ) => row.id === Number( item.item_id ) );
		return /* translators: 1: quantity, 2: product name */ sprintf( __( '%1$s × %2$s', 'storewright' ), item.qty, line?.name ?? '' );
	} );

	return (
		<li className="sw-shipment">
			<div>
				<p className="sw-order__strong">{ online ? __( 'Delivered online', 'storewright' ) : name || __( 'Sent', 'storewright' ) }</p>
				{ number && <p>{ link ? <a className="sw-link" href={ link } target="_blank" rel="noreferrer">{ number }</a> : number }</p> }
				<p className="sw-order__muted">{ items.join( ', ' ) }</p>
				{ fulfilment.date_updated && <small className="sw-order__muted">{ timeAgo( fulfilment.date_updated ) }</small> }
			</div>
			{ ! online && (
				<Button
					variant="tertiary"
					size="compact"
					isDestructive
					isBusy={ busy }
					disabled={ busy }
					onClick={ () => {
						setBusy( true );
						onRemove( fulfilment ).finally( () => setBusy( false ) );
					} }
				>
					{ __( 'Remove', 'storewright' ) }
				</Button>
			) }
		</li>
	);
}

function ShipForm( { order, remaining, onShipped, onCancel } ) {
	const [ quantities, setQuantities ] = useState( Object.fromEntries( remaining.map( ( row ) => [ row.item.id, row.left ] ) ) );
	const [ carrier, setCarrier ] = useState( carriers[ 0 ]?.key ?? OTHER );
	const [ carrierLabel, setCarrierLabel ] = useState( '' );
	const [ number, setNumber ] = useState( '' );
	const [ link, setLink ] = useState( '' );
	const [ notify, setNotify ] = useState( true );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );

	const items = remaining.map( ( row ) => ( { item_id: row.item.id, qty: Number( quantities[ row.item.id ] ) || 0 } ) ).filter( ( item ) => item.qty > 0 );

	const submit = ( event ) => {
		event.preventDefault();
		setBusy( true );
		setError( null );
		const metaData = [
			{ key: '_items', value: items },
			{ key: '_shipment_provider', value: carrier },
			{ key: '_tracking_number', value: number.trim() },
			{ key: '_tracking_url', value: link.trim() },
		];
		if ( carrier === OTHER ) {
			metaData.push( { key: '_provider_name', value: carrierLabel.trim() } );
		}
		createFulfilment( order.id, { status: 'fulfilled', is_fulfilled: true, notify_customer: notify, meta_data: metaData } )
			.then( onShipped )
			.catch( ( failure ) => setError( failure.message ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<form className="sw-ship" onSubmit={ submit } aria-label={ __( 'Ship items', 'storewright' ) }>
			{ error && (
				<Notice status="error" onRemove={ () => setError( null ) }>
					{ error }
				</Notice>
			) }
			<ul className="sw-ship__items">
				{ remaining.map( ( row ) => (
					<li key={ row.item.id }>
						<span>{ row.item.name }</span>
						<TextControl
							__next40pxDefaultSize
							type="number"
							min={ 0 }
							max={ row.left }
							label={ /* translators: %s: product name */ sprintf( __( 'Quantity of %s', 'storewright' ), row.item.name ) }
							hideLabelFromVision
							value={ String( quantities[ row.item.id ] ) }
							onChange={ ( value ) => setQuantities( { ...quantities, [ row.item.id ]: Math.max( 0, Math.min( row.left, Number( value ) ) ) } ) }
						/>
						<small>{ /* translators: %s: quantity still to ship */ sprintf( __( 'of %s', 'storewright' ), row.left ) }</small>
					</li>
				) ) }
			</ul>
			<SelectControl __next40pxDefaultSize label={ __( 'Carrier', 'storewright' ) } value={ carrier } options={ carriers.map( ( item ) => ( { label: item.name, value: item.key } ) ) } onChange={ setCarrier } />
			{ carrier === OTHER && <TextControl __next40pxDefaultSize label={ __( 'Carrier name', 'storewright' ) } value={ carrierLabel } onChange={ setCarrierLabel } required /> }
			<TextControl __next40pxDefaultSize label={ __( 'Tracking number', 'storewright' ) } value={ number } onChange={ setNumber } />
			<TextControl __next40pxDefaultSize type="url" label={ __( 'Tracking link', 'storewright' ) } help={ __( 'Paste the link from the carrier, if there is one.', 'storewright' ) } value={ link } onChange={ setLink } />
			<CheckboxControl label={ __( 'Email the customer that it is on its way', 'storewright' ) } checked={ notify } onChange={ setNotify } />
			<div className="sw-ship__actions">
				<Button variant="primary" type="submit" isBusy={ busy } disabled={ busy || items.length === 0 } accessibleWhenDisabled>
					{ __( 'Mark as shipped', 'storewright' ) }
				</Button>
				<Button variant="tertiary" onClick={ onCancel }>
					{ __( 'Cancel', 'storewright' ) }
				</Button>
			</div>
		</form>
	);
}

export function Shipments( { order } ) {
	const [ list, setList ] = useState( null );
	const [ open, setOpen ] = useState( false );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		let current = true;
		setList( null );
		setOpen( false );
		loadFulfilments( order.id )
			.then( ( loaded ) => current && setList( loaded ) )
			.catch( ( failure ) => current && setError( failure.message ) );
		return () => {
			current = false;
		};
	}, [ order.id ] );

	if ( ! list ) {
		return <Block title={ __( 'Shipments', 'storewright' ) }>{ error ? <Notice status="error" isDismissible={ false }>{ error }</Notice> : <Spinner /> }</Block>;
	}

	const shipped = shippedQuantities( list );
	const remaining = order.line_items.map( ( item ) => ( { item, left: item.quantity - ( shipped[ item.id ] ?? 0 ) } ) ).filter( ( row ) => row.left > 0 );
	const closed = [ 'cancelled', 'refunded', 'failed', 'pending' ].includes( order.status );

	let summary = __( 'Nothing has been sent yet.', 'storewright' );
	if ( remaining.length === 0 ) {
		summary = __( 'Everything has been sent.', 'storewright' );
	} else if ( list.length > 0 ) {
		summary = __( 'Part of the order has been sent.', 'storewright' );
	}

	return (
		<Block title={ __( 'Shipments', 'storewright' ) }>
			<p className={ remaining.length === 0 ? 'sw-ship__summary is-done' : 'sw-ship__summary' }>{ summary }</p>
			{ error && (
				<Notice status="error" onRemove={ () => setError( null ) }>
					{ error }
				</Notice>
			) }
			{ list.length > 0 && (
				<ol className="sw-shipments">
					{ list.map( ( fulfilment ) => (
						<Shipment
							key={ fulfilment.id }
							fulfilment={ fulfilment }
							order={ order }
							onRemove={ ( target ) =>
								deleteFulfilment( order.id, target.id )
									.then( () => setList( list.filter( ( item ) => item.id !== target.id ) ) )
									.catch( ( failure ) => setError( failure.message ) )
							}
						/>
					) ) }
				</ol>
			) }
			{ remaining.length > 0 && ! closed && ! open && (
				<Button variant="secondary" onClick={ () => setOpen( true ) }>
					{ __( 'Ship items', 'storewright' ) }
				</Button>
			) }
			{ open && (
				<ShipForm
					order={ order }
					remaining={ remaining }
					onCancel={ () => setOpen( false ) }
					onShipped={ ( created ) => {
						setList( [ ...list, created ] );
						setOpen( false );
					} }
				/>
			) }
		</Block>
	);
}
