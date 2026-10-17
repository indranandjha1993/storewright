<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

use WC_Shipping_Method;

final class DeliveryMethods {

	public static function describe( WC_Shipping_Method $method ): array {
		return [
			'id'        => (int) $method->get_instance_id(),
			'type'      => $method->id,
			'title'     => $method->get_title(),
			'enabled'   => $method->is_enabled(),
			'cost'      => (string) $method->get_option( 'cost', '' ),
			'minAmount' => (string) $method->get_option( 'min_amount', '' ),
			'charge'    => self::charge( $method ),
			'editable'  => self::editable( $method ),
		];
	}

	public static function offered( int $zoneId ): array {
		$zone = \WC_Shipping_Zones::get_zone( $zoneId );
		if ( ! $zone instanceof \WC_Shipping_Zone ) {
			return [];
		}
		return array_values(
			array_map(
				static fn( WC_Shipping_Method $method ): array => [
					'title'  => $method->get_title(),
					'charge' => self::charge( $method ),
				],
				$zone->get_shipping_methods( true )
			)
		);
	}

	public static function charge( WC_Shipping_Method $method ): string {
		if ( $method->id === 'free_shipping' ) {
			$minimum = (string) $method->get_option( 'min_amount', '' );
			return in_array( $method->get_option( 'requires', '' ), [ 'min_amount', 'either', 'both' ], true ) && (float) $minimum > 0
				/* translators: %s: order amount */
				? sprintf( __( 'Free on orders of %s or more', 'storewright' ), self::money( $minimum ) )
				: __( 'Free', 'storewright' );
		}
		$cost = (string) $method->get_option( 'cost', '' );
		if ( ! is_numeric( $cost ) ) {
			return $cost === '' ? __( 'Free', 'storewright' ) : $cost;
		}
		return (float) $cost > 0 ? self::money( $cost ) : __( 'Free', 'storewright' );
	}

	public static function editable( WC_Shipping_Method $method ): bool {
		$cost = (string) $method->get_option( 'cost', '' );
		return match ( $method->id ) {
			'flat_rate'     => $cost === '' || is_numeric( $cost ),
			'free_shipping' => in_array( $method->get_option( 'requires', '' ), [ '', 'min_amount' ], true ),
			default         => false,
		};
	}

	public static function configure( WC_Shipping_Method $method, array $input ): void {
		$method->init_instance_settings();
		$settings = [
			...$method->instance_settings,
			'title' => $input['title'],
		];
		if ( $method->id === 'flat_rate' ) {
			$settings['cost'] = $input['cost'];
		} else {
			$settings['min_amount'] = $input['minAmount'];
			$settings['requires']   = (float) $input['minAmount'] > 0 ? 'min_amount' : '';
		}
		update_option( $method->get_instance_option_key(), $settings );
	}

	private static function money( string $amount ): string {
		return html_entity_decode( wp_strip_all_tags( wc_price( (float) $amount ) ), ENT_QUOTES, 'UTF-8' );
	}
}
