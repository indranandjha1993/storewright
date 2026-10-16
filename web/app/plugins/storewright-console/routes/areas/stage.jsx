import { Notice, Spinner } from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { createArea, deleteArea, loadAreas, reorderAreas, testAddress, updateArea } from './api';
import { AreaEditor } from './area-editor';
import { AreaList } from './area-list';
import { AreaMap } from './area-map';
import { AreaTest } from './area-test';
import { draftOf, newDraft, payloadOf, problemWith } from './draft';
import { boundsOf, viewOf } from './geo';
import './style.scss';

const COLOURS = [ '#0f5c4d', '#b4541a', '#3858e9', '#8a2c8a', '#5b6b00', '#a0352c' ];

export const stage = () => {
	const [ areas, setAreas ] = useState( null );
	const [ places, setPlaces ] = useState( [] );
	const [ draft, setDraft ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ answer, setAnswer ] = useState( null );
	const [ marker, setMarker ] = useState( null );
	const [ view, setView ] = useState( null );

	useEffect( () => {
		loadAreas()
			.then( ( data ) => {
				setAreas( data.areas );
				setPlaces( data.places );
				setView( viewOf( data.areas.map( ( area ) => area.shape ) ) );
			} )
			.catch( ( failure ) => setError( failure.message ) );
	}, [] );

	const colours = useMemo( () => ( areas ?? [] ).map( ( area, index ) => COLOURS[ index % COLOURS.length ] ), [ areas ] );

	const shapes = useMemo(
		() =>
			( areas ?? [] )
				.map( ( area, index ) => ( { id: area.id, shape: area.shape, colour: colours[ index ], faint: draft !== null } ) )
				.filter( ( item ) => item.shape && item.id !== draft?.id ),
		[ areas, colours, draft ]
	);

	const change = ( request, after ) => {
		setBusy( true );
		setError( null );
		return request
			.then( ( data ) => {
				setAreas( data.areas );
				after?.();
			} )
			.catch( ( failure ) => setError( failure.message ) )
			.finally( () => setBusy( false ) );
	};

	const close = () => {
		setDraft( null );
		setError( null );
	};

	const open = ( next, shape ) => {
		setDraft( next );
		setError( null );
		setAnswer( null );
		setMarker( null );
		if ( shape ) {
			setView( { bounds: boundsOf( [ shape ] ) } );
		}
	};

	const save = () => {
		const problem = problemWith( draft );
		if ( problem ) {
			setError( problem );
			return;
		}
		const payload = payloadOf( draft );
		change( draft.id === null ? createArea( payload ) : updateArea( draft.id, payload ), close );
	};

	const move = ( id, step ) => {
		const ids = areas.filter( ( area ) => area.id !== 0 ).map( ( area ) => area.id );
		const from = ids.indexOf( id );
		[ ids[ from ], ids[ from + step ] ] = [ ids[ from + step ], ids[ from ] ];
		change( reorderAreas( ids ) );
	};

	const check = ( data, point ) =>
		testAddress( data )
			.then( ( result ) => {
				setAnswer( result );
				setMarker( point ?? result.point );
			} )
			.catch( ( failure ) => setError( failure.message ) );

	const find = ( pin ) =>
		testAddress( { pin } )
			.then( ( result ) => {
				if ( ! result.point ) {
					return result.message;
				}
				setView( { point: result.point, zoom: 13 } );
				/* translators: 1: PIN code, 2: district */
				return sprintf( __( 'Showing %1$s, %2$s.', 'storewright' ), result.pin, result.district );
			} )
			.catch( ( failure ) => failure.message );

	const clickMap = ( point ) => {
		if ( draft === null ) {
			check( { lat: point[ 0 ], lng: point[ 1 ] }, point );
		} else if ( draft.shapeKind === 'radius' ) {
			setDraft( { ...draft, centre: point } );
		} else if ( draft.shapeKind === 'polygon' ) {
			setDraft( { ...draft, corners: [ ...draft.corners, point ] } );
		}
	};

	const moveCorner = ( index, point ) => setDraft( { ...draft, corners: draft.corners.map( ( corner, at ) => ( at === index ? point : corner ) ) } );

	if ( areas === null ) {
		return <div className="sw-areas__loading">{ error ? <Notice status="error" isDismissible={ false }>{ error }</Notice> : <Spinner /> }</div>;
	}

	const saved = areas.find( ( area ) => area.id === draft?.id );

	return (
		<div className="sw-areas">
			<div className="sw-areas__panel">
				{ draft ? (
					<AreaEditor
						draft={ draft }
						places={ places }
						savedName={ saved?.name ?? '' }
						error={ error }
						busy={ busy }
						onChange={ setDraft }
						onFind={ find }
						onSave={ save }
						onCancel={ close }
						onDelete={ () => change( deleteArea( draft.id ), close ) }
					/>
				) : (
					<>
						{ error && (
							<Notice status="error" onRemove={ () => setError( null ) }>
								{ error }
							</Notice>
						) }
						<AreaList
							areas={ areas }
							colours={ colours }
							highlight={ answer?.areaId }
							busy={ busy }
							tester={ <AreaTest answer={ answer } onCheck={ ( data ) => check( data ) } /> }
							onAdd={ () => open( newDraft( places ) ) }
							onEdit={ ( area ) => open( draftOf( area ), area.shape ) }
							onMove={ move }
						/>
					</>
				) }
			</div>
			<AreaMap shapes={ shapes } draft={ draft } marker={ marker } view={ view } onClick={ clickMap } onMoveCorner={ moveCorner } />
		</div>
	);
};
