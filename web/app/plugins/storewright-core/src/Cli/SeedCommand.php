<?php

declare(strict_types=1);

namespace Storewright\Core\Cli;

use Storewright\Core\Seed\Catalogue;
use Storewright\Core\Seed\Customers;
use WP_CLI;

final class SeedCommand {

	public function __invoke( array $args, array $assoc ): void {
		if ( wp_get_environment_type() === 'production' && empty( $assoc['yes'] ) ) {
			WP_CLI::error( 'Refusing to seed a production store without --yes.' );
		}
		$catalogue = ( new Catalogue() )->run();
		$customers = ( new Customers() )->run();
		WP_CLI::success(
			sprintf(
				'%d categories, %d products, %d coupon(s), %d customers in place.',
				$catalogue['categories'],
				$catalogue['products'],
				$catalogue['coupons'],
				$customers
			)
		);
	}
}
