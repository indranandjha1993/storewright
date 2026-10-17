<?php
/**
 * Title: Search title
 * Slug: storewright/search-title
 * Categories: storewright
 * Description: The search terms as the page heading, under a small label.
 * Inserter: no
 */

?>
<p class="sw-eyebrow"><?php esc_html_e( 'Search results', 'storewright' ); ?></p>
<h1 class="wp-block-heading sw-shop__title">&#8220;<?php echo esc_html( get_search_query() ); ?>&#8221;</h1>
