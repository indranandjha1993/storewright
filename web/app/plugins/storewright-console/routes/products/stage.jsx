import { Button, Notice, SearchControl, Spinner, TextControl, ToggleControl } from '@wordpress/components';
import { Fragment, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { adminLink } from '../../lib/data';
import { count, money } from '../../lib/format';
import { Empty, Screen, Segmented } from '../../lib/screen';
import { loadProducts, PER_PAGE, saveProduct } from './api';
import './style.scss';

const FILTERS = [
	{ key: '', label: __( 'All', 'storewright' ) },
	{ key: 'instock', label: __( 'In stock', 'storewright' ) },
	{ key: 'outofstock', label: __( 'Out of stock', 'storewright' ) },
	{ key: 'onbackorder', label: __( 'On backorder', 'storewright' ) },
];

const stockText = ( product ) => {
	if ( product.type === 'variable' ) {
		return __( 'Per variation', 'storewright' );
	}
	if ( product.manage_stock ) {
		/* translators: %s: units in stock */
		return sprintf( __( '%s in stock', 'storewright' ), count( product.stock_quantity ?? 0 ) );
	}
	return { instock: __( 'In stock', 'storewright' ), outofstock: __( 'Out of stock', 'storewright' ), onbackorder: __( 'On backorder', 'storewright' ) }[ product.stock_status ] ?? product.stock_status;
};

function Editor( { product, onSaved, onClose } ) {
	const [ draft, setDraft ] = useState( {
		regular_price: product.regular_price,
		sale_price: product.sale_price,
		manage_stock: product.manage_stock,
		stock_quantity: product.stock_quantity ?? 0,
		stock_status: product.stock_status,
	} );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const set = ( key ) => ( value ) => setDraft( ( current ) => ( { ...current, [ key ]: value } ) );
	const simple = product.type !== 'variable';

	const submit = ( event ) => {
		event.preventDefault();
		setBusy( true );
		setError( null );
		const data = simple
			? { ...draft, stock_quantity: Number( draft.stock_quantity ) || 0, status: product.status }
			: { status: product.status };
		saveProduct( product.id, data )
			.then( onSaved )
			.catch( ( failure ) => setError( failure.message ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<form className="sw-products__editor" onSubmit={ submit } aria-label={ /* translators: %s: product name */ sprintf( __( 'Edit %s', 'storewright' ), product.name ) }>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ simple ? (
				<div className="sw-products__fields">
					<TextControl label={ __( 'Price', 'storewright' ) } type="number" min="0" step="any" value={ draft.regular_price } onChange={ set( 'regular_price' ) } />
					<TextControl label={ __( 'Sale price', 'storewright' ) } type="number" min="0" step="any" value={ draft.sale_price } onChange={ set( 'sale_price' ) } help={ __( 'Leave empty for no sale.', 'storewright' ) } />
					<ToggleControl label={ __( 'Track stock', 'storewright' ) } checked={ draft.manage_stock } onChange={ set( 'manage_stock' ) } />
					{ draft.manage_stock ? (
						<TextControl label={ __( 'Units in stock', 'storewright' ) } type="number" min="0" step="1" value={ String( draft.stock_quantity ) } onChange={ set( 'stock_quantity' ) } />
					) : (
						<ToggleControl label={ __( 'Available to buy', 'storewright' ) } checked={ draft.stock_status === 'instock' } onChange={ ( on ) => set( 'stock_status' )( on ? 'instock' : 'outofstock' ) } />
					) }
				</div>
			) : (
				<p className="sw-products__hint">{ __( 'Prices and stock for this product live on its variations. Open the full editor to change them.', 'storewright' ) }</p>
			) }
			<div className="sw-products__editor-actions">
				{ simple && (
					<Button variant="primary" size="compact" type="submit" isBusy={ busy } disabled={ busy }>
						{ __( 'Save', 'storewright' ) }
					</Button>
				) }
				<Button variant="tertiary" size="compact" onClick={ onClose }>
					{ simple ? __( 'Cancel', 'storewright' ) : __( 'Close', 'storewright' ) }
				</Button>
				<a className="sw-link sw-products__full" href={ adminLink( `post.php?post=${ product.id }&action=edit` ) }>
					{ __( 'Full editor', 'storewright' ) }
				</a>
			</div>
		</form>
	);
}

export const stage = () => {
	const [ search, setSearch ] = useState( '' );
	const [ query, setQuery ] = useState( '' );
	const [ stock, setStock ] = useState( '' );
	const [ pageNumber, setPageNumber ] = useState( 1 );
	const [ result, setResult ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ editing, setEditing ] = useState( null );

	useEffect( () => {
		const timer = setTimeout( () => {
			setQuery( search.trim() );
			setPageNumber( 1 );
		}, 300 );
		return () => clearTimeout( timer );
	}, [ search ] );

	useEffect( () => {
		let current = true;
		setError( null );
		loadProducts( { search: query, stock, pageNumber } )
			.then( ( loaded ) => current && setResult( loaded ) )
			.catch( ( failure ) => current && setError( failure.message ) );
		return () => {
			current = false;
		};
	}, [ query, stock, pageNumber ] );

	const saved = ( updated ) => {
		setResult( ( current ) => current && { ...current, items: current.items.map( ( product ) => ( product.id === updated.id ? updated : product ) ) } );
		setEditing( null );
	};

	const from = result ? ( pageNumber - 1 ) * PER_PAGE + 1 : 0;
	const to = result ? Math.min( pageNumber * PER_PAGE, result.total ) : 0;

	return (
		<Screen
			title={ __( 'Products', 'storewright' ) }
			className="sw-products"
			wide
			actions={
				<>
					<SearchControl label={ __( 'Search products', 'storewright' ) } placeholder={ __( 'Name or SKU', 'storewright' ) } value={ search } onChange={ setSearch } size="compact" />
					<Button variant="primary" href={ adminLink( 'post-new.php?post_type=product' ) }>
						{ __( 'Add a product', 'storewright' ) }
					</Button>
				</>
			}
		>
			<Segmented
				label={ __( 'Stock', 'storewright' ) }
				value={ stock }
				options={ FILTERS }
				onChange={ ( next ) => {
					setStock( next );
					setPageNumber( 1 );
				} }
			/>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<div className="sw-products__list">
				{ ! result && ! error && <Spinner /> }
				{ result && result.items.length === 0 && <Empty>{ __( 'No products match.', 'storewright' ) }</Empty> }
				{ result && result.items.length > 0 && (
					<>
						<table className="sw-table sw-products__table">
							<thead>
								<tr>
									<th scope="col">{ __( 'Product', 'storewright' ) }</th>
									<th scope="col">{ __( 'Category', 'storewright' ) }</th>
									<th scope="col">{ __( 'Stock', 'storewright' ) }</th>
									<th scope="col" className="is-number">{ __( 'Price', 'storewright' ) }</th>
									<th scope="col" className="is-number">{ __( 'Sold', 'storewright' ) }</th>
									<th scope="col"><span className="screen-reader-text">{ __( 'Actions', 'storewright' ) }</span></th>
								</tr>
							</thead>
							<tbody>
								{ result.items.map( ( product ) => (
									<Fragment key={ product.id }>
										<tr className={ editing === product.id ? 'is-selected' : '' }>
											<td>
												<span className="sw-products__name">
													<span className="sw-products__thumb">{ product.images[ 0 ] && <img src={ product.images[ 0 ].src } alt="" /> }</span>
													<span>
														<strong>{ product.name }</strong>
														<small>
															{ product.sku }
															{ product.status !== 'publish' && ` · ${ product.status }` }
															{ product.type === 'variable' && ` · ${ __( 'Variable', 'storewright' ) }` }
														</small>
													</span>
												</span>
											</td>
											<td>{ product.categories.map( ( category ) => category.name ).join( ', ' ) }</td>
											<td>
												<span className={ `sw-pill ${ product.stock_status === 'instock' ? 'is-good' : 'is-bad' }` }>{ stockText( product ) }</span>
											</td>
											<td className="is-number">
												{ product.on_sale ? (
													<>
														<s>{ money( product.regular_price, 2 ) }</s> { money( product.sale_price, 2 ) }
													</>
												) : (
													money( product.price, 2 )
												) }
											</td>
											<td className="is-number">{ count( product.total_sales ) }</td>
											<td>
												<div className="sw-actions">
													<button type="button" className="sw-link" aria-expanded={ editing === product.id } onClick={ () => setEditing( editing === product.id ? null : product.id ) }>
														{ __( 'Edit', 'storewright' ) }
													</button>
												</div>
											</td>
										</tr>
										{ editing === product.id && (
											<tr className="sw-products__editor-row">
												<td colSpan={ 6 }>
													<Editor product={ product } onSaved={ saved } onClose={ () => setEditing( null ) } />
												</td>
											</tr>
										) }
									</Fragment>
								) ) }
							</tbody>
						</table>
						<footer className="sw-products__pager">
							<span>{ /* translators: 1: first row, 2: last row, 3: total */ sprintf( __( '%1$s to %2$s of %3$s', 'storewright' ), from, to, count( result.total ) ) }</span>
							<Button variant="tertiary" size="compact" disabled={ pageNumber <= 1 } accessibleWhenDisabled onClick={ () => setPageNumber( pageNumber - 1 ) }>
								{ __( 'Previous', 'storewright' ) }
							</Button>
							<Button variant="tertiary" size="compact" disabled={ pageNumber >= result.pages } accessibleWhenDisabled onClick={ () => setPageNumber( pageNumber + 1 ) }>
								{ __( 'Next', 'storewright' ) }
							</Button>
						</footer>
					</>
				) }
			</div>
		</Screen>
	);
};
