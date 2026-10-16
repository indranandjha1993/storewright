<?php
/**
 * Title: Category grid
 * Slug: storewright/category-grid
 * Categories: storewright
 * Description: Every product category that has products, as photo tiles linking to the category.
 */

$storewright_terms = get_terms(
	[
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'number'     => 8,
		'orderby'    => 'name',
	]
);
if ( is_wp_error( $storewright_terms ) || $storewright_terms === [] ) {
	return;
}
?>
<section class="sw-section sw-categories">
	<header class="sw-section__head">
		<div>
			<p class="sw-eyebrow"><?php esc_html_e( 'Browse', 'storewright' ); ?></p>
			<h2 class="sw-section__title"><?php esc_html_e( 'Shop by category', 'storewright' ); ?></h2>
		</div>
		<a class="sw-section__more" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php esc_html_e( 'View all', 'storewright' ); ?><?php echo storewright_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
	</header>
	<ul class="sw-categories__grid">
		<?php foreach ( $storewright_terms as $storewright_term ) : ?>
			<?php $storewright_thumbnail = (int) get_term_meta( $storewright_term->term_id, 'thumbnail_id', true ); ?>
			<li class="sw-category">
				<a class="sw-category__link" href="<?php echo esc_url( (string) get_term_link( $storewright_term ) ); ?>" aria-labelledby="sw-category-<?php echo (int) $storewright_term->term_id; ?>">
					<?php if ( $storewright_thumbnail ) : ?>
						<?php
						echo wp_get_attachment_image(
							$storewright_thumbnail,
							'large',
							false,
							[
								'class'   => 'sw-category__image',
								'loading' => 'lazy',
							]
						);
						?>
					<?php endif; ?>
					<span class="sw-category__body">
						<span class="sw-category__name" id="sw-category-<?php echo (int) $storewright_term->term_id; ?>"><?php echo esc_html( $storewright_term->name ); ?></span>
						<?php if ( $storewright_term->description ) : ?>
							<span class="sw-category__description"><?php echo esc_html( $storewright_term->description ); ?></span>
						<?php endif; ?>
						<span class="sw-category__count">
							<?php
							/* translators: %s: number of products */
							echo esc_html( sprintf( _n( '%s product', '%s products', $storewright_term->count, 'storewright' ), number_format_i18n( $storewright_term->count ) ) );
							?>
						</span>
					</span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
