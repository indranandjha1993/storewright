<?php
/**
 * Title: Footer note
 * Slug: storewright/footer-note
 * Categories: storewright
 * Description: Copyright line with the store name and the current year.
 * Inserter: no
 */

?>
<p class="sw-footer__note">
	<?php
	/* translators: 1: year, 2: store name */
	echo esc_html( sprintf( __( '%1$s %2$s. All prices include GST.', 'storewright' ), wp_date( 'Y' ), get_bloginfo( 'name' ) ) );
	?>
</p>
