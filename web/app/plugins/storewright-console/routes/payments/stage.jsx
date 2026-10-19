import { Button, Notice, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { consoleLink } from '../../lib/data';
import { count, money, timeAgo } from '../../lib/format';
import { Card, Empty, Screen, Segmented } from '../../lib/screen';
import { checkOrder, loadEvents, loadHealth, loadUnsettled } from './api';
import './style.scss';

const NAMES = { razorpay: 'Razorpay', stripe: 'Stripe' };

const MODES = {
	live: [ __( 'Live', 'storewright' ), 'is-good' ],
	sandbox: [ __( 'Sandbox', 'storewright' ), 'is-quiet' ],
	refused: [ __( 'Live keys refused here', 'storewright' ), 'is-bad' ],
	off: [ __( 'Not set up', 'storewright' ), 'is-bad' ],
};

const EVENT_STATUS = {
	processed: [ __( 'Processed', 'storewright' ), 'is-good' ],
	received: [ __( 'Waiting', 'storewright' ), 'is-quiet' ],
	failed: [ __( 'Failed', 'storewright' ), 'is-bad' ],
};

function Copy( { text } ) {
	const [ done, setDone ] = useState( false );
	return (
		<Button
			variant="tertiary"
			size="compact"
			onClick={ () =>
				navigator.clipboard?.writeText( text ).then( () => {
					setDone( true );
					setTimeout( () => setDone( false ), 1500 );
				} )
			}
		>
			{ done ? __( 'Copied', 'storewright' ) : __( 'Copy', 'storewright' ) }
		</Button>
	);
}

function Gateway( { gateway } ) {
	const [ label, tone ] = MODES[ gateway.mode ] ?? MODES.off;
	const healthy = gateway.failed === 0 && gateway.stuck === 0;
	return (
		<Card title={ NAMES[ gateway.gateway ] } className="sw-gateway" action={ <span className={ `sw-pill ${ tone }` }>{ label }</span> }>
			<dl className="sw-gateway__facts">
				<dt>{ __( 'Last webhook', 'storewright' ) }</dt>
				<dd>{ gateway.last ? timeAgo( gateway.last ) : __( 'None yet', 'storewright' ) }</dd>
				<dt>{ __( 'Last 24 hours', 'storewright' ) }</dt>
				<dd>{ /* translators: %s: number of events */ sprintf( __( '%s events', 'storewright' ), count( gateway.day ) ) }</dd>
				<dt>{ __( 'Problems', 'storewright' ) }</dt>
				<dd className={ healthy ? '' : 'is-bad' }>
					{ healthy ? __( 'None', 'storewright' ) : /* translators: 1: failed events, 2: stuck events */ sprintf( __( '%1$s failed, %2$s waiting', 'storewright' ), count( gateway.failed ), count( gateway.stuck ) ) }
				</dd>
			</dl>
			<div className="sw-gateway__webhook">
				<span>{ __( 'Webhook address', 'storewright' ) }</span>
				<code>{ gateway.webhook }</code>
				<Copy text={ gateway.webhook } />
			</div>
		</Card>
	);
}

function Unsettled() {
	const [ orders, setOrders ] = useState( null );
	const [ answers, setAnswers ] = useState( {} );
	const [ busy, setBusy ] = useState( null );

	useEffect( () => {
		loadUnsettled().then( setOrders );
	}, [] );

	const check = ( order ) => {
		setBusy( order.id );
		checkOrder( order.id )
			.then( ( answer ) => {
				setAnswers( { ...answers, [ order.id ]: answer } );
				if ( answer.status === 'paid' ) {
					setOrders( orders.filter( ( item ) => item.id !== order.id ) );
				}
			} )
			.catch( ( failure ) => setAnswers( { ...answers, [ order.id ]: { status: 'unknown', message: failure.message } } ) )
			.finally( () => setBusy( null ) );
	};

	const settled = Object.entries( answers ).filter( ( [ , answer ] ) => answer.status === 'paid' );

	return (
		<Card title={ __( 'Waiting for payment', 'storewright' ) } padded={ false }>
			<p className="sw-payments__lead">{ __( 'Online orders still unpaid after 15 minutes, from the last 7 days. Ask the gateway whether the money arrived; a paid order is recorded straight away.', 'storewright' ) }</p>
			{ settled.map( ( [ id, answer ] ) => (
				<Notice key={ id } status="success" isDismissible={ false }>
					{ /* translators: 1: order number, 2: gateway message */ sprintf( __( 'Order #%1$s: %2$s', 'storewright' ), answer.order.number, answer.message ) }
				</Notice>
			) ) }
			{ ! orders && <Spinner /> }
			{ orders && orders.length === 0 && <Empty>{ __( 'Every online order is settled.', 'storewright' ) }</Empty> }
			{ orders && orders.length > 0 && (
				<table className="sw-table sw-payments__table">
					<thead>
						<tr>
							<th scope="col">{ __( 'Order', 'storewright' ) }</th>
							<th scope="col">{ __( 'Customer', 'storewright' ) }</th>
							<th scope="col">{ __( 'Gateway', 'storewright' ) }</th>
							<th scope="col" className="is-number">{ __( 'Total', 'storewright' ) }</th>
							<th scope="col"><span className="screen-reader-text">{ __( 'Check', 'storewright' ) }</span></th>
						</tr>
					</thead>
					<tbody>
						{ orders.map( ( order ) => (
							<tr key={ order.id }>
								<td>
									<a className="sw-link" href={ consoleLink( '/orders', `order=${ order.id }` ) }>#{ order.number }</a>
									<small>{ timeAgo( order.created ) }</small>
								</td>
								<td>{ order.customer }</td>
								<td>{ NAMES[ order.gateway ] ?? order.title }</td>
								<td className="is-number">{ money( order.total, 2 ) }</td>
								<td>
									<div className="sw-payments__check">
										<Button variant="secondary" size="compact" isBusy={ busy === order.id } disabled={ busy !== null } accessibleWhenDisabled onClick={ () => check( order ) }>
											{ /* translators: %s: gateway name */ sprintf( __( 'Check with %s', 'storewright' ), NAMES[ order.gateway ] ?? order.title ) }
										</Button>
										{ answers[ order.id ] && <small role="status">{ answers[ order.id ].message }</small> }
									</div>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</Card>
	);
}

function Events() {
	const [ filter, setFilter ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ result, setResult ] = useState( null );

	useEffect( () => {
		let current = true;
		setResult( null );
		loadEvents( { status: filter, page } ).then( ( loaded ) => current && setResult( loaded ) );
		return () => {
			current = false;
		};
	}, [ filter, page ] );

	const filters = [
		{ key: '', label: __( 'All', 'storewright' ) },
		{ key: 'failed', label: __( 'Failed', 'storewright' ) },
		{ key: 'received', label: __( 'Waiting', 'storewright' ) },
	];

	return (
		<Card
			title={ __( 'Webhook log', 'storewright' ) }
			padded={ false }
			action={
				<Segmented
					label={ __( 'Webhook status', 'storewright' ) }
					value={ filter }
					options={ filters }
					onChange={ ( next ) => {
						setFilter( next );
						setPage( 1 );
					} }
				/>
			}
		>
			{ ! result && <Spinner /> }
			{ result && result.items.length === 0 && <Empty>{ __( 'No webhook events here.', 'storewright' ) }</Empty> }
			{ result && result.items.length > 0 && (
				<>
					<table className="sw-table">
						<thead>
							<tr>
								<th scope="col">{ __( 'Received', 'storewright' ) }</th>
								<th scope="col">{ __( 'Gateway', 'storewright' ) }</th>
								<th scope="col">{ __( 'Event', 'storewright' ) }</th>
								<th scope="col">{ __( 'Status', 'storewright' ) }</th>
								<th scope="col" className="is-number">{ __( 'Deliveries', 'storewright' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ result.items.map( ( event ) => {
								const [ label, tone ] = EVENT_STATUS[ event.status ] ?? [ event.status, '' ];
								return (
									<tr key={ `${ event.gateway }-${ event.id }` }>
										<td>{ timeAgo( event.received ) }</td>
										<td>{ NAMES[ event.gateway ] ?? event.gateway }</td>
										<td>
											{ event.type }
											<small className="sw-payments__id">{ event.id }</small>
										</td>
										<td>
											<span className={ `sw-pill ${ tone }` }>{ label }</span>
										</td>
										<td className="is-number">{ event.deliveries }</td>
									</tr>
								);
							} ) }
						</tbody>
					</table>
					<footer className="sw-payments__pager">
						<span>{ /* translators: 1: page, 2: pages */ sprintf( __( 'Page %1$s of %2$s', 'storewright' ), page, result.pages ) }</span>
						<Button variant="tertiary" size="compact" disabled={ page <= 1 } accessibleWhenDisabled onClick={ () => setPage( page - 1 ) }>
							{ __( 'Previous', 'storewright' ) }
						</Button>
						<Button variant="tertiary" size="compact" disabled={ page >= result.pages } accessibleWhenDisabled onClick={ () => setPage( page + 1 ) }>
							{ __( 'Next', 'storewright' ) }
						</Button>
					</footer>
				</>
			) }
		</Card>
	);
}

export const stage = () => {
	const [ health, setHealth ] = useState( null );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		loadHealth()
			.then( setHealth )
			.catch( ( failure ) => setError( failure.message ) );
	}, [] );

	return (
		<Screen title={ __( 'Payments', 'storewright' ) } lead={ __( 'Gateway keys come from the server environment. This screen shows whether each gateway is working and which payments need a look.', 'storewright' ) } wide>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ ! health && ! error && <Spinner /> }
			{ health && (
				<div className="sw-payments__gateways">
					{ health.gateways.map( ( gateway ) => (
						<Gateway key={ gateway.gateway } gateway={ gateway } />
					) ) }
					<Card title={ __( 'Cash on delivery', 'storewright' ) } className="sw-gateway" action={ <span className={ `sw-pill ${ health.cashOnDelivery ? 'is-good' : 'is-quiet' }` }>{ health.cashOnDelivery ? __( 'On', 'storewright' ) : __( 'Off', 'storewright' ) }</span> }>
						<p className="sw-payments__note">{ __( 'Offered for goods that ship. Orders stay unpaid until you mark them completed after delivery.', 'storewright' ) }</p>
					</Card>
				</div>
			) }
			<Unsettled />
			<Events />
		</Screen>
	);
};
