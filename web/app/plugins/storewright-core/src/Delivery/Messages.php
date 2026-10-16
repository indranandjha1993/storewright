<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

final class Messages {

	public static function undeliverable( Answer $answer, Address $address ): string {
		$place = $address->postcode !== '' ? $address->postcode : __( 'this address', 'storewright' );
		if ( $answer->reason === Answer::OUTSIDE_AREA ) {
			/* translators: %s: PIN code */
			return sprintf( __( 'We do not deliver to %s yet. Try another address, or share your location if you are nearby.', 'storewright' ), $place );
		}
		/* translators: %s: PIN code */
		return sprintf( __( 'We do not deliver to %s yet. Please use another delivery address.', 'storewright' ), $place );
	}

	public static function badge( Answer $answer ): string {
		return match ( $answer->status ) {
			Answer::DELIVERABLE   => __( 'We deliver here.', 'storewright' ),
			Answer::APPROXIMATE   => __( 'Delivery here is likely; we confirm it with your location at checkout.', 'storewright' ),
			default               => __( 'Outside our delivery area.', 'storewright' ),
		};
	}
}
