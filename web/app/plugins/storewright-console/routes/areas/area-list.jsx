import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { methodsText, pinsText, shapeText, whereText } from './describe';

function AreaItem( { area, colour, first, last, highlighted, busy, onEdit, onMove } ) {
	const fixed = area.id === 0;
	return (
		<li className={ highlighted ? 'sw-areas__item is-highlighted' : 'sw-areas__item' } aria-label={ area.name }>
			<span className="sw-areas__position" style={ { background: colour } }>
				{ area.position }
			</span>
			<div className="sw-areas__body">
				<h2 className="sw-areas__name">{ area.name }</h2>
				<dl className="sw-areas__facts">
					<dt>{ __( 'Where', 'storewright' ) }</dt>
					<dd>{ whereText( area ) }</dd>
					{ ! fixed && (
						<>
							<dt>{ __( 'PIN codes', 'storewright' ) }</dt>
							<dd>{ pinsText( area ) }</dd>
							<dt>{ __( 'Map', 'storewright' ) }</dt>
							<dd>{ shapeText( area.shape ) }</dd>
						</>
					) }
					<dt>{ __( 'Delivery', 'storewright' ) }</dt>
					<dd>{ methodsText( area ) }</dd>
				</dl>
				<div className="sw-areas__buttons">
					<Button variant="secondary" size="compact" onClick={ () => onEdit( area ) }>
						{ __( 'Edit', 'storewright' ) }
					</Button>
					{ ! fixed && (
						<>
							<Button variant="tertiary" size="compact" disabled={ first || busy } accessibleWhenDisabled onClick={ () => onMove( area.id, -1 ) }>
								{ __( 'Move up', 'storewright' ) }
							</Button>
							<Button variant="tertiary" size="compact" disabled={ last || busy } accessibleWhenDisabled onClick={ () => onMove( area.id, 1 ) }>
								{ __( 'Move down', 'storewright' ) }
							</Button>
						</>
					) }
				</div>
			</div>
		</li>
	);
}

export function AreaList( { areas, colours, highlight, busy, tester, onAdd, onEdit, onMove } ) {
	const movable = areas.filter( ( area ) => area.id !== 0 );
	return (
		<div className="sw-areas__list">
			<div className="sw-areas__head">
				<h1 className="sw-areas__title">{ __( 'Delivery areas', 'storewright' ) }</h1>
				<Button variant="primary" onClick={ onAdd }>
					{ __( 'Add an area', 'storewright' ) }
				</Button>
			</div>
			<p className="sw-areas__hint">
				{ __( 'Addresses are checked against the areas from the top. The first area that covers an address and offers a delivery method decides what the shopper can choose.', 'storewright' ) }
			</p>
			{ tester }
			<ol className="sw-areas__items">
				{ areas.map( ( area, index ) => (
					<AreaItem
						key={ area.id }
						area={ area }
						colour={ colours[ index ] }
						first={ area.id === movable[ 0 ]?.id }
						last={ area.id === movable[ movable.length - 1 ]?.id }
						highlighted={ area.id === highlight }
						busy={ busy }
						onEdit={ onEdit }
						onMove={ onMove }
					/>
				) ) }
			</ol>
		</div>
	);
}
