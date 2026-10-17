<?php

declare(strict_types=1);

namespace Storewright\Core\Emails;

use WC_Email;

final class ShipmentCopy {

	public static function register(): void {
		add_filter( 'woocommerce_email_subject_customer_fulfillment_created', [ self::class, 'subject' ], 10, 3 );
		add_filter( 'woocommerce_email_heading_customer_fulfillment_created', [ self::class, 'heading' ] );
		add_action( 'woocommerce_email_header', [ self::class, 'start' ] );
		add_action( 'woocommerce_email_footer', [ self::class, 'stop' ], 99 );
	}

	public static function subject( string $subject, mixed $order, mixed $email ): string {
		$text = __( 'Your {site_title} order {order_number} is on its way', 'storewright' );
		return $email instanceof WC_Email ? $email->format_string( $text ) : $subject;
	}

	public static function heading(): string {
		return __( 'Your parcel is on its way', 'storewright' );
	}

	public static function start(): void {
		add_filter( 'gettext_woocommerce', [ self::class, 'line' ], 10, 2 );
	}

	public static function stop(): void {
		remove_filter( 'gettext_woocommerce', [ self::class, 'line' ] );
	}

	public static function line( string $translation, string $text ): string {
		return match ( $text ) {
			'Woo! Some items you purchased are being fulfilled. You can use the below information to track your shipment:' => __( 'Your parcel has left us. The details below show what is in it and how to follow it.', 'storewright' ),
			"We wanted to let you know that one of the previously fulfilled shipments from your order has been removed from our system. This may have been due to a correction or an update in our fulfillment records. Don\u{2019}t worry \u{2014} this won\u{2019}t affect any items you\u{2019}ve already received." => __( 'We removed one of the shipments on your order from our records, usually to correct a mistake. Anything you have already received is not affected.', 'storewright' ),
			default => $translation,
		};
	}
}
