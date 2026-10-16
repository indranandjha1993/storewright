<?php

declare(strict_types=1);

namespace Storewright\Core\Seed;

use WC_Customer;

final class Customers {

	private const PEOPLE = [
		[ 'asha', 'Asha', 'Menon', 'asha@example.com', '560001', 'KA', 'Bengaluru' ],
		[ 'rohan', 'Rohan', 'Deshpande', 'rohan@example.com', '411001', 'MH', 'Pune' ],
	];

	public function run(): int {
		foreach ( self::PEOPLE as [$login, $first, $last, $email, $postcode, $state, $city] ) {
			if ( email_exists( $email ) ) {
				continue;
			}
			$customer = new WC_Customer();
			$customer->set_username( $login );
			$customer->set_email( $email );
			$customer->set_password( wp_generate_password( 20 ) );
			$customer->set_first_name( $first );
			$customer->set_last_name( $last );
			$customer->set_billing_first_name( $first );
			$customer->set_billing_last_name( $last );
			$customer->set_billing_email( $email );
			$customer->set_billing_country( 'IN' );
			$customer->set_billing_state( $state );
			$customer->set_billing_city( $city );
			$customer->set_billing_postcode( $postcode );
			$customer->set_billing_address_1( '12 Market Street' );
			$customer->set_shipping_first_name( $first );
			$customer->set_shipping_last_name( $last );
			$customer->set_shipping_country( 'IN' );
			$customer->set_shipping_state( $state );
			$customer->set_shipping_city( $city );
			$customer->set_shipping_postcode( $postcode );
			$customer->set_shipping_address_1( '12 Market Street' );
			$customer->save();
		}
		return count( self::PEOPLE );
	}
}
