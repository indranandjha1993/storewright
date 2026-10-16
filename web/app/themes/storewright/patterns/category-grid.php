<?php
/**
 * Title: Category grid
 * Slug: storewright/category-grid
 * Categories: storewright
 * Description: Every product category that has products, as tiles linking to the category.
 */

?>
<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|50"}}},"layout":{"type":"constrained","contentSize":"78rem"}} -->
<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--50)">
	<!-- wp:heading {"level":2} -->
	<h2 class="wp-block-heading"><?php esc_html_e( 'Shop by category', 'storewright' ); ?></h2>
	<!-- /wp:heading -->
	<!-- wp:terms-query {"termQuery":{"perPage":8,"taxonomy":"product_cat","order":"asc","orderBy":"name","include":[],"hideEmpty":true,"showNested":false,"inherit":false},"className":"sw-categories"} -->
	<div class="wp-block-terms-query sw-categories">
		<!-- wp:term-template -->
			<!-- wp:term-name {"isLink":true,"fontSize":"large"} /-->
		<!-- /wp:term-template -->
	</div>
	<!-- /wp:terms-query -->
</div>
<!-- /wp:group -->
