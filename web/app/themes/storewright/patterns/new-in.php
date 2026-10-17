<?php
/**
 * Title: New in
 * Slug: storewright/new-in
 * Categories: storewright
 * Description: The eight newest products that are in stock.
 */

?>
<!-- wp:group {"className":"sw-section sw-new-in","layout":{"type":"constrained","contentSize":"78rem"}} -->
<div class="wp-block-group sw-section sw-new-in">
	<!-- wp:group {"className":"sw-section__head","layout":{"type":"flex","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
	<div class="wp-block-group sw-section__head">
		<!-- wp:group {"layout":{"type":"flex","orientation":"vertical"},"style":{"spacing":{"blockGap":"0"}}} -->
		<div class="wp-block-group">
			<!-- wp:paragraph {"className":"sw-eyebrow"} -->
			<p class="sw-eyebrow"><?php esc_html_e( 'Just landed', 'storewright' ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:heading {"level":2,"className":"sw-section__title"} -->
			<h2 class="wp-block-heading sw-section__title"><?php esc_html_e( 'New in', 'storewright' ); ?></h2>
			<!-- /wp:heading -->
		</div>
		<!-- /wp:group -->
		<!-- wp:paragraph {"className":"sw-section__more-wrap"} -->
		<p class="sw-section__more-wrap"><a class="sw-section__more" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php esc_html_e( 'View all', 'storewright' ); ?></a></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
	<!-- wp:woocommerce/product-collection {"queryId":2,"query":{"perPage":8,"pages":1,"offset":0,"postType":"product","order":"desc","orderBy":"date","search":"","exclude":[],"inherit":false,"taxQuery":{},"isProductCollectionBlock":true,"featured":false,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[]},"tagName":"div","displayLayout":{"type":"flex","columns":4,"shrinkColumns":true},"dimensions":{"widthType":"fill"},"queryContextIncludes":["collection"]} -->
	<div class="wp-block-woocommerce-product-collection">
		<!-- wp:woocommerce/product-template -->
			<!-- wp:woocommerce/product-image {"showSaleBadge":false,"imageSizing":"single","isDescendentOfQueryLoop":true} -->
				<!-- wp:woocommerce/product-sale-badge {"isDescendentOfQueryLoop":true,"align":"left"} /-->
			<!-- /wp:woocommerce/product-image -->
			<!-- wp:post-title {"level":3,"isLink":true,"fontSize":"medium","__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->
			<!-- wp:woocommerce/product-rating {"isDescendentOfQueryLoop":true} /-->
			<!-- wp:woocommerce/product-price {"isDescendentOfQueryLoop":true,"fontSize":"small"} /-->
			<!-- wp:woocommerce/product-button {"isDescendentOfQueryLoop":true,"fontSize":"small"} /-->
		<!-- /wp:woocommerce/product-template -->
		<!-- wp:woocommerce/product-collection-no-results -->
			<!-- wp:paragraph -->
			<p><?php esc_html_e( 'No products yet.', 'storewright' ); ?></p>
			<!-- /wp:paragraph -->
		<!-- /wp:woocommerce/product-collection-no-results -->
	</div>
	<!-- /wp:woocommerce/product-collection -->
</div>
<!-- /wp:group -->
