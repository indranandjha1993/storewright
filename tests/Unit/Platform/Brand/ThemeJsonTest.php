<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Platform\Brand;

use Brain\Monkey\Functions;
use Storewright\Platform\Brand\Settings;
use Storewright\Platform\Brand\ThemeJson;
use Storewright\Tests\TestCase;

final class ThemeJsonTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
		Functions\when( 'get_theme_file_uri' )->alias( static fn( string $path ): string => "/t/{$path}" );
	}

	public function test_the_palette_and_corners_follow_the_settings(): void {
		$data    = ThemeJson::data(
			[
				...Settings::DEFAULTS,
				'accent' => '#7a1f5c',
				'radius' => 'round',
			]
		);
		$palette = array_column( $data['settings']['color']['palette'], 'color', 'slug' );

		self::assertSame( '#7a1f5c', $palette['accent'] );
		self::assertSame( '#ffffff', $palette['on-accent'] );
		self::assertSame( '#fafaf7', $palette['base'] );
		self::assertSame( '999px', $data['settings']['custom']['radiusButton'] );
		self::assertSame( 'var(--wp--custom--radius-button)', $data['styles']['elements']['button']['border']['radius'] );
	}

	public function test_fonts_are_declared_once_even_when_shared(): void {
		$data     = ThemeJson::data(
			[
				...Settings::DEFAULTS,
				'headingFont' => 'inter',
				'bodyFont'    => 'inter',
			]
		);
		$families = array_column( $data['settings']['typography']['fontFamilies'], null, 'slug' );

		self::assertArrayHasKey( 'fontFace', $families['sans'] );
		self::assertArrayNotHasKey( 'fontFace', $families['heading'] );
		self::assertSame( $families['sans']['fontFamily'], $families['heading']['fontFamily'] );
	}

	public function test_a_different_heading_font_brings_its_own_faces(): void {
		$data     = ThemeJson::data(
			[
				...Settings::DEFAULTS,
				'headingFont' => 'fraunces',
				'bodyFont'    => 'system',
			]
		);
		$families = array_column( $data['settings']['typography']['fontFamilies'], null, 'slug' );

		self::assertArrayNotHasKey( 'fontFace', $families['sans'] );
		self::assertSame( '/t/assets/fonts/fraunces-latin-wght-normal.woff2', $families['heading']['fontFace'][0]['src'][0] );
	}
}
