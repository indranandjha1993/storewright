import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { loadBrand, saveBrand, uploadLogo } from './api';
import { contrast, isHex } from './colour';
import { ColourField, FontField, LogoField, RadiusField } from './fields';
import { Preview } from './preview';
import './style.scss';

function problemWith( draft ) {
	for ( const key of [ 'accent', 'background', 'text', 'sale' ] ) {
		if ( ! isHex( draft[ key ] ) ) {
			return __( 'Colours are written as hex, such as #0F5C4D.', 'storewright' );
		}
	}
	if ( contrast( draft.text, draft.background ) < 4.5 ) {
		return __( 'The text colour needs more contrast against the background so the store stays readable.', 'storewright' );
	}
	if ( draft.name.trim() === '' ) {
		return __( 'The store needs a name.', 'storewright' );
	}
	return null;
}

export const stage = () => {
	const [ brand, setBrand ] = useState( null );
	const [ draft, setDraft ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ saved, setSaved ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ uploading, setUploading ] = useState( false );
	const [ pendingLogo, setPendingLogo ] = useState( null );

	const take = ( data ) => {
		setBrand( data );
		setPendingLogo( null );
		setDraft( { ...data.settings, name: data.name, logo: data.logo.id } );
		const tokens = document.getElementById( 'storewright-tokens' );
		if ( tokens ) {
			tokens.textContent = data.adminCss;
		}
	};

	useEffect( () => {
		let current = true;
		loadBrand()
			.then( ( data ) => current && take( data ) )
			.catch( ( failure ) => current && setError( failure.message ) );
		return () => {
			current = false;
		};
	}, [] );

	const set = ( key ) => ( value ) => {
		setSaved( false );
		setDraft( ( current ) => ( { ...current, [ key ]: value } ) );
	};

	const save = ( event ) => {
		event.preventDefault();
		const problem = problemWith( draft );
		if ( problem ) {
			setError( problem );
			return;
		}
		setBusy( true );
		setError( null );
		saveBrand( draft )
			.then( ( data ) => {
				take( data );
				setSaved( true );
			} )
			.catch( ( failure ) => setError( failure.message ) )
			.finally( () => setBusy( false ) );
	};

	const upload = ( file ) => {
		setUploading( true );
		setError( null );
		uploadLogo( file )
			.then( ( media ) => {
				setPendingLogo( { id: media.id, url: media.source_url } );
				set( 'logo' )( media.id );
			} )
			.catch( ( failure ) => setError( failure.message ) )
			.finally( () => setUploading( false ) );
	};

	const removeLogo = () => {
		setPendingLogo( { id: 0, url: '' } );
		set( 'logo' )( 0 );
	};

	if ( ! draft ) {
		return <div className="sw-brand__loading">{ error ? <Notice status="error" isDismissible={ false }>{ error }</Notice> : <Spinner /> }</div>;
	}

	const dirty = JSON.stringify( draft ) !== JSON.stringify( { ...brand.settings, name: brand.name, logo: brand.logo.id } );
	const logo = pendingLogo && draft.logo === pendingLogo.id ? pendingLogo : brand.logo;

	return (
		<div className="sw-brand">
			<style>{ brand.fontCss }</style>
			<form className="sw-brand__form" onSubmit={ save } aria-labelledby="sw-brand-title">
				<h1 id="sw-brand-title" className="sw-brand__title">{ __( 'Brand', 'storewright' ) }</h1>
				<p className="sw-brand__hint">{ __( 'One set of choices styles the store, the checkout, emails, the sign-in page and this console.', 'storewright' ) }</p>
				{ error && (
					<Notice status="error" onRemove={ () => setError( null ) }>
						{ error }
					</Notice>
				) }
				{ saved && ! dirty && (
					<Notice status="success" onRemove={ () => setSaved( false ) }>
						{ __( 'Saved. The store is wearing the new brand.', 'storewright' ) }
					</Notice>
				) }
				<fieldset className="sw-brand__fieldset">
					<legend className="sw-brand__legend">{ __( 'Identity', 'storewright' ) }</legend>
					<TextControl label={ __( 'Store name', 'storewright' ) } value={ draft.name } onChange={ set( 'name' ) } />
					<LogoField logo={ logo } busy={ uploading } onUpload={ upload } onRemove={ removeLogo } />
				</fieldset>
				<fieldset className="sw-brand__fieldset">
					<legend className="sw-brand__legend">{ __( 'Colours', 'storewright' ) }</legend>
					<ColourField label={ __( 'Accent', 'storewright' ) } value={ draft.accent } help={ __( 'Buttons, links and highlights.', 'storewright' ) } onChange={ set( 'accent' ) } />
					<ColourField label={ __( 'Background', 'storewright' ) } value={ draft.background } help={ __( 'The page behind everything. Cards sit on white, or a lighter shade of a dark background.', 'storewright' ) } onChange={ set( 'background' ) } />
					<ColourField label={ __( 'Text', 'storewright' ) } value={ draft.text } onChange={ set( 'text' ) } />
					<ColourField label={ __( 'Sale', 'storewright' ) } value={ draft.sale } help={ __( 'Sale badges and warnings.', 'storewright' ) } onChange={ set( 'sale' ) } />
				</fieldset>
				<fieldset className="sw-brand__fieldset">
					<legend className="sw-brand__legend">{ __( 'Typography and shape', 'storewright' ) }</legend>
					<FontField label={ __( 'Headings', 'storewright' ) } value={ draft.headingFont } fonts={ brand.fonts } onChange={ set( 'headingFont' ) } />
					<FontField label={ __( 'Body text', 'storewright' ) } value={ draft.bodyFont } fonts={ brand.fonts } onChange={ set( 'bodyFont' ) } />
					<RadiusField value={ draft.radius } onChange={ set( 'radius' ) } />
				</fieldset>
				<div className="sw-brand__buttons">
					<Button variant="primary" type="submit" isBusy={ busy } disabled={ busy || ! dirty } accessibleWhenDisabled>
						{ __( 'Save brand', 'storewright' ) }
					</Button>
					<Button variant="tertiary" disabled={ ! dirty } accessibleWhenDisabled onClick={ () => take( brand ) }>
						{ __( 'Discard changes', 'storewright' ) }
					</Button>
				</div>
			</form>
			<Preview settings={ draft } fonts={ brand.fonts } name={ draft.name } logo={ logo.url } />
		</div>
	);
};
