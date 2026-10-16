<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Platform;

use Brain\Monkey\Functions;
use Storewright\Platform\Mail;
use Storewright\Tests\TestCase;

final class MailTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		putenv( 'MAIL_FROM' );
	}

	public function test_a_configured_sender_is_kept(): void {
		self::assertSame( 'orders@shop.example', Mail::fromAddress( 'orders@shop.example' ) );
	}

	public function test_the_localhost_default_is_replaced_with_the_site_domain(): void {
		Functions\when( 'home_url' )->justReturn( 'https://shop.example' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		self::assertSame( 'store@shop.example', Mail::fromAddress( 'wordpress@localhost' ) );
	}

	public function test_a_dotless_host_gets_a_valid_placeholder_domain(): void {
		Functions\when( 'home_url' )->justReturn( 'http://localhost:8082' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		self::assertSame( 'store@storewright.test', Mail::fromAddress( 'wordpress@localhost' ) );
	}

	public function test_mail_from_env_wins(): void {
		putenv( 'MAIL_FROM=hello@shop.example' );
		Functions\when( 'home_url' )->justReturn( 'https://shop.example' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		self::assertSame( 'hello@shop.example', Mail::fromAddress( 'wordpress@localhost' ) );
	}

	public function test_the_default_sender_name_becomes_the_site_name(): void {
		Functions\when( 'get_bloginfo' )->justReturn( 'Asha Home' );

		self::assertSame( 'Asha Home', Mail::fromName( 'WordPress' ) );
		self::assertSame( 'Support', Mail::fromName( 'Support' ) );
	}
}
