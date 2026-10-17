import { Button, Notice, Spinner, TextareaControl, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { loadBrand, saveBrand, uploadImage } from './api';
import { contrast, isHex } from './colour';
import { ColourField, FontField, ImageField, RadiusField } from './fields';
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
	const [ pending, setPending ] = useState( {} );

	const take = ( data ) => {
		setBrand( data );
		setPending( {} );
		setDraft( { ...data.settings, name: data.name, description: data.description, announcement: data.announcement, logo: data.logo.id, heroImage: data.heroImage.id } );
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

	const upload = ( key ) => ( file ) => {
		setUploading( key );
		setError( null );
		uploadImage( file )
			.then( ( media ) => {
				setPending( ( current ) => ( { ...current, [ key ]: { id: media.id, url: media.source_url } } ) );
				set( key )( media.id );
			} )
			.catch( ( failure ) => setError( failure.message ) )
			.finally( () => setUploading( false ) );
	};

	const remove = ( key ) => () => {
		setPending( ( current ) => ( { ...current, [ key ]: { id: 0, url: '' } } ) );
		set( key )( 0 );
	};

	const image = ( key ) => ( pending[ key ] && draft[ key ] === pending[ key ].id ? pending[ key ] : brand[ key ] );

	if ( ! draft ) {
		return <div className="sw-brand__loading">{ error ? <Notice status="error" isDismissible={ false }>{ error }</Notice> : <Spinner /> }</div>;
	}

	const dirty = JSON.stringify( draft ) !== JSON.stringify( { ...brand.settings, name: brand.name, description: brand.description, announcement: brand.announcement, logo: brand.logo.id, heroImage: brand.heroImage.id } );
	const logo = image( 'logo' );

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
					<ImageField
						label={ __( 'Logo', 'storewright' ) }
						image={ logo }
						busy={ uploading === 'logo' }
						empty={ __( 'Without a logo the store shows its name in the heading typeface.', 'storewright' ) }
						hint={ __( 'PNG or SVG with a transparent background works best, about 400 pixels wide.', 'storewright' ) }
						onUpload={ upload( 'logo' ) }
						onRemove={ remove( 'logo' ) }
					/>
					<TextareaControl
						label={ __( 'Store description', 'storewright' ) }
						help={ __( 'One or two sentences on what the store sells. Search results and link previews show it under the store name.', 'storewright' ) }
						value={ draft.description }
						rows={ 2 }
						maxLength={ 160 }
						onChange={ set( 'description' ) }
					/>
					<TextControl
						label={ __( 'Announcement', 'storewright' ) }
						help={ __( 'One line above the header, for an offer or a delivery promise. Leave empty to hide it.', 'storewright' ) }
						value={ draft.announcement }
						maxLength={ 140 }
						onChange={ set( 'announcement' ) }
					/>
					<ImageField
						label={ __( 'Home page photo', 'storewright' ) }
						image={ image( 'heroImage' ) }
						busy={ uploading === 'heroImage' }
						empty={ __( 'Without a photo the opening section sits on the soft accent tint.', 'storewright' ) }
						hint={ __( 'A wide photo, at least 1600 pixels across. The headline sits on its left side.', 'storewright' ) }
						onUpload={ upload( 'heroImage' ) }
						onRemove={ remove( 'heroImage' ) }
					/>
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
