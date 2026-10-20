<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Platform;

use PHPUnit\Framework\TestCase;
use Storewright\Platform\Required;

final class RequiredTest extends TestCase {

	public function test_a_required_plugin_that_was_active_stays_active(): void {
		$old = [ 'woocommerce/woocommerce.php', 'storewright-core/storewright-core.php' ];

		self::assertSame( [ 'woocommerce/woocommerce.php', 'storewright-core/storewright-core.php' ], Required::protect( [ 'woocommerce/woocommerce.php' ], $old ) );
		self::assertSame( [ 'woocommerce/woocommerce.php', 'storewright-core/storewright-core.php' ], Required::protect( [], $old ) );
	}

	public function test_plugins_can_still_be_switched_on_and_others_switched_off(): void {
		self::assertSame( [ 'storewright-core/storewright-core.php' ], Required::protect( [ 'storewright-core/storewright-core.php' ], [] ) );
		self::assertSame( [ 'woocommerce/woocommerce.php' ], Required::protect( [ 'woocommerce/woocommerce.php' ], [ 'woocommerce/woocommerce.php', 'hello/hello.php' ] ) );
	}

	public function test_the_theme_cannot_leave_storewright_once_set(): void {
		self::assertSame( 'storewright', Required::keepTemplate( 'twentytwentyfive', 'storewright' ) );
		self::assertSame( 'storewright', Required::keepTemplate( 'storewright', 'twentytwentyfive' ) );
		self::assertSame( 'twentytwentyfive', Required::keepTemplate( 'twentytwentyfive', '' ) );
	}
}
