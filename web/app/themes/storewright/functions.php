<?php

declare(strict_types=1);

add_action(
	'after_setup_theme',
	static function (): void {
		add_theme_support( 'editor-styles' );
		add_editor_style( 'style.css' );
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
	}
);

add_action(
	'init',
	static function (): void {
		register_block_pattern_category( 'storewright', [ 'label' => __( 'Store sections', 'storewright' ) ] );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_enqueue_style( 'storewright', get_stylesheet_uri(), [], (string) filemtime( get_stylesheet_directory() . '/style.css' ) );
		wp_enqueue_script( 'storewright-search', get_theme_file_uri( 'assets/js/search.js' ), [], (string) filemtime( get_theme_file_path( 'assets/js/search.js' ) ), true );
		wp_add_inline_script(
			'storewright-search',
			'window.storewrightSearch = ' . wp_json_encode(
				[
					'endpoint' => rest_url( 'wc/store/v1/products' ),
					'results'  => home_url( '/' ),
					'label'    => __( 'Search suggestions', 'storewright' ),
					'empty'    => __( 'No products match yet.', 'storewright' ),
					/* translators: %s: search terms */
					'all'      => __( 'See all results for "%s"', 'storewright' ),
				]
			) . ';',
			'before'
		);
	}
);

add_filter( 'woocommerce_product_single_add_to_cart_text', static fn(): string => __( 'Add to bag', 'storewright' ) );
add_filter( 'woocommerce_product_description_heading', '__return_empty_string' );
add_filter( 'woocommerce_product_additional_information_heading', '__return_empty_string' );
add_filter( 'woocommerce_product_additional_information_tab_title', static fn(): string => __( 'Details', 'storewright' ) );
add_filter( 'woocommerce_product_add_to_cart_text', static fn( string $text, WC_Product $product ): string => $product->is_type( 'variable' ) ? __( 'Choose options', 'storewright' ) : __( 'Add to bag', 'storewright' ), 10, 2 );

add_filter(
	'woocommerce_product_add_to_cart_description',
	static fn( string $description, WC_Product $product ): string => sprintf(
		/* translators: %s: product name */
		$product->is_type( 'variable' ) ? __( 'Choose options for “%s”', 'storewright' ) : __( 'Add to bag: “%s”', 'storewright' ),
		$product->get_name()
	),
	10,
	2
);

add_filter(
	'woocommerce_reviews_title',
	/* translators: %d: number of reviews */
	static fn( string $title, int $count ): string => esc_html( sprintf( _n( '%d review', '%d reviews', $count, 'storewright' ), $count ) ),
	10,
	2
);

add_filter(
	'woocommerce_product_tabs',
	static function ( array $tabs ): array {
		if ( isset( $tabs['reviews'] ) ) {
			$tabs['reviews']['callback'] = 'storewright_reviews_tab';
		}
		return $tabs;
	},
	98
);

function storewright_reviews_tab(): void {
	global $product;
	echo '<div class="sw-reviews">';
	if ( $product instanceof WC_Product && $product->get_review_count() > 0 ) {
		storewright_review_summary( $product );
	}
	comments_template();
	echo '</div>';
}

function storewright_review_summary( WC_Product $product ): void {
	$count   = $product->get_review_count();
	$average = (float) $product->get_average_rating();
	$ratings = $product->get_rating_counts();
	?>
	<aside class="sw-reviews__summary" aria-label="<?php esc_attr_e( 'Rating summary', 'storewright' ); ?>">
		<p class="sw-reviews__score"><?php echo esc_html( number_format_i18n( $average, 1 ) ); ?></p>
		<?php echo wp_kses_post( wc_get_rating_html( $average, $count ) ); ?>
		<p class="sw-reviews__count">
			<?php
			/* translators: %d: number of reviews */
			echo esc_html( sprintf( _n( 'Based on %d review', 'Based on %d reviews', $count, 'storewright' ), $count ) );
			?>
		</p>
		<ul class="sw-reviews__bars">
			<?php for ( $storewright_stars = 5; $storewright_stars >= 1; $storewright_stars-- ) : ?>
				<?php $storewright_share = (int) round( (int) ( $ratings[ $storewright_stars ] ?? 0 ) / $count * 100 ); ?>
				<li>
					<span>
						<?php
						/* translators: %d: star rating from 1 to 5 */
						echo esc_html( sprintf( _n( '%d star', '%d stars', $storewright_stars, 'storewright' ), $storewright_stars ) );
						?>
					</span>
					<span class="sw-reviews__bar"><span style="width: <?php echo esc_attr( (string) $storewright_share ); ?>%"></span></span>
					<span><?php echo esc_html( $storewright_share . '%' ); ?></span>
				</li>
			<?php endfor; ?>
		</ul>
	</aside>
	<?php
}

add_action(
	'init',
	static function (): void {
		foreach ( array_diff( scandir( __DIR__ . '/blocks' ) ?: [], [ '.', '..' ] ) as $storewright_block ) {
			register_block_type( __DIR__ . '/blocks/' . $storewright_block );
		}
	}
);

function storewright_hero_image( string $size = 'full' ): string {
	$id  = (int) get_option( 'storewright_hero_image' );
	$url = $id ? wp_get_attachment_image_url( $id, $size ) : false;
	return $url ? $url : '';
}

function storewright_announcement(): string {
	return trim( (string) get_option( 'storewright_announcement', '' ) );
}

function storewright_icon( string $name ): string {
	$paths = [
		'truck'  => '<path d="M3 7h11v8H3zM14 10h4l3 3v2h-7z"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>',
		'return' => '<path d="M4 10a8 8 0 1 1 2.3 5.7"/><path d="M4 4v6h6"/>',
		'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
		'wallet' => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/><circle cx="16.5" cy="14.5" r="1"/>',
		'pin'    => '<path d="M12 21s-6-5.3-6-10a6 6 0 1 1 12 0c0 4.7-6 10-6 10z"/><circle cx="12" cy="11" r="2"/>',
		'arrow'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
	];
	return sprintf(
		'<svg class="sw-icon sw-icon--%s" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
		esc_attr( $name ),
		$paths[ $name ] ?? ''
	);
}
