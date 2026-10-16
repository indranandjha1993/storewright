<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

use InvalidArgumentException;
use OutOfBoundsException;
use WC_Cache_Helper;
use WC_Shipping_Method;
use WC_Shipping_Zone;
use WC_Shipping_Zones;

final class Areas {

	private ShapeStore $shapes;

	public function __construct( private readonly \wpdb $db ) {
		$this->shapes = new ShapeStore( $db );
	}

	public function all(): array {
		$shapes = $this->shapes->all();
		$zones  = [ ...array_map( static fn( int $id ): WC_Shipping_Zone => new WC_Shipping_Zone( $id ), $this->ids() ), new WC_Shipping_Zone( 0 ) ];
		$areas  = [];
		foreach ( $zones as $index => $zone ) {
			$areas[] = $this->area( $zone, $index + 1, $shapes[ $zone->get_id() ] ?? null );
		}
		return $areas;
	}

	public function create( AreaInput $input ): int {
		$this->checkMethods( [], $input->methods );
		$zone = new WC_Shipping_Zone();
		$this->place( $zone, $input );
		$this->saveMethods( $zone, [], $input->methods );
		$this->order( [ $zone->get_id(), ...array_diff( $this->ids(), [ $zone->get_id() ] ) ] );
		return $zone->get_id();
	}

	public function update( int $id, AreaInput $input ): void {
		$zone     = $this->zone( $id );
		$existing = $zone->get_shipping_methods();
		$this->checkMethods( $existing, $input->methods );
		if ( $id !== 0 ) {
			$this->place( $zone, $input );
		}
		$this->saveMethods( $zone, $existing, $input->methods );
		WC_Cache_Helper::get_transient_version( 'shipping', true );
		WooZones::forget();
	}

	public function delete( int $id ): void {
		if ( $id === 0 ) {
			throw new InvalidArgumentException( esc_html__( 'Everywhere else always stays at the end of the list.', 'storewright' ) );
		}
		$this->zone( $id );
		WC_Shipping_Zones::delete_zone( $id );
		WooZones::forget();
	}

	public function reorder( array $ids ): void {
		$ids     = array_values( array_map( 'intval', $ids ) );
		$current = $this->ids();
		if ( count( $ids ) !== count( $current ) || array_diff( $current, $ids ) !== [] ) {
			throw new InvalidArgumentException( esc_html__( 'The list changed while you were sorting it. Reload the page and try again.', 'storewright' ) );
		}
		$this->order( $ids );
	}

	private function area( WC_Shipping_Zone $zone, int $position, ?Shape $shape ): array {
		$regions = [];
		$pins    = [];
		foreach ( $zone->get_zone_locations() as $location ) {
			if ( $location->type === 'postcode' ) {
				$pins[] = $location->code;
				continue;
			}
			$regions[] = Places::region( $location->type, $location->code );
		}
		return [
			'id'       => $zone->get_id(),
			'name'     => $zone->get_id() === 0 ? __( 'Everywhere else', 'storewright' ) : $zone->get_zone_name(),
			'position' => $position,
			'regions'  => $regions,
			'pins'     => $pins,
			'shape'    => $shape?->toArray(),
			'methods'  => array_values( array_map( [ DeliveryMethods::class, 'describe' ], $zone->get_shipping_methods() ) ),
		];
	}

	private function place( WC_Shipping_Zone $zone, AreaInput $input ): void {
		$zone->set_zone_name( $input->name );
		$zone->set_locations(
			[
				...array_map(
					static fn( array $region ): array => [
						'type' => $region[0],
						'code' => $region[1],
					],
					$input->regions
				),
				...array_map(
					static fn( string $pin ): array => [
						'type' => 'postcode',
						'code' => $pin,
					],
					$input->pins
				),
			]
		);
		$zone->save();
		if ( $input->shape ) {
			$this->shapes->put( $zone->get_id(), $input->shape );
		} else {
			$this->shapes->clear( $zone->get_id() );
		}
	}

	private function checkMethods( array $existing, array $inputs ): void {
		foreach ( $inputs as $input ) {
			if ( $input['id'] === 0 ) {
				continue;
			}
			$method = $existing[ $input['id'] ] ?? null;
			if ( ! $method instanceof WC_Shipping_Method ) {
				throw new InvalidArgumentException( esc_html__( 'A delivery method was removed elsewhere. Reload the page and try again.', 'storewright' ) );
			}
			if ( $input['title'] === '' && DeliveryMethods::editable( $method ) ) {
				throw new InvalidArgumentException( esc_html__( 'Give each delivery method the name shoppers will see.', 'storewright' ) );
			}
		}
	}

	private function saveMethods( WC_Shipping_Zone $zone, array $existing, array $inputs ): void {
		$kept = [];
		foreach ( $inputs as $order => $input ) {
			$method = $input['id'] === 0 ? WC_Shipping_Zones::get_shipping_method( $zone->add_shipping_method( $input['type'] ) ) : $existing[ $input['id'] ];
			if ( DeliveryMethods::editable( $method ) ) {
				DeliveryMethods::configure( $method, $input );
			}
			$this->methodRow( $method, $order + 1, $input['enabled'] );
			$kept[] = (int) $method->get_instance_id();
		}
		foreach ( array_diff( array_keys( $existing ), $kept ) as $removed ) {
			$zone->delete_shipping_method( (int) $removed );
		}
	}

	private function methodRow( WC_Shipping_Method $method, int $order, bool $enabled ): void {
		$this->db->update(
			$this->db->prefix . 'woocommerce_shipping_zone_methods',
			[
				'method_order' => $order,
				'is_enabled'   => (int) $enabled,
			],
			[ 'instance_id' => $method->get_instance_id() ],
			[ '%d', '%d' ],
			[ '%d' ]
		);
	}

	private function order( array $ids ): void {
		foreach ( array_values( $ids ) as $position => $id ) {
			$zone = new WC_Shipping_Zone( (int) $id );
			$zone->set_zone_order( $position );
			$zone->save();
		}
		WooZones::forget();
	}

	private function zone( int $id ): WC_Shipping_Zone {
		$zone = WC_Shipping_Zones::get_zone( $id );
		if ( ! $zone instanceof WC_Shipping_Zone ) {
			throw new OutOfBoundsException( esc_html__( 'That delivery area no longer exists.', 'storewright' ) );
		}
		return $zone;
	}

	private function ids(): array {
		return array_map( static fn( array $zone ): int => (int) $zone['id'], WC_Shipping_Zones::get_zones( 'admin' ) );
	}
}
