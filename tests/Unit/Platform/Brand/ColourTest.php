<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Platform\Brand;

use PHPUnit\Framework\TestCase;
use Storewright\Platform\Brand\Colour;

final class ColourTest extends TestCase {

	public function test_short_and_long_hex_normalise_the_same_way(): void {
		self::assertSame( '#ffffff', Colour::normalise( '#FFF' ) );
		self::assertSame( '#0f5c4d', Colour::normalise( '#0F5C4D' ) );
		self::assertTrue( Colour::isHex( '#abc' ) );
		self::assertFalse( Colour::isHex( 'red' ) );
		self::assertFalse( Colour::isHex( '#0F5C4D;}' ) );
	}

	public function test_mixing_darkening_and_rgb(): void {
		self::assertSame( '#000000', Colour::darken( '#fff', 1.0 ) );
		self::assertSame( '#0d5144', Colour::darken( '#0F5C4D', 0.12 ) );
		self::assertSame( '#808080', Colour::mix( '#000000', '#ffffff', 0.5 ) );
		self::assertSame( '15,92,77', Colour::rgb( '#0F5C4D' ) );
	}

	public function test_readable_text_picks_white_on_dark_and_ink_on_light(): void {
		self::assertSame( '#ffffff', Colour::readableOn( '#0f5c4d' ) );
		self::assertSame( '#15171a', Colour::readableOn( '#ffd54a' ) );
		self::assertTrue( Colour::isLight( '#fafaf7' ) );
		self::assertFalse( Colour::isLight( '#15171a' ) );
	}

	public function test_contrast_ratio_matches_wcag(): void {
		self::assertEqualsWithDelta( 21.0, Colour::contrast( '#000000', '#ffffff' ), 0.01 );
		self::assertEqualsWithDelta( 1.0, Colour::contrast( '#777777', '#777777' ), 0.01 );
	}
}
