import { Notice, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { count, timeAgo } from '../../lib/format';
import { Card, Empty, Screen } from '../../lib/screen';
import { dismissJob, loadJobs, retryJob } from './api';
import './style.scss';

function runnerState( runner ) {
	if ( ! runner.last ) {
		return [ __( 'Has not run yet', 'storewright' ), 'is-quiet', __( 'The runner has not reported in. Check that the cron container is up.', 'storewright' ) ];
	}
	if ( runner.stale ) {
		/* translators: %s: how long ago */
		return [ __( 'Not running', 'storewright' ), 'is-bad', sprintf( __( 'Last run %s. Emails, webhooks and stock updates wait until it is back.', 'storewright' ), timeAgo( runner.last ) ) ];
	}
	/* translators: %s: how long ago */
	return [ __( 'Running', 'storewright' ), 'is-good', sprintf( __( 'Last run %s.', 'storewright' ), timeAgo( runner.last ) ) ];
}

function Runner( { jobs } ) {
	const [ label, tone, note ] = runnerState( jobs.runner );
	return (
		<Card title={ __( 'Runner', 'storewright' ) } action={ <span className={ `sw-pill ${ tone }` }>{ label }</span> }>
			<p>{ note }</p>
			<dl className="sw-jobs__facts">
				<dt>{ __( 'Waiting to run', 'storewright' ) }</dt>
				<dd>{ count( jobs.pending ) }</dd>
				<dt>{ __( 'Overdue', 'storewright' ) }</dt>
				<dd className={ jobs.overdue > 0 ? 'is-bad' : '' }>{ count( jobs.overdue ) }</dd>
			</dl>
		</Card>
	);
}

function Failed( { jobs, onChange } ) {
	const [ busy, setBusy ] = useState( null );
	const [ error, setError ] = useState( null );
	const act = ( job, request ) => {
		setBusy( job.id );
		setError( null );
		request( job.id )
			.then( onChange )
			.catch( ( problem ) => setError( problem.message ) )
			.finally( () => setBusy( null ) );
	};
	return (
		<Card title={ __( 'Failed', 'storewright' ) } padded={ false }>
			{ error && <Notice status="error" isDismissible={ false }>{ error }</Notice> }
			{ jobs.failed.length === 0 ? (
				<Empty>{ __( 'Nothing has failed.', 'storewright' ) }</Empty>
			) : (
				<table className="sw-table">
					<thead>
						<tr>
							<th>{ __( 'When', 'storewright' ) }</th>
							<th>{ __( 'Job', 'storewright' ) }</th>
							<th>{ __( 'What happened', 'storewright' ) }</th>
							<th />
						</tr>
					</thead>
					<tbody>
						{ jobs.failed.map( ( job ) => (
							<tr key={ job.id }>
								<td>{ timeAgo( job.date ) }</td>
								<td>
									<code>{ job.hook }</code>
									{ Object.keys( job.args ).length > 0 && <small>{ JSON.stringify( job.args ) }</small> }
								</td>
								<td>{ job.log }</td>
								<td>
									<div className="sw-actions">
										<button type="button" className="sw-link" disabled={ busy === job.id } onClick={ () => act( job, retryJob ) }>
											{ __( 'Run again', 'storewright' ) }
										</button>
										<button type="button" className="sw-link is-quiet" disabled={ busy === job.id } onClick={ () => act( job, dismissJob ) }>
											{ __( 'Dismiss', 'storewright' ) }
										</button>
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

export const stage = () => {
	const [ jobs, setJobs ] = useState( null );
	const [ error, setError ] = useState( null );
	const load = () => loadJobs().then( setJobs ).catch( ( problem ) => setError( problem.message ) );

	useEffect( () => {
		let live = true;
		loadJobs()
			.then( ( data ) => live && setJobs( data ) )
			.catch( ( problem ) => live && setError( problem.message ) );
		return () => {
			live = false;
		};
	}, [] );

	return (
		<Screen title={ __( 'Background work', 'storewright' ) } lead={ __( 'Emails, webhooks, stock updates and cleanup run behind the store. Anything that failed waits here.', 'storewright' ) }>
			{ error && <Notice status="error" isDismissible={ false }>{ error }</Notice> }
			{ ! jobs && ! error && <Spinner /> }
			{ jobs && (
				<div className="sw-jobs">
					<Runner jobs={ jobs } />
					<Failed jobs={ jobs } onChange={ load } />
				</div>
			) }
		</Screen>
	);
};
