<?php

declare(strict_types=1);

namespace Storewright\Platform;

spl_autoload_register(static function (string $class): void {
	$prefix = __NAMESPACE__ . '\\';
	if (!str_starts_with($class, $prefix)) {
		return;
	}
	$file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
	if (is_file($file)) {
		require $file;
	}
});

const VERSION = '0.1.0';

Health::register();
Hardening::register();
Mail::register();
GatewayCredentials::register();
