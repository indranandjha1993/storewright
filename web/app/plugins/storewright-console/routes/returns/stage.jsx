import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { consoleLink } from '../../lib/data';
import { money, timeAgo } from '../../lib/format';
import { Card, Empty, Screen } from '../../lib/screen';
import './style.scss';

const STATES = {
	requested: [ __( 'New', 'storewright' ), 'is-good' ],
	approved: [ __( 'Pickup arranged', 'storewright' ), 'is-quiet' ],
	received: [ __( 'Back with us', 'storewright' ), 'is-quiet' ],
	refunded: [ __( 'Refunded', 'storewright' ), '' ],
	declined: [ __( 'Declined', 'storewright' ), 'is-bad' ],
};

const ACTIONS = {
	approved: __( 'Approve and arrange pickup', 'storewright' ),
	received: __( 'Mark as received', 'storewright' ),
	refunded: __( 'Refund', 'storewright' ),
	declined: __( 'Decline', 'storewright' ),
};

function Row( { item, onMoved } ) {
	const [ note, setNote ] = useState( '' );
	const [ busy, setBusy ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ label, tone ] = STATES[ item.status ] ?? [ item.status, '' ];

	const move = ( status ) => {
		setBusy( status );
		setError( null );
		apiFetch( { path: `/storewright/v1/returns/${ item.order }/${ item.id }`, method: 'POST', data: { status, note } } )
			.then( ( moved ) => {
				setNote( '' );
				onMoved( moved );
			} )
			.catch( ( failure ) => setError( failure.message ) )
			.finally( () => setBusy( null ) );
	};

	return (
		<li className="sw-return-row">
			<div className="sw-return-row__main">
				<p className="sw-return-row__head">
					<a className="sw-link" href={ consoleLink( '/orders', `order=${ item.order }` ) }>#{ item.number }</a>
					<span>{ item.customer }</span>
					<span className={ `sw-pill ${ tone }` }>{ label }</span>
				</p>
				<p className="sw-return-row__items">{ item.summary }</p>
				<p className="sw-return-row__reason">
					{ item.reasonText }
					{ item.note && ` · ${ item.note }` }
				</p>
				<small>
					{ /* translators: 1: amount, 2: payment method, 3: relative time */ sprintf( __( '%1$s paid by %2$s · asked %3$s', 'storewright' ), money( item.amount, 2 ), item.payment || '-', timeAgo( item.created ) ) }
				</small>
				{ error && (
					<Notice status="error" onRemove={ () => setError( null ) }>
						{ error }
					</Notice>
				) }
			</div>
			{ item.next.length > 0 && (
				<div className="sw-return-row__actions">
					<TextControl __next40pxDefaultSize label={ __( 'Message to the customer', 'storewright' ) } hideLabelFromVision placeholder={ __( 'Message to the customer (optional)', 'storewright' ) } value={ note } onChange={ setNote } />
					<div className="sw-return-row__buttons">
						{ item.next.map( ( status ) => (
							<Button
								key={ status }
								variant={ status === 'declined' ? 'tertiary' : 'primary' }
								isDestructive={ status === 'declined' }
								size="compact"
								isBusy={ busy === status }
								disabled={ busy !== null }
								accessibleWhenDisabled
								onClick={ () => move( status ) }
							>
								{ ACTIONS[ status ] }
							</Button>
						) ) }
					</div>
				</div>
			) }
		</li>
	);
}

export const stage = () => {
	const [ all, setAll ] = useState( false );
	const [ rows, setRows ] = useState( null );

	useEffect( () => {
		let current = true;
		setRows( null );
		apiFetch( { path: addQueryArgs( '/storewright/v1/returns', { all: all ? 1 : undefined } ) } ).then( ( loaded ) => current && setRows( loaded ) );
		return () => {
			current = false;
		};
	}, [ all ] );

	const moved = ( item ) => setRows( rows.map( ( row ) => ( row.id === item.id ? item : row ) ) );

	return (
		<Screen
			title={ __( 'Returns', 'storewright' ) }
			lead={ __( 'Customers ask from their order page within the return window. Approve, collect, then refund: online payments go back automatically, cash on delivery is refunded by you.', 'storewright' ) }
			actions={
				<div className="sw-returns__switch" role="group" aria-label={ __( 'Which returns', 'storewright' ) }>
					<Button variant={ all ? 'secondary' : 'primary' } size="compact" aria-pressed={ ! all } onClick={ () => setAll( false ) }>
						{ __( 'Open', 'storewright' ) }
					</Button>
					<Button variant={ all ? 'primary' : 'secondary' } size="compact" aria-pressed={ all } onClick={ () => setAll( true ) }>
						{ __( 'All', 'storewright' ) }
					</Button>
				</div>
			}
		>
			<Card padded={ false }>
				{ ! rows && <Spinner /> }
				{ rows && rows.length === 0 && <Empty>{ all ? __( 'No returns yet.', 'storewright' ) : __( 'No returns waiting.', 'storewright' ) }</Empty> }
				{ rows && rows.length > 0 && (
					<ol className="sw-returns__list">
						{ rows.map( ( item ) => (
							<Row key={ item.id } item={ item } onMoved={ moved } />
						) ) }
					</ol>
				) }
			</Card>
		</Screen>
	);
};
