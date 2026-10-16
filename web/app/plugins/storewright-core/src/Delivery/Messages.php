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

	public static function forShopper( string $lookup, string $pin, string $district, ?Answer $answer ): string {
		if ( $lookup === 'invalid' ) {
			return __( 'Enter the 6 digit PIN code of the delivery address.', 'storewright' );
		}
		if ( $lookup === 'unknown' || $answer === null ) {
			/* translators: %s: PIN code */
			return $pin === '' ? __( 'We could not place your location. Enter your PIN code instead.', 'storewright' ) : sprintf( __( 'We could not find PIN %s. Check the number and try again.', 'storewright' ), $pin );
		}
		$place = $district !== '' ? "{$pin}, {$district}" : $pin;
		return match ( $answer->status ) {
			/* translators: %s: PIN code and district */
			Answer::DELIVERABLE => sprintf( __( 'We deliver to %s.', 'storewright' ), $place ),
			/* translators: %s: PIN code and district */
			Answer::APPROXIMATE => sprintf( __( 'We probably deliver to %s. Use your location to be sure.', 'storewright' ), $place ),
			/* translators: %s: PIN code and district */
			default             => sprintf( __( 'We do not deliver to %s yet. Try another PIN code or use your location.', 'storewright' ), $place ),
		};
	}

	public static function badge( Answer $answer ): string {
		return match ( $answer->status ) {
			Answer::DELIVERABLE   => __( 'We deliver here.', 'storewright' ),
			Answer::APPROXIMATE   => __( 'Delivery here is likely; we confirm it with your location at checkout.', 'storewright' ),
			default               => __( 'Outside our delivery area.', 'storewright' ),
		};
	}
}
