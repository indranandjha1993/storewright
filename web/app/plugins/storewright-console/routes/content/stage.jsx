import { Button, Notice, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { adminLink } from '../../lib/data';
import { timeAgo } from '../../lib/format';
import { Card, Empty, Screen } from '../../lib/screen';
import { loadEntries, loadPages } from './api';
import './style.scss';

const SYSTEM = [ 'shop', 'cart', 'checkout', 'my-account', 'home', 'journal' ];

const statusLabel = {
	publish: __( 'Live', 'storewright' ),
	draft: __( 'Draft', 'storewright' ),
	pending: __( 'Awaiting review', 'storewright' ),
	private: __( 'Private', 'storewright' ),
};

function Rows( { items, kind } ) {
	if ( items.length === 0 ) {
		return <Empty>{ kind === 'page' ? __( 'No pages yet.', 'storewright' ) : __( 'No journal entries yet. The first one shows up here.', 'storewright' ) }</Empty>;
	}
	return (
		<table className="sw-table">
			<thead>
				<tr>
					<th scope="col">{ __( 'Title', 'storewright' ) }</th>
					<th scope="col">{ __( 'Status', 'storewright' ) }</th>
					<th scope="col">{ __( 'Updated', 'storewright' ) }</th>
					<th scope="col"><span className="screen-reader-text">{ __( 'Actions', 'storewright' ) }</span></th>
				</tr>
			</thead>
			<tbody>
				{ items.map( ( item ) => (
					<tr key={ item.id }>
						<td>
							<strong dangerouslySetInnerHTML={ { __html: item.title.rendered || __( '(no title)', 'storewright' ) } } />
							<small>/{ item.slug }/</small>
						</td>
						<td>
							<span className={ `sw-pill ${ item.status === 'publish' ? 'is-good' : 'is-quiet' }` }>{ statusLabel[ item.status ] ?? item.status }</span>
						</td>
						<td>{ timeAgo( item.modified_gmt + 'Z' ) }</td>
						<td className="is-number sw-content__actions">
							<Button variant="secondary" size="compact" href={ adminLink( `post.php?post=${ item.id }&action=edit` ) }>
								{ __( 'Edit', 'storewright' ) }
							</Button>
							<a className="sw-link" href={ item.link } target="_blank" rel="noreferrer">
								{ __( 'View', 'storewright' ) }
							</a>
						</td>
					</tr>
				) ) }
			</tbody>
		</table>
	);
}

export const stage = () => {
	const [ pages, setPages ] = useState( null );
	const [ entries, setEntries ] = useState( null );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		let current = true;
		Promise.all( [ loadPages(), loadEntries() ] )
			.then( ( [ loadedPages, loadedEntries ] ) => {
				if ( current ) {
					setPages( loadedPages );
					setEntries( loadedEntries );
				}
			} )
			.catch( ( failure ) => current && setError( failure.message ) );
		return () => {
			current = false;
		};
	}, [] );

	const store = ( pages ?? [] ).filter( ( page ) => ! SYSTEM.includes( page.slug ) );

	return (
		<Screen
			title={ __( 'Pages and journal', 'storewright' ) }
			lead={ __( 'The pages shoppers read, and the journal where the store tells its story. Each opens in the editor.', 'storewright' ) }
			className="sw-content"
			wide
			actions={
				<>
					<Button variant="secondary" href={ adminLink( 'post-new.php?post_type=page' ) }>
						{ __( 'New page', 'storewright' ) }
					</Button>
					<Button variant="primary" href={ adminLink( 'post-new.php' ) }>
						{ __( 'New journal entry', 'storewright' ) }
					</Button>
				</>
			}
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ ! pages && ! error && <Spinner /> }
			{ pages && (
				<div className="sw-content__grid">
					<Card title={ __( 'Pages', 'storewright' ) } padded={ false }>
						<Rows items={ store } kind="page" />
					</Card>
					<Card
						title={ __( 'Journal', 'storewright' ) }
						padded={ false }
						action={
							<a className="sw-link" href={ adminLink( 'edit.php' ) }>
								{ __( 'All entries', 'storewright' ) }
							</a>
						}
					>
						<Rows items={ entries ?? [] } kind="post" />
					</Card>
				</div>
			) }
			<p className="sw-content__note">{ __( 'Shop, bag, checkout and account pages are managed by the store itself and are not listed here.', 'storewright' ) }</p>
		</Screen>
	);
};
