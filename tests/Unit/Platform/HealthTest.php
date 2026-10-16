<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Platform;

use Brain\Monkey\Functions;
use Storewright\Platform\Health;
use Storewright\Tests\TestCase;

final class HealthTest extends TestCase
{
	public function test_version_reports_build_and_runtime(): void
	{
		putenv('STOREWRIGHT_VERSION=1.2.3');
		putenv('GIT_SHA=abc123');
		Functions\when('get_bloginfo')->justReturn('7.1.3');
		Functions\when('wp_get_environment_type')->justReturn('production');

		$version = Health::version();

		self::assertSame('1.2.3', $version['version']);
		self::assertSame('abc123', $version['sha']);
		self::assertSame('7.1.3', $version['wordpress']);
		self::assertSame('production', $version['environment']);
		putenv('STOREWRIGHT_VERSION');
		putenv('GIT_SHA');
	}

	public function test_report_is_unhealthy_when_the_database_does_not_answer(): void
	{
		$GLOBALS['wpdb'] = new class {
			public function get_var(string $query): ?string
			{
				return null;
			}
		};

		$report = Health::report();

		self::assertFalse($report['ok']);
		self::assertFalse($report['checks']['database']);
		unset($GLOBALS['wpdb']);
	}
}
