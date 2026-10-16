<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Platform\Brand;

use Brain\Monkey\Functions;
use Storewright\Platform\Brand\Settings;
use Storewright\Platform\Brand\Tokens;
use Storewright\Tests\TestCase;

final class TokensTest extends TestCase {

	public function test_css_variables_carry_colours_fonts_and_corners(): void {
		Functions\when( 'get_theme_file_uri' )->alias( static fn( string $path ): string => "/t/{$path}" );

		$css = Tokens::css(
			[
				...Settings::DEFAULTS,
				'accent'      => '#7a1f5c',
				'headingFont' => 'fraunces',
				'radius'      => 'sharp',
			]
		);

		self::assertStringStartsWith( ':root{', $css );
		self::assertStringContainsString( '--sw-accent:#7a1f5c;', $css );
		self::assertStringContainsString( '--sw-on-accent:#ffffff;', $css );
		self::assertStringContainsString( "--sw-font-heading:'Fraunces Variable'", $css );
		self::assertStringContainsString( '--sw-radius-button:2px;', $css );
	}

	public function test_admin_css_recolours_the_admin_theme(): void {
		$css = Tokens::adminCss(
			[
				...Settings::DEFAULTS,
				'accent' => '#ffd54a',
			]
		);

		self::assertStringContainsString( '--wp-admin-theme-color:#ffd54a;', $css );
		self::assertStringContainsString( 'color:#15171a', $css );
	}
}
