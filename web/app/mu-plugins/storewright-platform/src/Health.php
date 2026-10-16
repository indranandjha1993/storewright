<?php

declare(strict_types=1);

namespace Storewright\Platform;

final class Health
{
	public static function register(): void
	{
		add_action('init', [self::class, 'respond'], 0);
	}

	public static function respond(): void
	{
		$path = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?');
		if ($path === '/version') {
			self::send(200, self::version());
		}
		if ($path === '/health') {
			$report = self::report();
			self::send($report['ok'] ? 200 : 503, $report);
		}
	}

	public static function version(): array
	{
		return [
			'name' => 'storewright',
			'version' => getenv('STOREWRIGHT_VERSION') ?: VERSION,
			'sha' => getenv('GIT_SHA') ?: 'unknown',
			'wordpress' => get_bloginfo('version'),
			'php' => PHP_VERSION,
			'environment' => wp_get_environment_type(),
		];
	}

	public static function report(): array
	{
		$checks = [
			'database' => self::database(),
			'cache' => self::cache(),
		];
		return ['ok' => !in_array(false, $checks, true), 'checks' => $checks];
	}

	private static function database(): bool
	{
		global $wpdb;
		return (string) $wpdb->get_var('SELECT 1') === '1';
	}

	private static function cache(): bool
	{
		if (!class_exists(\Redis::class)) {
			return true;
		}
		try {
			$redis = new \Redis();
			return $redis->connect((string) (defined('WP_REDIS_HOST') ? WP_REDIS_HOST : 'cache'), 6379, 1.0) && $redis->ping() !== false;
		} catch (\RedisException) {
			return false;
		}
	}

	private static function send(int $status, array $body): never
	{
		status_header($status);
		nocache_headers();
		header('Content-Type: application/json; charset=utf-8');
		echo wp_json_encode($body);
		exit;
	}
}
