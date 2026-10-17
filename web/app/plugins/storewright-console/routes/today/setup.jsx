import apiFetch from '@wordpress/api-fetch';
import { Button } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { check, Icon } from '@wordpress/icons';
import { consoleLink } from '../../lib/data';

export function Setup() {
	const [ setup, setSetup ] = useState( null );

	useEffect( () => {
		let current = true;
		apiFetch( { path: '/storewright/v1/setup' } ).then( ( loaded ) => current && setSetup( loaded ) );
		return () => {
			current = false;
		};
	}, [] );

	if ( ! setup || setup.dismissed || setup.done === setup.steps.length ) {
		return null;
	}

	const hide = () => apiFetch( { path: '/storewright/v1/setup/dismiss', method: 'POST' } ).then( setSetup );

	return (
		<section className="sw-setup" aria-label={ __( 'Getting started', 'storewright' ) }>
			<header className="sw-setup__head">
				<div>
					<h2 className="sw-setup__title">{ __( 'Getting started', 'storewright' ) }</h2>
					<p className="sw-setup__lead">{ /* translators: 1: steps done, 2: all steps */ sprintf( __( '%1$s of %2$s done. Each step opens the screen that finishes it.', 'storewright' ), setup.done, setup.steps.length ) }</p>
				</div>
				<Button variant="tertiary" size="compact" onClick={ hide }>
					{ __( 'Hide', 'storewright' ) }
				</Button>
			</header>
			<div className="sw-setup__bar" role="progressbar" aria-valuemin={ 0 } aria-valuemax={ setup.steps.length } aria-valuenow={ setup.done } aria-label={ __( 'Setup progress', 'storewright' ) }>
				<span style={ { width: `${ ( setup.done / setup.steps.length ) * 100 }%` } } />
			</div>
			<ol className="sw-setup__steps">
				{ setup.steps.map( ( step ) => (
					<li key={ step.id } className={ step.done ? 'is-done' : '' }>
						<span className="sw-setup__mark" aria-hidden="true">{ step.done && <Icon icon={ check } size={ 18 } /> }</span>
						<span className="sw-setup__text">
							<strong>{ step.title }</strong>
							<small>{ step.detail }</small>
						</span>
						{ step.done ? (
							<span className="sw-setup__state">{ __( 'Done', 'storewright' ) }</span>
						) : (
							<a className="components-button is-secondary is-compact" href={ consoleLink( step.path ) }>
								{ __( 'Open', 'storewright' ) }
							</a>
						) }
					</li>
				) ) }
			</ol>
		</section>
	);
}
