<?php

defined( 'ABSPATH' ) || exit;
?>
<div class="sw-saved">
	<header class="sw-account__section-head">
		<h2><?php esc_html_e( 'Saved for later', 'storewright' ); ?></h2>
		<p><?php esc_html_e( 'Products you saved while browsing. Prices and stock are shown as they are today.', 'storewright' ); ?></p>
	</header>
	<?php if ( $products === [] ) : ?>
		<p class="sw-account__empty">
			<?php esc_html_e( 'Nothing saved yet. Use "Save for later" on any product page.', 'storewright' ); ?>
			<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Browse the shop', 'storewright' ); ?></a>
		</p>
	<?php endif; ?>
	<ul class="sw-saved__list">
		<?php foreach ( $products as $storewright_product ) : ?>
			<li class="sw-saved__item">
				<a class="sw-saved__image" href="<?php echo esc_url( $storewright_product->get_permalink() ); ?>"><?php echo wp_kses_post( $storewright_product->get_image( 'woocommerce_thumbnail' ) ); ?></a>
				<div class="sw-saved__body">
					<a class="sw-saved__name" href="<?php echo esc_url( $storewright_product->get_permalink() ); ?>"><?php echo esc_html( $storewright_product->get_name() ); ?></a>
					<span class="sw-saved__price"><?php echo wp_kses_post( $storewright_product->get_price_html() ); ?></span>
					<?php if ( ! $storewright_product->is_in_stock() ) : ?>
						<span class="sw-saved__stock"><?php esc_html_e( 'Out of stock', 'storewright' ); ?></span>
					<?php endif; ?>
				</div>
				<div class="sw-saved__actions">
					<?php if ( $storewright_product->is_in_stock() ) : ?>
						<a class="wp-element-button" href="<?php echo esc_url( $storewright_product->is_type( 'simple' ) ? $storewright_product->add_to_cart_url() : $storewright_product->get_permalink() ); ?>"><?php echo esc_html( $storewright_product->is_type( 'simple' ) ? __( 'Add to bag', 'storewright' ) : __( 'Choose options', 'storewright' ) ); ?></a>
					<?php endif; ?>
					<form method="post">
						<?php wp_nonce_field( $nonce ); ?>
						<input type="hidden" name="product" value="<?php echo esc_attr( (string) $storewright_product->get_id() ); ?>">
						<button type="submit" class="sw-address__link" name="storewright_saved_action" value="remove"><?php esc_html_e( 'Remove', 'storewright' ); ?></button>
					</form>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
