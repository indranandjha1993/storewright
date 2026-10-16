<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Platform\WhiteLabel;

use Brain\Monkey\Functions;
use Storewright\Platform\WhiteLabel\Brand;
use Storewright\Tests\TestCase;

final class BrandTest extends TestCase {

	public function test_the_accent_comes_from_the_brand_setting(): void {
		Functions\when( 'get_option' )->alias( static fn( string $name ) => $name === 'storewright_brand' ? [ 'accent' => '#7A1F5C' ] : false );

		self::assertSame( '#7a1f5c', Brand::accent() );
	}

	public function test_an_accent_that_is_not_a_plain_hex_colour_falls_back(): void {
		Functions\when( 'get_option' )->alias( static fn( string $name ) => $name === 'storewright_brand' ? [ 'accent' => 'red;}body{display:none' ] : false );

		self::assertSame( '#0f5c4d', Brand::accent() );
	}

	public function test_the_logo_falls_back_to_the_theme_mod(): void {
		Functions\when( 'get_option' )->justReturn( 0 );
		Functions\when( 'get_theme_mod' )->justReturn( 12 );
		Functions\when( 'wp_get_attachment_image_url' )->alias( static fn( int $id ): string => "https://store.test/logo-{$id}.png" );

		self::assertSame( 'https://store.test/logo-12.png', Brand::logoUrl() );
	}
}
