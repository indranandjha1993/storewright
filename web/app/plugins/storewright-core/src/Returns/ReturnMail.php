<?php

declare(strict_types=1);

namespace Storewright\Core\Returns;

use WC_Order;

final class ReturnMail {

	public static function customer( WC_Order $order, array $request ): void {
		$items              = ReturnRequests::summary( $order, $request['items'] );
		[ $heading, $body ] = match ( $request['status'] ) {
			'requested' => [ __( 'We have your return request', 'storewright' ), __( 'We will look at it within a working day and email you how the items get back to us.', 'storewright' ) ],
			'approved'  => [ __( 'Your return is approved', 'storewright' ), __( 'Pack the items in their original packaging. We will arrange a pickup from your delivery address and email the date.', 'storewright' ) ],
			'declined'  => [ __( 'About your return request', 'storewright' ), __( 'We cannot accept this return.', 'storewright' ) ],
			'received'  => [ __( 'Your return has reached us', 'storewright' ), __( 'We are checking the items and will refund you shortly.', 'storewright' ) ],
			default     => [ '', '' ],
		};
		if ( $heading === '' ) {
			return;
		}
		$lines = [
			$body,
			/* translators: 1: order number, 2: items */
			sprintf( __( 'Order #%1$s: %2$s', 'storewright' ), $order->get_order_number(), $items ),
		];
		if ( ( $request['answer'] ?? '' ) !== '' ) {
			$lines[] = $request['answer'];
		}
		self::send( $order->get_billing_email(), $heading, $lines );
	}

	public static function pickup( WC_Order $order, array $request, string $courier, string $awb ): void {
		self::send(
			$order->get_billing_email(),
			__( 'Your return pickup is booked', 'storewright' ),
			[
				/* translators: 1: courier name, 2: waybill number */
				sprintf( __( '%1$s will collect the parcel from your delivery address; the waybill is %2$s. Keep it packed and ready.', 'storewright' ), $courier, $awb ),
				/* translators: 1: order number, 2: items */
				sprintf( __( 'Order #%1$s: %2$s', 'storewright' ), $order->get_order_number(), ReturnRequests::summary( $order, $request['items'] ) ),
			]
		);
	}

	public static function staff( WC_Order $order, array $request ): void {
		$recipients = (string) ( ( (array) get_option( 'woocommerce_new_order_settings', [] ) )['recipient'] ?? '' ) ?: (string) get_option( 'admin_email' );
		self::send(
			$recipients,
			/* translators: %s: order number */
			sprintf( __( 'Return requested on order #%s', 'storewright' ), $order->get_order_number() ),
			[
				ReturnRequests::summary( $order, $request['items'] ),
				/* translators: %s: reason */
				sprintf( __( 'Reason: %s', 'storewright' ), ReturnRequests::reason( $request['reason'] ) ),
				$request['note'],
			]
		);
	}

	private static function send( string $to, string $heading, array $lines ): void {
		$mailer  = WC()->mailer();
		$content = implode( '', array_map( static fn( string $line ): string => '<p>' . esc_html( $line ) . '</p>', array_filter( $lines ) ) );
		$mailer->send( $to, $heading, $mailer->wrap_message( $heading, $content ) );
	}
}
