<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

final class Places {

	public static function region( string $type, string $code ): array {
		return [
			'type'  => $type,
			'code'  => $code,
			'label' => self::label( $type, $code ),
		];
	}

	public static function options(): array {
		$countries = WC()->countries;
		$base      = $countries->get_base_country();
		$options   = [ self::region( 'country', $base ) ];
		foreach ( array_keys( (array) $countries->get_states( $base ) ) as $state ) {
			$options[] = self::region( 'state', "{$base}:{$state}" );
		}
		foreach ( array_keys( $countries->get_shipping_countries() ) as $country ) {
			if ( $country !== $base ) {
				$options[] = self::region( 'country', (string) $country );
			}
		}
		return $options;
	}

	private static function label( string $type, string $code ): string {
		$countries = WC()->countries;
		$label     = match ( $type ) {
			'country'   => $countries->get_countries()[ $code ] ?? $code,
			'continent' => $countries->get_continents()[ $code ]['name'] ?? $code,
			default     => self::state( $code ),
		};
		return html_entity_decode( (string) $label, ENT_QUOTES, 'UTF-8' );
	}

	private static function state( string $code ): string {
		[ $country, $state ] = array_pad( explode( ':', $code, 2 ), 2, '' );
		$countries           = WC()->countries;
		return sprintf( '%s, %s', $countries->get_states( $country )[ $state ] ?? $state, $countries->get_countries()[ $country ] ?? $country );
	}
}
