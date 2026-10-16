<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

use InvalidArgumentException;
use TypeError;

final class AreaInput {

	public const METHOD_TYPES = [ 'flat_rate', 'free_shipping' ];

	private function __construct(
		public readonly string $name,
		public readonly array $regions,
		public readonly array $pins,
		public readonly ?Shape $shape,
		public readonly array $methods,
	) {}

	public static function from( array $data ): self {
		$name = mb_substr( trim( sanitize_text_field( (string) ( $data['name'] ?? '' ) ) ), 0, 200 );
		if ( $name === '' ) {
			throw new InvalidArgumentException( esc_html__( 'Give the area a name.', 'storewright' ) );
		}
		$regions = array_map( self::region( ... ), array_values( (array) ( $data['regions'] ?? [] ) ) );
		return new self(
			$name,
			$regions,
			PinRules::parse( (string) ( $data['pins'] ?? '' ), self::onlyIndia( $regions ) ),
			self::shape( $data['shape'] ?? null ),
			array_map( self::method( ... ), array_values( (array) ( $data['methods'] ?? [] ) ) ),
		);
	}

	private static function region( mixed $region ): array {
		$type  = (string) ( $region['type'] ?? '' );
		$code  = strtoupper( trim( (string) ( $region['code'] ?? '' ) ) );
		$valid = match ( $type ) {
			'country', 'continent' => (bool) preg_match( '/^[A-Z]{2}$/', $code ),
			'state'                => (bool) preg_match( '/^[A-Z]{2}:[A-Z0-9-]{1,10}$/', $code ),
			default                => false,
		};
		if ( ! $valid ) {
			throw new InvalidArgumentException( esc_html__( 'One of the regions is not a country or state the store knows.', 'storewright' ) );
		}
		return [ $type, $code ];
	}

	private static function onlyIndia( array $regions ): bool {
		$abroad = array_filter( $regions, static fn( array $region ): bool => $region[1] !== 'IN' && ! str_starts_with( $region[1], 'IN:' ) );
		return $regions !== [] && $abroad === [];
	}

	private static function shape( mixed $shape ): ?Shape {
		if ( empty( $shape ) ) {
			return null;
		}
		try {
			return ShapeCodec::fromArray( (array) $shape );
		} catch ( TypeError ) {
			throw new InvalidArgumentException( esc_html__( 'The shape on the map could not be read. Draw it again.', 'storewright' ) );
		}
	}

	private static function method( mixed $method ): array {
		$id    = max( 0, (int) ( $method['id'] ?? 0 ) );
		$type  = (string) ( $method['type'] ?? '' );
		$title = trim( sanitize_text_field( (string) ( $method['title'] ?? '' ) ) );
		if ( $id === 0 && ! in_array( $type, self::METHOD_TYPES, true ) ) {
			throw new InvalidArgumentException( esc_html__( 'A new delivery method is either a delivery charge or free delivery.', 'storewright' ) );
		}
		if ( $id === 0 && $title === '' ) {
			throw new InvalidArgumentException( esc_html__( 'Give each delivery method the name shoppers will see.', 'storewright' ) );
		}
		$cost      = trim( (string) ( $method['cost'] ?? '' ) );
		$minAmount = trim( (string) ( $method['minAmount'] ?? '' ) );
		foreach ( [ $cost, $minAmount ] as $amount ) {
			if ( $amount !== '' && ( ! is_numeric( $amount ) || (float) $amount < 0 ) ) {
				/* translators: %s: delivery method name */
				throw new InvalidArgumentException( esc_html( sprintf( __( 'The amount for %s must be a number such as 49, or left empty.', 'storewright' ), $title ) ) );
			}
		}
		return [
			'id'        => $id,
			'type'      => $type,
			'title'     => $title,
			'enabled'   => (bool) ( $method['enabled'] ?? true ),
			'cost'      => $cost,
			'minAmount' => $minAmount,
		];
	}
}
