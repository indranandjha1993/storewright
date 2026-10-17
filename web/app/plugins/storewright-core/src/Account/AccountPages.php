<?php

declare(strict_types=1);

namespace Storewright\Core\Account;

use InvalidArgumentException;
use Storewright\Core\Asset;
use Storewright\Core\Delivery\Address;
use Storewright\Core\Delivery\DeliveryCheck;
use Storewright\Core\Delivery\Messages;

final class AccountPages {

	public const ENDPOINT    = 'addresses';
	public const SAVED       = 'saved';
	private const NONCE      = 'storewright_addresses';
	private const SAVE_NONCE = 'storewright_saved';
	private const VERSION    = '2';

	public static function register(): void {
		add_filter( 'woocommerce_get_query_vars', [ self::class, 'queryVars' ] );
		add_filter( 'woocommerce_account_menu_items', [ self::class, 'menu' ] );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', static fn(): string => __( 'Addresses', 'storewright' ) );
		add_filter( 'woocommerce_endpoint_' . self::SAVED . '_title', static fn(): string => __( 'Saved', 'storewright' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', [ self::class, 'render' ] );
		add_action( 'woocommerce_account_' . self::SAVED . '_endpoint', [ self::class, 'renderSaved' ] );
		add_action( 'template_redirect', [ self::class, 'handle' ] );
		add_action( 'template_redirect', [ self::class, 'handleSaved' ] );
		add_action( 'init', [ self::class, 'flushOnce' ], 99 );
		add_action( 'wp_enqueue_scripts', [ self::class, 'checkoutChooser' ] );
	}

	public static function queryVars( array $vars ): array {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		$vars[ self::SAVED ]    = self::SAVED;
		return $vars;
	}

	public static function flushOnce(): void {
		if ( get_option( 'storewright_account_endpoints' ) !== self::VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'storewright_account_endpoints', self::VERSION, false );
		}
	}

	public static function menu( array $items ): array {
		$labels = [
			'dashboard'       => __( 'Overview', 'storewright' ),
			'orders'          => __( 'Orders', 'storewright' ),
			self::SAVED       => __( 'Saved', 'storewright' ),
			'downloads'       => __( 'Downloads', 'storewright' ),
			self::ENDPOINT    => __( 'Addresses', 'storewright' ),
			'edit-account'    => __( 'Account details', 'storewright' ),
			'customer-logout' => __( 'Sign out', 'storewright' ),
		];
		return array_filter( $labels, static fn( string $key ): bool => in_array( $key, [ self::ENDPOINT, self::SAVED ], true ) || isset( $items[ $key ] ), ARRAY_FILTER_USE_KEY );
	}

	public static function handle(): void {
		if ( ! is_user_logged_in() || ! isset( $_POST['storewright_address_action'] ) ) {
			return;
		}
		check_admin_referer( self::NONCE );
		$book   = new AddressBook( get_current_user_id() );
		$action = sanitize_key( wp_unslash( $_POST['storewright_address_action'] ) );
		$id     = sanitize_key( wp_unslash( $_POST['address'] ?? '' ) );
		try {
			match ( $action ) {
				'add'     => $book->add( wp_unslash( (array) ( $_POST['shipping'] ?? [] ) ) ),
				'remove'  => $book->remove( $id ),
				'default' => $book->makeDefault( $id ),
				default   => null,
			};
			wc_add_notice(
				match ( $action ) {
					'add'     => __( 'Address saved.', 'storewright' ),
					'remove'  => __( 'Address removed.', 'storewright' ),
					default   => __( 'Deliveries now go to that address unless you choose another at checkout.', 'storewright' ),
				}
			);
		} catch ( InvalidArgumentException $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
		}
		wp_safe_redirect( wc_get_account_endpoint_url( self::ENDPOINT ) );
		exit;
	}

	public static function handleSaved(): void {
		if ( ! is_user_logged_in() || ( $_POST['storewright_saved_action'] ?? '' ) !== 'remove' ) {
			return;
		}
		check_admin_referer( self::SAVE_NONCE );
		( new SavedItems( get_current_user_id() ) )->remove( absint( wp_unslash( $_POST['product'] ?? 0 ) ) );
		wc_add_notice( __( 'Removed from your saved products.', 'storewright' ) );
		wp_safe_redirect( wc_get_account_endpoint_url( self::SAVED ) );
		exit;
	}

	public static function renderSaved(): void {
		wc_get_template(
			'myaccount/storewright-saved.php',
			[
				'products' => ( new SavedItems( get_current_user_id() ) )->products(),
				'nonce'    => self::SAVE_NONCE,
			],
			'',
			dirname( __DIR__, 2 ) . '/templates/'
		);
	}

	public static function render(): void {
		$book      = new AddressBook( get_current_user_id() );
		$addresses = $book->withDefault();
		wc_get_template(
			'myaccount/storewright-addresses.php',
			[
				'addresses' => array_map( [ self::class, 'describe' ], $addresses ),
				'nonce'     => self::NONCE,
				'billing'   => wc_get_account_formatted_address( 'billing' ),
				'countries' => WC()->countries->get_shipping_countries(),
				'states'    => WC()->countries->get_states( 'IN' ),
			],
			'',
			dirname( __DIR__, 2 ) . '/templates/'
		);
	}

	public static function describe( array $address ): array {
		$answer = DeliveryCheck::forShopper( new Address( $address['country'], $address['state'], $address['postcode'] ), null );
		return [
			...$address,
			'formatted' => WC()->countries->get_formatted_address( $address ),
			'status'    => $answer->status,
			'badge'     => Messages::badge( $answer ),
		];
	}

	public static function checkoutChooser(): void {
		if ( ! is_user_logged_in() || ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		$addresses = ( new AddressBook( get_current_user_id() ) )->withDefault();
		if ( count( $addresses ) < 2 ) {
			return;
		}
		Asset::script( 'storewright-saved-addresses', 'assets/js/saved-addresses.js', [ 'wp-data', 'wp-i18n' ] );
		wp_add_inline_script(
			'storewright-saved-addresses',
			'window.storewrightAddresses = ' . wp_json_encode( array_values( array_map( [ self::class, 'describe' ], $addresses ) ) ) . ';',
			'before'
		);
	}
}
