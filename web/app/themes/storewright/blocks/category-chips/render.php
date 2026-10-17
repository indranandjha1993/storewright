<?php
$storewright_terms = get_terms(
	[
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'orderby'    => 'name',
	]
);
if ( is_wp_error( $storewright_terms ) || count( $storewright_terms ) < 2 ) {
	return;
}
$storewright_current = get_queried_object();
$storewright_active  = $storewright_current instanceof WP_Term && $storewright_current->taxonomy === 'product_cat' ? $storewright_current->term_id : 0;
$storewright_all     = ! $storewright_active && ! is_search();
?>
<nav class="sw-chips" aria-label="<?php esc_attr_e( 'Categories', 'storewright' ); ?>">
	<a class="sw-chip<?php echo $storewright_all ? ' is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"<?php echo $storewright_all ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Everything', 'storewright' ); ?></a>
	<?php foreach ( $storewright_terms as $storewright_term ) : ?>
		<a class="sw-chip<?php echo $storewright_term->term_id === $storewright_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( (string) get_term_link( $storewright_term ) ); ?>"<?php echo $storewright_term->term_id === $storewright_active ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $storewright_term->name ); ?></a>
	<?php endforeach; ?>
</nav>
