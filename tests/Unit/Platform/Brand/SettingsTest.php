<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Platform\Brand;

use Brain\Monkey\Functions;
use InvalidArgumentException;
use Storewright\Platform\Brand\Settings;
use Storewright\Tests\TestCase;

final class SettingsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
	}

	public function test_missing_or_broken_stored_values_fall_back_to_defaults(): void {
		Functions\when( 'get_option' )->justReturn(
			[
				'accent'      => '#ABC',
				'text'        => 'not a colour',
				'headingFont' => 'comic-sans',
				'radius'      => 'bouncy',
			]
		);

		$settings = Settings::get();

		self::assertSame( '#aabbcc', $settings['accent'] );
		self::assertSame( '#15171a', $settings['text'] );
		self::assertSame( 'fraunces', $settings['headingFont'] );
		self::assertSame( 'soft', $settings['radius'] );
	}

	public function test_validation_accepts_a_full_set(): void {
		$settings = Settings::validate(
			[
				'accent'      => '#7A1F5C',
				'background'  => '#FFFFFF',
				'text'        => '#111111',
				'sale'        => '#C0392B',
				'headingFont' => 'fraunces',
				'bodyFont'    => 'inter',
				'radius'      => 'round',
			]
		);

		self::assertSame( '#7a1f5c', $settings['accent'] );
		self::assertSame( 'fraunces', $settings['headingFont'] );
		self::assertSame( 'round', $settings['radius'] );
	}

	public function test_low_contrast_text_is_refused(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'more contrast' );

		Settings::validate(
			[
				...Settings::DEFAULTS,
				'text'       => '#cccccc',
				'background' => '#ffffff',
			]
		);
	}

	public function test_a_bad_colour_names_the_setting(): void {
		$this->expectExceptionMessage( 'The sale colour must be a hex colour such as #0F5C4D.' );

		Settings::validate(
			[
				...Settings::DEFAULTS,
				'sale' => 'tomato',
			]
		);
	}

	public function test_an_unknown_font_or_radius_is_refused(): void {
		$this->expectException( InvalidArgumentException::class );

		Settings::validate(
			[
				...Settings::DEFAULTS,
				'bodyFont' => 'papyrus',
			]
		);
	}
}
