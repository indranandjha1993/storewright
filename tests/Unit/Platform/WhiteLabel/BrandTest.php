<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Platform\WhiteLabel;

use Brain\Monkey\Functions;
use Storewright\Platform\WhiteLabel\Brand;
use Storewright\Tests\TestCase;

final class BrandTest extends TestCase {

	public function test_the_accent_comes_from_the_theme_palette(): void {
		Functions\when( 'wp_get_global_settings' )->justReturn(
			[
				[
					'slug'  => 'base',
					'color' => '#FAFAF7',
				],
				[
					'slug'  => 'accent',
					'color' => '#7A1F5C',
				],
			]
		);

		self::assertSame( '#7A1F5C', Brand::accent() );
	}

	public function test_an_accent_that_is_not_a_plain_hex_colour_is_ignored(): void {
		Functions\when( 'wp_get_global_settings' )->justReturn(
			[
				[
					'slug'  => 'accent',
					'color' => 'red;}body{display:none',
				],
			]
		);

		self::assertSame( '#0F5C4D', Brand::accent() );
	}

	public function test_darken_and_rgb_handle_short_and_long_hex(): void {
		self::assertSame( '#000000', Brand::darken( '#fff', 1.0 ) );
		self::assertSame( '#0e5345', Brand::darken( '#0F5C4D', 0.1 ) );
		self::assertSame( '15,92,77', Brand::rgb( '#0F5C4D' ) );
		self::assertSame( '255,255,255', Brand::rgb( '#fff' ) );
	}
}
