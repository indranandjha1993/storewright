<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Platform\WhiteLabel;

use PHPUnit\Framework\TestCase;
use Storewright\Platform\WhiteLabel\Wording;

final class WordingTest extends TestCase {

	public function test_product_names_become_the_store_and_the_brand(): void {
		self::assertSame( 'Store Status', Wording::rewrite( 'WooCommerce Status', 'Kirana Co' ) );
		self::assertSame( 'Thank you for creating with Kirana Co.', Wording::rewrite( 'Thank you for creating with WordPress.', 'Kirana Co' ) );
	}

	public function test_title_formats_lose_the_product_suffix(): void {
		self::assertSame( '%1$s &lsaquo; %2$s', Wording::rewrite( '%1$s &lsaquo; %2$s &#8212; WordPress', 'Kirana Co' ) );
		self::assertSame( '%1$s ‹ %2$s', Wording::rewrite( '%1$s ‹ %2$s ' . "\u{2014}" . ' WooCommerce', 'Kirana Co' ) );
	}

	public function test_domains_are_left_alone(): void {
		self::assertSame( 'Docs on WooCommerce.com and WordPress.org', Wording::rewrite( 'Docs on WooCommerce.com and WordPress.org', 'Kirana Co' ) );
	}

	public function test_text_without_either_name_is_returned_as_is(): void {
		self::assertSame( 'Orders', Wording::rewrite( 'Orders', 'Kirana Co' ) );
		self::assertSame( 'woocommerce_settings', Wording::rewrite( 'woocommerce_settings', 'Kirana Co' ) );
	}
}
