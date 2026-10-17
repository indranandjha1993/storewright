<?php

declare(strict_types=1);

namespace Storewright\Core\Account;

use InvalidArgumentException;
use WC_Customer;

final class AddressBook {

	public const META   = 'storewright_addresses';
	public const LIMIT  = 10;
	public const FIELDS = [ 'first_name', 'last_name', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone' ];

	public function __construct( private readonly int $userId ) {}

	public function all(): array {
		$saved = get_user_meta( $this->userId, self::META, true );
		$list  = [];
		foreach ( is_array( $saved ) ? $saved : [] as $id => $address ) {
			if ( is_array( $address ) ) {
				$list[ (string) $id ] = self::clean( $address );
			}
		}
		return $list;
	}

	public function withDefault(): array {
		$customer = new WC_Customer( $this->userId );
		$default  = [];
		foreach ( self::FIELDS as $field ) {
			$getter            = "get_shipping_{$field}";
			$default[ $field ] = (string) $customer->$getter();
		}
		$list = $default['postcode'] !== '' ? [ 'default' => self::clean( $default ) ] : [];
		return [ ...$list, ...$this->all() ];
	}

	public function add( array $input ): string {
		$address = self::validate( $input );
		$list    = $this->all();
		if ( count( $list ) >= self::LIMIT ) {
			throw new InvalidArgumentException( esc_html__( 'You can keep up to ten saved addresses. Remove one to add another.', 'storewright' ) );
		}
		$id          = substr( md5( wp_json_encode( $address ) . microtime() ), 0, 10 );
		$list[ $id ] = $address;
		update_user_meta( $this->userId, self::META, $list );
		return $id;
	}

	public function remove( string $id ): void {
		$list = $this->all();
		unset( $list[ $id ] );
		update_user_meta( $this->userId, self::META, $list );
	}

	public function makeDefault( string $id ): void {
		$list = $this->all();
		if ( ! isset( $list[ $id ] ) ) {
			return;
		}
		$customer = new WC_Customer( $this->userId );
		$current  = [];
		foreach ( self::FIELDS as $field ) {
			$getter            = "get_shipping_{$field}";
			$setter            = "set_shipping_{$field}";
			$current[ $field ] = (string) $customer->$getter();
			$customer->$setter( $list[ $id ][ $field ] );
		}
		$customer->save();
		unset( $list[ $id ] );
		if ( $current['postcode'] !== '' ) {
			$list[ substr( md5( wp_json_encode( $current ) ), 0, 10 ) ] = self::clean( $current );
		}
		update_user_meta( $this->userId, self::META, $list );
	}

	public static function validate( array $input ): array {
		$address = self::clean( $input );
		foreach ( [ 'first_name', 'address_1', 'city', 'postcode', 'country' ] as $required ) {
			if ( $address[ $required ] === '' ) {
				throw new InvalidArgumentException( esc_html__( 'Fill in the name, street, city and PIN code.', 'storewright' ) );
			}
		}
		if ( $address['country'] === 'IN' && ! preg_match( '/^[1-8][0-9]{5}$/', $address['postcode'] ) ) {
			throw new InvalidArgumentException( esc_html__( 'Enter the 6 digit PIN code of the address.', 'storewright' ) );
		}
		return $address;
	}

	private static function clean( array $input ): array {
		$address = [];
		foreach ( self::FIELDS as $field ) {
			$address[ $field ] = trim( sanitize_text_field( (string) ( $input[ $field ] ?? '' ) ) );
		}
		$address['country']  = strtoupper( $address['country'] ?: 'IN' );
		$address['state']    = strtoupper( $address['state'] );
		$address['postcode'] = strtoupper( (string) preg_replace( '/\s+/', '', $address['postcode'] ) );
		return $address;
	}
}
