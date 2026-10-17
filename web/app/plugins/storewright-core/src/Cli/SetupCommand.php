<?php

declare(strict_types=1);

namespace Storewright\Core\Cli;

use Storewright\Core\Setup\SampleContent;
use Storewright\Core\Setup\StoreDefaults;
use WP_CLI;

final class SetupCommand {

	public function __invoke(): void {
		$result  = ( new StoreDefaults() )->apply();
		$removed = ( new SampleContent() )->remove();
		WP_CLI::success( sprintf( '%d store settings applied, shipping zone %d and tracking page %d in place, %d sample items removed.', $result['options'], $result['zone'], $result['tracking'], $removed ) );
	}
}
