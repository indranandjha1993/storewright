import { Button, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

function Answer( { answer } ) {
	return (
		<div className={ `sw-areas__answer is-${ answer.status }` }>
			<p className="sw-areas__answer-message">{ answer.message }</p>
			{ answer.area && (
				<p className="sw-areas__hint">
					{ sprintf( /* translators: %s: delivery area name */ __( 'Decided by %s.', 'storewright' ), answer.area ) }
				</p>
			) }
			{ answer.options.length > 0 && (
				<ul className="sw-areas__options">
					{ answer.options.map( ( option ) => (
						<li key={ option.title }>{ `${ option.title }: ${ option.charge }` }</li>
					) ) }
				</ul>
			) }
		</div>
	);
}

export function AreaTest( { answer, onCheck } ) {
	const [ pin, setPin ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const submit = ( event ) => {
		event.preventDefault();
		setBusy( true );
		onCheck( { pin } ).finally( () => setBusy( false ) );
	};
	return (
		<form className="sw-areas__test" onSubmit={ submit } aria-labelledby="sw-areas-test-title">
			<h2 id="sw-areas-test-title" className="sw-areas__subtitle">{ __( 'Test an address', 'storewright' ) }</h2>
			<p className="sw-areas__hint">{ __( 'Enter a PIN code, or click the map to test a spot. The answer is what a shopper would see.', 'storewright' ) }</p>
			<div className="sw-areas__row">
				<TextControl label={ __( 'PIN code to test', 'storewright' ) } value={ pin } onChange={ setPin } inputMode="numeric" maxLength={ 6 } />
				<Button variant="secondary" type="submit" isBusy={ busy } disabled={ busy }>
					{ __( 'Check', 'storewright' ) }
				</Button>
			</div>
			<div role="status">{ answer && <Answer answer={ answer } /> }</div>
		</form>
	);
}
