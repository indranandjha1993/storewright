<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Platform\Brand;

use Brain\Monkey\Functions;
use Storewright\Platform\Brand\Fonts;
use Storewright\Tests\TestCase;

final class FontsTest extends TestCase {

	public function test_bundled_fonts_point_at_the_theme_files(): void {
		Functions\when( 'get_theme_file_uri' )->alias( static fn( string $path ): string => "https://store.test/themes/storewright/{$path}" );

		$font = Fonts::get( 'manrope' );

		self::assertSame( 'Manrope', $font['name'] );
		self::assertStringStartsWith( "'Manrope Variable'", $font['family'] );
		self::assertCount( 2, $font['faces'] );
		self::assertSame( 'https://store.test/themes/storewright/assets/fonts/manrope-latin-wght-normal.woff2', $font['faces'][0]['src'][0] );
		self::assertSame( '200 800', $font['faces'][0]['fontWeight'] );
	}

	public function test_system_fonts_have_no_files_and_unknown_slugs_fall_back(): void {
		self::assertSame( [], Fonts::get( 'system' )['faces'] );
		self::assertSame( 'system', Fonts::get( 'wingdings' )['slug'] );
		self::assertFalse( Fonts::has( 'wingdings' ) );
	}

	public function test_emails_get_the_closest_safe_family(): void {
		self::assertSame( 'Georgia', Fonts::emailFamily( 'fraunces' ) );
		self::assertSame( 'Helvetica', Fonts::emailFamily( 'inter' ) );
		self::assertStringStartsWith( 'Georgia', Fonts::emailStack( 'playfair-display' ) );
		self::assertStringEndsWith( 'sans-serif', Fonts::emailStack( 'wingdings' ) );
	}

	public function test_font_face_css_is_built_from_the_faces(): void {
		Functions\when( 'get_theme_file_uri' )->alias( static fn( string $path ): string => "https://t/{$path}" );
		Functions\stubEscapeFunctions();

		$css = Fonts::fontFaceCss( Fonts::get( 'inter' )['faces'] );

		self::assertStringContainsString( "@font-face{font-family:'Inter Variable';", $css );
		self::assertStringContainsString( 'url(https://t/assets/fonts/inter-latin-ext-wght-normal.woff2)', $css );
	}
}
