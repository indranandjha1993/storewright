<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Platform\Brand;

use PHPUnit\Framework\TestCase;
use Storewright\Platform\Brand\Palette;
use Storewright\Platform\Brand\Settings;

final class PaletteTest extends TestCase {

	public function test_the_default_palette_matches_the_theme(): void {
		$palette = Palette::from( Settings::DEFAULTS );

		self::assertSame( '#fafaf7', $palette['base'] );
		self::assertSame( '#ffffff', $palette['surface'] );
		self::assertSame( '#15171a', $palette['contrast'] );
		self::assertSame( '#0f5c4d', $palette['accent'] );
		self::assertSame( '#ffffff', $palette['onAccent'] );
		self::assertSame( '#b4472b', $palette['sale'] );
		self::assertSame( '#dfdfdc', $palette['line'] );
	}

	public function test_a_dark_background_lifts_the_surface_and_hover(): void {
		$palette = Palette::from(
			[
				...Settings::DEFAULTS,
				'background' => '#101214',
				'text'       => '#f2f2ef',
				'accent'     => '#2f9e7a',
			]
		);

		self::assertSame( '#1e2022', $palette['surface'] );
		self::assertSame( '#48aa8a', $palette['accentHover'] );
		self::assertSame( '#15171a', $palette['onAccent'] );
	}

	public function test_corner_presets(): void {
		self::assertSame( '999px', Palette::radii( 'round' )['button'] );
		self::assertSame( '2px', Palette::radii( 'sharp' )['radius'] );
		self::assertSame( '10px', Palette::radii( 'anything' )['radius'] );
	}
}
