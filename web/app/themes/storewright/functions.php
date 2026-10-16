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
	'wp_enqueue_scripts',
	static function (): void {
		wp_enqueue_style( 'storewright', get_stylesheet_uri(), [], wp_get_theme()->get( 'Version' ) );
	}
);

add_filter( 'woocommerce_product_single_add_to_cart_text', static fn(): string => __( 'Add to bag', 'storewright' ) );
add_filter( 'woocommerce_product_add_to_cart_text', static fn( string $text, WC_Product $product ): string => $product->is_type( 'variable' ) ? __( 'Choose options', 'storewright' ) : __( 'Add to bag', 'storewright' ), 10, 2 );
