<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Platform\Brand;

use Brain\Monkey\Functions;
use Storewright\Platform\Brand\Emails;
use Storewright\Tests\TestCase;

final class EmailsTest extends TestCase {

	public function test_email_colours_come_from_the_palette(): void {
		Functions\when( 'get_option' )->alias( static fn( string $name ) => $name === 'storewright_brand' ? [ 'accent' => '#7a1f5c' ] : false );

		self::assertSame( '#7a1f5c', Emails::colour( 'accent' ) );
		self::assertSame( '#ffffff', Emails::colour( 'surface' ) );
		self::assertSame( '#15171a', Emails::colour( 'contrast' ) );
	}
}
