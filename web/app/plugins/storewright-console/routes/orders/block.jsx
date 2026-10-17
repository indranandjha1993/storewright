export function Block( { title, children } ) {
	return (
		<section className="sw-order__block" aria-label={ title }>
			<h3 className="sw-order__block-title">{ title }</h3>
			{ children }
		</section>
	);
}
