export function Block( { title, children } ) {
	return (
		<section className="sw-panel__block" aria-label={ title }>
			<h3 className="sw-panel__block-title">{ title }</h3>
			{ children }
		</section>
	);
}
