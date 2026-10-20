<?php

declare(strict_types=1);

namespace Storewright\Core\Returns;

use Storewright\Core\Fulfilment\ShipmentStore;
use Storewright\Core\Fulfilment\Shipping;
use InvalidArgumentException;
use WC_Order;

final class CustomerForm {

	private const NONCE = 'storewright_return';

	public static function register(): void {
		add_action( 'woocommerce_order_details_after_order_table', [ self::class, 'render' ], 20 );
		add_action( 'template_redirect', [ self::class, 'handle' ] );
	}

	public static function render( WC_Order $order ): void {
		if ( ! is_account_page() || $order->get_customer_id() !== get_current_user_id() ) {
			return;
		}
		$requests = ReturnRequests::all( $order );
		$left     = ReturnRequests::returnable( $order );
		if ( $requests === [] && $left === [] ) {
			return;
		}
		wc_get_template(
			'myaccount/storewright-returns.php',
			[
				'order'    => $order,
				'requests' => $requests,
				'left'     => $left,
				'deadline' => ReturnRequests::deadline( $order ),
				'nonce'    => self::NONCE,
				'pickups'  => self::pickups( $order ),
			],
			'',
			dirname( __DIR__, 2 ) . '/templates/'
		);
	}

	private static function pickups( WC_Order $order ): array {
		$pickups = [];
		foreach ( Shipping::store()->forOrder( $order->get_id() ) as $row ) {
			if ( $row['kind'] === ShipmentStore::RETURN && $row['status'] !== ShipmentStore::CANCELLED ) {
				$pickups[ (string) $row['return_id'] ] = $row;
			}
		}
		return $pickups;
	}

	public static function handle(): void {
		if ( ! is_user_logged_in() || ! isset( $_POST['storewright_return_order'] ) ) {
			return;
		}
		check_admin_referer( self::NONCE );
		$order = wc_get_order( absint( wp_unslash( $_POST['storewright_return_order'] ) ) );
		if ( ! $order instanceof WC_Order || $order->get_customer_id() !== get_current_user_id() ) {
			return;
		}
		try {
			$request = ReturnRequests::request(
				$order,
				array_map( 'absint', wp_unslash( (array) ( $_POST['storewright_return_items'] ?? [] ) ) ),
				sanitize_key( wp_unslash( $_POST['storewright_return_reason'] ?? '' ) ),
				sanitize_textarea_field( wp_unslash( $_POST['storewright_return_note'] ?? '' ) )
			);
			ReturnMail::customer( $order, $request );
			ReturnMail::staff( $order, $request );
			wc_add_notice( __( 'Return requested. We will email you about the pickup.', 'storewright' ) );
		} catch ( InvalidArgumentException $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
		}
		wp_safe_redirect( $order->get_view_order_url() );
		exit;
	}
}
