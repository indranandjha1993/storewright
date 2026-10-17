<div class="sw-empty">
	<p class="sw-empty__title">
		<?php
		/* translators: %s: search terms */
		printf( esc_html__( 'Nothing matches "%s" yet.', 'storewright' ), esc_html( get_search_query() ) );
		?>
	</p>
	<p class="sw-empty__text"><?php esc_html_e( 'Check the spelling, try a broader word, or start from a category.', 'storewright' ); ?></p>
	<?php echo do_blocks( '<!-- wp:storewright/category-chips /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<p><a class="wp-element-button" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php esc_html_e( 'Browse everything', 'storewright' ); ?></a></p>
</div>
