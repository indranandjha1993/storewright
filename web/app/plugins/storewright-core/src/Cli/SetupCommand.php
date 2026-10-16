<?php

declare(strict_types=1);

namespace Storewright\Core\Cli;

use Storewright\Core\Setup\StoreDefaults;
use WP_CLI;

final class SetupCommand {

	public function __invoke(): void {
		$result = ( new StoreDefaults() )->apply();
		WP_CLI::success( sprintf( '%d store settings applied, shipping zone %d in place.', $result['options'], $result['zone'] ) );
	}
}
