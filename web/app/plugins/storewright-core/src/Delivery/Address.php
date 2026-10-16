<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

final class Address {

	public readonly string $country;
	public readonly string $state;
	public readonly string $postcode;

	public function __construct( string $country, string $state = '', string $postcode = '' ) {
		$this->country  = strtoupper( trim( $country ) );
		$this->state    = strtoupper( trim( $state ) );
		$this->postcode = strtoupper( (string) preg_replace( '/\s+/', '', $postcode ) );
	}
}
