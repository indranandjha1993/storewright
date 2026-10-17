<?php
/**
 * Title: No results
 * Slug: storewright/no-results
 * Categories: storewright
 * Description: Shown when a product search finds nothing, with ways back into the catalogue.
 * Inserter: no
 */

?>
<div class="sw-empty">
	<p class="sw-empty__title">
		<?php
		/* translators: %s: search terms */
		printf( esc_html__( 'Nothing matches "%s" yet.', 'storewright' ), esc_html( get_search_query() ) );
		?>
	</p>
	<p class="sw-empty__text"><?php esc_html_e( 'Check the spelling, try a broader word, or start from a category.', 'storewright' ); ?></p>
	<!-- wp:pattern {"slug":"storewright/category-chips"} /-->
	<p><a class="wp-element-button" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php esc_html_e( 'Browse everything', 'storewright' ); ?></a></p>
</div>
