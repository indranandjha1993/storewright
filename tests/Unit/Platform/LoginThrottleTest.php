<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Platform;

use Brain\Monkey\Functions;
use Storewright\Platform\LoginThrottle;
use Storewright\Tests\TestCase;
use WP_Error;

final class LoginThrottleTest extends TestCase {

	private array $store = [];

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
		Functions\when( 'get_transient' )->alias( fn( string $key ) => $this->store[ $key ] ?? false );
		Functions\when( 'set_transient' )->alias(
			function ( string $key, mixed $value ): bool {
				$this->store[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_transient' )->alias(
			function ( string $key ): bool {
				unset( $this->store[ $key ] );
				return true;
			}
		);
		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
	}

	public function test_the_fifth_failure_locks_the_name_from_that_address(): void {
		for ( $i = 0; $i < 4; $i++ ) {
			LoginThrottle::failed( 'asha' );
		}
		self::assertNull( LoginThrottle::gate( null, 'asha' ) );

		LoginThrottle::failed( 'Asha' );

		$gate = LoginThrottle::gate( null, 'asha' );
		self::assertInstanceOf( WP_Error::class, $gate );
		self::assertSame( 'storewright_locked', $gate->get_error_code() );
	}

	public function test_another_address_or_a_login_clears_it(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			LoginThrottle::failed( 'asha' );
		}
		$_SERVER['REMOTE_ADDR'] = '198.51.100.2';
		self::assertFalse( LoginThrottle::locked( 'asha' ) );

		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
		LoginThrottle::clear( 'asha' );
		self::assertFalse( LoginThrottle::locked( 'asha' ) );
	}
}
