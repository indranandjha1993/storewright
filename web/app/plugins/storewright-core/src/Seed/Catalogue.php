<?php

declare(strict_types=1);

namespace Storewright\Core\Seed;

use WC_Coupon;
use WC_Product_Attribute;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;

final class Catalogue {

	private Media $media;

	public function __construct() {
		$this->media = new Media();
	}

	public function run(): array {
		$categories = [
			'apparel'  => $this->category( 'Apparel', 'apparel' ),
			'home'     => $this->category( 'Home', 'home' ),
			'books'    => $this->category( 'Books', 'books' ),
			'software' => $this->category( 'Software', 'software' ),
		];

		$this->variable(
			'SW-TEE',
			'Everyday Tee',
			'Heavyweight cotton tee, pre-shrunk, printed in Bengaluru.',
			799,
			$categories['apparel'],
			'#1f6f8b',
			[
				'Size'   => [ 'S', 'M', 'L', 'XL' ],
				'Colour' => [ 'Black', 'Sand' ],
			]
		);
		$this->simple(
			'SW-MUG',
			'Stoneware Mug',
			'A 350 ml stoneware mug, dishwasher safe.',
			449,
			$categories['home'],
			'#c0603a',
			[
				'weight' => '0.4',
				'length' => '12',
				'width'  => '9',
				'height' => '10',
				'stock'  => 40,
			]
		);
		$this->simple(
			'SW-NOTE',
			'Dot Grid Notebook',
			'A5, 160 pages of 100 gsm paper, lay-flat binding.',
			349,
			$categories['home'],
			'#5b6b3a',
			[
				'weight' => '0.3',
				'length' => '21',
				'width'  => '15',
				'height' => '2',
				'stock'  => 120,
			]
		);
		$this->digital( 'SW-EBOOK', 'Running a Small Shop Online', 'A short guide to pricing, shipping and returns for a first store. PDF, 24 pages.', 299, $categories['books'], '#3b3b98', 'guide.pdf', $this->media->pdf( 'Running a Small Shop Online' ) );
		$this->digital( 'SW-ICONS', 'Storefront Icon Set', '120 SVG icons for navigation, cart and account screens. MIT licensed.', 999, $categories['software'], '#2d6a4f', 'icons.zip', $this->media->zip( 'storefront-icons' ) );

		$coupons = $this->coupon( 'WELCOME10', 10 );

		return [
			'categories' => count( $categories ),
			'products'   => 5,
			'coupons'    => $coupons,
		];
	}

	private function category( string $name, string $slug ): int {
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( $term ) {
			return (int) $term->term_id;
		}
		$created = wp_insert_term( $name, 'product_cat', [ 'slug' => $slug ] );
		return is_wp_error( $created ) ? 0 : (int) $created['term_id'];
	}

	private function simple( string $sku, string $name, string $description, int $price, int $category, string $colour, array $shipping ): void {
		if ( wc_get_product_id_by_sku( $sku ) ) {
			return;
		}
		$product = new WC_Product_Simple();
		$this->fill( $product, $sku, $name, $description, $price, $category, $colour );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( $shipping['stock'] );
		$product->set_weight( $shipping['weight'] );
		$product->set_length( $shipping['length'] );
		$product->set_width( $shipping['width'] );
		$product->set_height( $shipping['height'] );
		$product->save();
	}

	private function digital( string $sku, string $name, string $description, int $price, int $category, string $colour, string $fileName, string $path ): void {
		if ( wc_get_product_id_by_sku( $sku ) ) {
			return;
		}
		$product = new WC_Product_Simple();
		$this->fill( $product, $sku, $name, $description, $price, $category, $colour );
		$product->set_virtual( true );
		$product->set_downloadable( true );
		$product->set_downloads(
			[
				[
					'name' => $fileName,
					'file' => $path,
				],
			]
		);
		$product->set_download_limit( 5 );
		$product->set_download_expiry( 365 );
		$product->save();
	}

	private function variable( string $sku, string $name, string $description, int $price, int $category, string $colour, array $options ): void {
		if ( wc_get_product_id_by_sku( $sku ) ) {
			return;
		}
		$product = new WC_Product_Variable();
		$this->fill( $product, $sku, $name, $description, $price, $category, $colour );
		$attributes = [];
		$position   = 0;
		foreach ( $options as $label => $values ) {
			$attribute = new WC_Product_Attribute();
			$attribute->set_name( $label );
			$attribute->set_options( $values );
			$attribute->set_position( $position++ );
			$attribute->set_visible( true );
			$attribute->set_variation( true );
			$attributes[] = $attribute;
		}
		$product->set_attributes( $attributes );
		$parent = $product->save();

		$combinations = $this->combinations( $options );
		foreach ( $combinations as $index => $combination ) {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $parent );
			$variation->set_sku( $sku . '-' . $index );
			$variation->set_regular_price( (string) $price );
			$variation->set_manage_stock( true );
			$variation->set_stock_quantity( 15 );
			$variation->set_weight( '0.25' );
			$variation->set_attributes( array_change_key_case( array_combine( array_keys( $options ), $combination ), CASE_LOWER ) );
			$variation->save();
		}
		\WC_Product_Variable::sync( $parent );
	}

	private function fill( \WC_Product $product, string $sku, string $name, string $description, int $price, int $category, string $colour ): void {
		$product->set_name( $name );
		$product->set_sku( $sku );
		$product->set_regular_price( (string) $price );
		$product->set_description( $description );
		$product->set_short_description( wp_trim_words( $description, 12 ) );
		$product->set_status( 'publish' );
		$product->set_category_ids( [ $category ] );
		$product->set_image_id( $this->media->image( $name, $colour ) );
	}

	private function combinations( array $options ): array {
		$result = [ [] ];
		foreach ( $options as $values ) {
			$next = [];
			foreach ( $result as $prefix ) {
				foreach ( $values as $value ) {
					$next[] = [ ...$prefix, $value ];
				}
			}
			$result = $next;
		}
		return $result;
	}

	private function coupon( string $code, int $percent ): int {
		if ( wc_get_coupon_id_by_code( $code ) ) {
			return 1;
		}
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( $percent );
		$coupon->set_individual_use( true );
		$coupon->set_usage_limit_per_user( 1 );
		$coupon->save();
		return 1;
	}
}
