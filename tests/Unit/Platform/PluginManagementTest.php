<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Platform;

use PHPUnit\Framework\TestCase;
use Storewright\Platform\PluginManagement;

final class PluginManagementTest extends TestCase {

	protected function tearDown(): void {
		putenv( 'PLUGIN_MANAGEMENT' );
	}

	public function test_management_is_managed_unless_the_server_opens_it(): void {
		self::assertSame( 'managed', PluginManagement::mode() );
		putenv( 'PLUGIN_MANAGEMENT=open' );
		self::assertSame( 'open', PluginManagement::mode() );
		putenv( 'PLUGIN_MANAGEMENT=yes' );
		self::assertSame( 'managed', PluginManagement::mode() );
	}

	public function test_themes_and_core_stay_locked_while_plugins_open(): void {
		self::assertSame( [ 'do_not_allow' ], PluginManagement::caps( [ 'install_themes' ], 'install_themes' ) );
		self::assertSame( [ 'do_not_allow' ], PluginManagement::caps( [ 'update_core' ], 'update_core' ) );
		self::assertSame( [ 'install_plugins' ], PluginManagement::caps( [ 'install_plugins' ], 'install_plugins' ) );
		self::assertSame( [ 'delete_plugins' ], PluginManagement::caps( [ 'delete_plugins' ], 'delete_plugins' ) );
	}

	public function test_required_plugins_never_offer_an_update(): void {
		$transient           = new \stdClass();
		$transient->response = [
			'woocommerce/woocommerce.php' => (object) [ 'new_version' => '99.0.0' ],
			'hello/hello.php'             => (object) [ 'new_version' => '2.0.0' ],
		];

		$filtered = PluginManagement::updates( $transient );

		self::assertSame( [ 'hello/hello.php' ], array_keys( $filtered->response ) );
		self::assertFalse( PluginManagement::autoUpdate( true, (object) [ 'plugin' => 'woocommerce/woocommerce.php' ] ) );
		self::assertTrue( PluginManagement::autoUpdate( true, (object) [ 'plugin' => 'hello/hello.php' ] ) );
		self::assertNull( PluginManagement::updates( null ) );
	}
}
