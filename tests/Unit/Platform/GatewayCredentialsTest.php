<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Platform;

use Storewright\Platform\GatewayCredentials;
use Storewright\Tests\TestCase;

final class GatewayCredentialsTest extends TestCase
{
	protected function tearDown(): void
	{
		foreach (['STRIPE_TEST_SECRET_KEY', 'RAZORPAY_KEY_ID', 'RAZORPAY_TEST_KEY_ID', 'RAZORPAY_TEST_KEY_SECRET'] as $name) {
			putenv($name);
		}
		parent::tearDown();
	}

	public function test_environment_values_override_saved_settings(): void
	{
		putenv('STRIPE_TEST_SECRET_KEY=sk_test_env');

		$merged = GatewayCredentials::merge(['test_secret_key' => 'sk_test_saved', 'enabled' => 'yes'], ['test_secret_key' => 'STRIPE_TEST_SECRET_KEY']);

		self::assertSame('sk_test_env', $merged['test_secret_key']);
		self::assertSame('yes', $merged['enabled']);
	}

	public function test_empty_environment_values_leave_settings_alone(): void
	{
		putenv('STRIPE_TEST_SECRET_KEY=');

		$merged = GatewayCredentials::merge(['test_secret_key' => 'sk_test_saved'], ['test_secret_key' => 'STRIPE_TEST_SECRET_KEY']);

		self::assertSame('sk_test_saved', $merged['test_secret_key']);
	}

	public function test_a_missing_option_becomes_an_array_with_a_description(): void
	{
		$merged = GatewayCredentials::merge(false, []);

		self::assertSame(['description' => ''], $merged);
	}

	public function test_razorpay_falls_back_to_test_keys_when_live_keys_are_absent(): void
	{
		putenv('RAZORPAY_TEST_KEY_ID=rzp_test_1');
		putenv('RAZORPAY_TEST_KEY_SECRET=s');

		self::assertSame('RAZORPAY_TEST_KEY_ID', GatewayCredentials::razorpayMap()['key_id']);

		putenv('RAZORPAY_KEY_ID=rzp_live_1');

		self::assertSame('RAZORPAY_KEY_ID', GatewayCredentials::razorpayMap()['key_id']);
	}
}
