<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Platform;

use Brain\Monkey\Functions;
use Storewright\Platform\Hardening;
use Storewright\Tests\TestCase;

final class HardeningTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'is_ssl' )->justReturn( false );
	}

	public function test_headers_drop_pingback_and_add_browser_protections(): void {
		$headers = Hardening::headers(
			[
				'X-Pingback' => 'http://example.test/xmlrpc.php',
				'Link'       => 'rel',
			]
		);

		self::assertArrayNotHasKey( 'X-Pingback', $headers );
		self::assertSame( 'rel', $headers['Link'] );
		self::assertSame( 'nosniff', $headers['X-Content-Type-Options'] );
		self::assertSame( 'SAMEORIGIN', $headers['X-Frame-Options'] );
		self::assertSame( 'strict-origin-when-cross-origin', $headers['Referrer-Policy'] );
	}

	public function test_guests_cannot_see_the_users_routes(): void {
		\Brain\Monkey\Functions\when( 'is_user_logged_in' )->justReturn( false );
		$endpoints = [
			'/wp/v2/users'               => [],
			'/wp/v2/users/(?P<id>[\d]+)' => [],
			'/wp/v2/posts'               => [],
		];

		self::assertSame( [ '/wp/v2/posts' ], array_keys( Hardening::hideUsersFromGuests( $endpoints ) ) );
	}

	public function test_logged_in_users_keep_the_users_routes(): void {
		\Brain\Monkey\Functions\when( 'is_user_logged_in' )->justReturn( true );
		$endpoints = [
			'/wp/v2/users' => [],
			'/wp/v2/posts' => [],
		];

		self::assertSame( $endpoints, Hardening::hideUsersFromGuests( $endpoints ) );
	}

	public function test_indexing_is_off_when_the_environment_says_so(): void {
		self::assertSame( '1', Hardening::indexing( '1' ) );
		define( 'DISALLOW_INDEXING', true );
		self::assertSame( '0', Hardening::indexing( '1' ) );
	}
}
