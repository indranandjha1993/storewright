<?php

declare(strict_types=1);

namespace Storewright\Core\Admin;

use InvalidArgumentException;
use Storewright\Core\Checkout\Gstin;
use Storewright\Core\Settings;
use WC_Email;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class SettingsEndpoint {

	private const CUSTOMER_EMAILS = [
		'customer_processing_order',
		'customer_on_hold_order',
		'customer_fulfillment_created',
		'customer_completed_order',
		'customer_refunded_order',
		'customer_cancelled_order',
		'customer_failed_order',
		'customer_note',
		'customer_new_account',
	];

	public static function register(): void {
		register_rest_route(
			'storewright/v1',
			'/settings',
			[
				[
					'methods'             => 'GET',
					'callback'            => static fn(): WP_REST_Response => new WP_REST_Response( self::describe() ),
					'permission_callback' => [ self::class, 'allowed' ],
				],
				[
					'methods'             => 'PUT',
					'callback'            => [ self::class, 'update' ],
					'permission_callback' => [ self::class, 'allowed' ],
				],
			]
		);
	}

	public static function allowed(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	public static function describe(): array {
		[ , $state ] = array_pad( explode( ':', (string) get_option( 'woocommerce_default_country', 'IN:KA' ) ), 2, '' );
		$cod         = (array) get_option( 'woocommerce_cod_settings', [] );
		$newOrder    = (array) get_option( 'woocommerce_new_order_settings', [] );
		$states      = WC()->countries->get_states( 'IN' ) ?: [];
		return [
			'store'    => [
				'address1' => (string) get_option( 'woocommerce_store_address', '' ),
				'address2' => (string) get_option( 'woocommerce_store_address_2', '' ),
				'city'     => (string) get_option( 'woocommerce_store_city', '' ),
				'postcode' => (string) get_option( 'woocommerce_store_postcode', '' ),
				'state'    => $state,
				'phone'    => (string) Settings::get( 'store_phone' ),
				'gstin'    => (string) Settings::get( 'store_gstin' ),
			],
			'states'   => array_map(
				static fn( string $code ): array => [
					'code' => $code,
					'name' => $states[ $code ],
				],
				array_keys( $states )
			),
			'emails'   => [
				'senderName'  => (string) get_option( 'woocommerce_email_from_name', get_bloginfo( 'name' ) ),
				'senderEmail' => (string) get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) ),
				'newOrderTo'  => (string) ( $newOrder['recipient'] ?? '' ) ?: (string) get_option( 'admin_email' ),
				'customer'    => array_values( array_map( [ self::class, 'email' ], array_filter( array_map( [ self::class, 'find' ], self::CUSTOMER_EMAILS ) ) ) ),
			],
			'checkout' => [
				'cod'               => ( $cod['enabled'] ?? 'no' ) === 'yes',
				'codLimit'          => (float) Settings::get( 'cod_max_total' ),
				'guestCheckout'     => get_option( 'woocommerce_enable_guest_checkout', 'yes' ) === 'yes',
				'accountAtCheckout' => get_option( 'woocommerce_enable_signup_and_login_from_checkout', 'no' ) === 'yes',
			],
		];
	}

	public static function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$input = (array) $request->get_json_params();
		try {
			self::store( (array) ( $input['store'] ?? [] ) );
			self::emails( (array) ( $input['emails'] ?? [] ) );
			self::checkout( (array) ( $input['checkout'] ?? [] ) );
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'storewright_settings_invalid', $e->getMessage(), [ 'status' => 400 ] );
		}
		return new WP_REST_Response( self::describe() );
	}

	private static function store( array $store ): void {
		if ( $store === [] ) {
			return;
		}
		$postcode = trim( (string) ( $store['postcode'] ?? '' ) );
		if ( $postcode !== '' && ! preg_match( '/^[1-9][0-9]{5}$/', $postcode ) ) {
			throw new InvalidArgumentException( esc_html__( 'The PIN code needs six digits.', 'storewright' ) );
		}
		$gstin = Gstin::normalise( (string) ( $store['gstin'] ?? '' ) );
		if ( $gstin !== '' && ! Gstin::isValid( $gstin ) ) {
			throw new InvalidArgumentException( esc_html__( 'That GSTIN does not look right. It has 15 characters, such as 29ABCDE1234F1Z5.', 'storewright' ) );
		}
		$state = strtoupper( sanitize_key( (string) ( $store['state'] ?? '' ) ) );
		if ( $state !== '' && ! array_key_exists( $state, WC()->countries->get_states( 'IN' ) ?: [] ) ) {
			throw new InvalidArgumentException( esc_html__( 'Choose a state from the list.', 'storewright' ) );
		}
		update_option( 'woocommerce_store_address', sanitize_text_field( (string) ( $store['address1'] ?? '' ) ) );
		update_option( 'woocommerce_store_address_2', sanitize_text_field( (string) ( $store['address2'] ?? '' ) ) );
		update_option( 'woocommerce_store_city', sanitize_text_field( (string) ( $store['city'] ?? '' ) ) );
		update_option( 'woocommerce_store_postcode', $postcode );
		if ( $state !== '' ) {
			update_option( 'woocommerce_default_country', 'IN:' . $state );
		}
		Settings::set(
			[
				'store_phone' => sanitize_text_field( (string) ( $store['phone'] ?? '' ) ),
				'store_gstin' => $gstin,
			]
		);
	}

	private static function emails( array $emails ): void {
		if ( $emails === [] ) {
			return;
		}
		$sender = sanitize_email( (string) ( $emails['senderEmail'] ?? '' ) );
		if ( ! is_email( $sender ) ) {
			throw new InvalidArgumentException( esc_html__( 'Emails need a sender address.', 'storewright' ) );
		}
		$recipients = array_filter( array_map( 'trim', explode( ',', (string) ( $emails['newOrderTo'] ?? '' ) ) ) );
		if ( $recipients === [] || array_filter( $recipients, static fn( string $address ): bool => ! is_email( $address ) ) !== [] ) {
			throw new InvalidArgumentException( esc_html__( 'New order emails need one or more addresses, separated by commas.', 'storewright' ) );
		}
		update_option( 'woocommerce_email_from_name', sanitize_text_field( (string) ( $emails['senderName'] ?? '' ) ) ?: get_bloginfo( 'name' ) );
		update_option( 'woocommerce_email_from_address', $sender );
		$newOrder              = (array) get_option( 'woocommerce_new_order_settings', [] );
		$newOrder['recipient'] = implode( ', ', array_map( 'sanitize_email', $recipients ) );
		update_option( 'woocommerce_new_order_settings', $newOrder );
		$enabled = array_map( 'strval', (array) ( $emails['enabled'] ?? [] ) );
		foreach ( self::CUSTOMER_EMAILS as $id ) {
			$email = self::find( $id );
			if ( $email ) {
				$email->update_option( 'enabled', in_array( $id, $enabled, true ) ? 'yes' : 'no' );
			}
		}
	}

	private static function checkout( array $checkout ): void {
		if ( $checkout === [] ) {
			return;
		}
		$limit = (float) ( $checkout['codLimit'] ?? 0 );
		if ( $limit < 0 ) {
			throw new InvalidArgumentException( esc_html__( 'The cash on delivery limit cannot be negative.', 'storewright' ) );
		}
		$cod            = (array) get_option( 'woocommerce_cod_settings', [] );
		$cod['enabled'] = ! empty( $checkout['cod'] ) ? 'yes' : 'no';
		update_option( 'woocommerce_cod_settings', $cod );
		Settings::set( [ 'cod_max_total' => $limit ] );
		update_option( 'woocommerce_enable_guest_checkout', ! empty( $checkout['guestCheckout'] ) ? 'yes' : 'no' );
		update_option( 'woocommerce_enable_signup_and_login_from_checkout', ! empty( $checkout['accountAtCheckout'] ) ? 'yes' : 'no' );
	}

	private static function find( string $id ): ?WC_Email {
		foreach ( WC()->mailer()->get_emails() as $email ) {
			if ( $email instanceof WC_Email && $email->id === $id ) {
				return $email;
			}
		}
		return null;
	}

	private static function email( WC_Email $email ): array {
		return [
			'id'      => $email->id,
			'title'   => self::label( $email->id ),
			'enabled' => $email->is_enabled(),
		];
	}

	private static function label( string $id ): string {
		return match ( $id ) {
			'customer_processing_order'    => __( 'Order received', 'storewright' ),
			'customer_on_hold_order'       => __( 'Order on hold', 'storewright' ),
			'customer_fulfillment_created' => __( 'Parcel on its way', 'storewright' ),
			'customer_completed_order'     => __( 'Order complete', 'storewright' ),
			'customer_refunded_order'      => __( 'Refund issued', 'storewright' ),
			'customer_cancelled_order'     => __( 'Order cancelled', 'storewright' ),
			'customer_failed_order'        => __( 'Payment failed', 'storewright' ),
			'customer_note'                => __( 'Note from the store', 'storewright' ),
			default                        => __( 'Account created', 'storewright' ),
		};
	}
}
