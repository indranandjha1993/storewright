<?php

declare(strict_types=1);

namespace Storewright\Core\Fulfilment;

final class TrackingEvent {

	public const BOOKED           = 'booked';
	public const PICKED_UP        = 'picked_up';
	public const IN_TRANSIT       = 'in_transit';
	public const OUT_FOR_DELIVERY = 'out_for_delivery';
	public const DELIVERED        = 'delivered';
	public const UNDELIVERED      = 'undelivered';
	public const RETURNING        = 'returning';
	public const RETURNED         = 'returned';
	public const CANCELLED        = 'cancelled';
	public const LOST             = 'lost';

	public const FINAL = [ self::DELIVERED, self::RETURNED, self::CANCELLED, self::LOST ];

	public function __construct(
		public readonly int $at,
		public readonly string $status,
		public readonly string $raw,
		public readonly string $location = ''
	) {}

	public static function fromWords( string $raw ): string {
		$text = strtoupper( $raw );
		return match ( true ) {
			str_contains( $text, 'RTO DELIVERED' ), str_contains( $text, 'RETURNED' ) => self::RETURNED,
			str_contains( $text, 'RTO' ), str_contains( $text, 'RETURN' ) => self::RETURNING,
			str_contains( $text, 'UNDELIVERED' ), str_contains( $text, 'EXCEPTION' ), str_contains( $text, 'FAILED' ) => self::UNDELIVERED,
			str_contains( $text, 'DELIVERED' ) => self::DELIVERED,
			str_contains( $text, 'OUT FOR DELIVERY' ) => self::OUT_FOR_DELIVERY,
			str_contains( $text, 'CANCEL' ) => self::CANCELLED,
			str_contains( $text, 'LOST' ), str_contains( $text, 'DAMAGED' ) => self::LOST,
			str_contains( $text, 'PICKED' ), str_contains( $text, 'PICKUP COMPLETE' ) => self::PICKED_UP,
			str_contains( $text, 'TRANSIT' ), str_contains( $text, 'SHIPPED' ), str_contains( $text, 'REACHED' ), str_contains( $text, 'DISPATCHED' ) => self::IN_TRANSIT,
			default => self::BOOKED,
		};
	}

	public static function words( string $status ): string {
		return match ( $status ) {
			self::PICKED_UP        => __( 'Picked up by the courier', 'storewright' ),
			self::IN_TRANSIT       => __( 'On its way', 'storewright' ),
			self::OUT_FOR_DELIVERY => __( 'Out for delivery', 'storewright' ),
			self::DELIVERED        => __( 'Delivered', 'storewright' ),
			self::UNDELIVERED      => __( 'Delivery attempted', 'storewright' ),
			self::RETURNING        => __( 'Coming back to us', 'storewright' ),
			self::RETURNED         => __( 'Back with us', 'storewright' ),
			self::CANCELLED        => __( 'Cancelled', 'storewright' ),
			self::LOST             => __( 'Lost or damaged in transit', 'storewright' ),
			default                => __( 'Booked with the courier', 'storewright' ),
		};
	}
}
