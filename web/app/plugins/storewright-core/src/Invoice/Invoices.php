<?php

declare(strict_types=1);

namespace Storewright\Core\Invoice;

use Storewright\Platform\Brand\Fonts;
use Storewright\Platform\Brand\Palette;
use Storewright\Platform\Brand\Settings as Brand;
use WC_Order;
use WP_REST_Response;

final class Invoices {

	public const QUERY = 'storewright-invoice';

	public static function register(): void {
		add_action( 'woocommerce_order_status_processing', [ self::class, 'assignById' ] );
		add_action( 'woocommerce_order_status_completed', [ self::class, 'assignById' ] );
		add_action( 'template_redirect', [ self::class, 'show' ] );
		add_action( 'woocommerce_order_details_after_order_table', [ self::class, 'link' ] );
		add_filter( 'woocommerce_rest_prepare_shop_order_object', [ self::class, 'rest' ], 10, 2 );
	}

	public static function assignById( int $orderId ): void {
		$order = wc_get_order( $orderId );
		if ( $order instanceof WC_Order ) {
			Invoice::assign( $order );
		}
	}

	public static function url( WC_Order $order ): string {
		return add_query_arg(
			[
				self::QUERY => $order->get_id(),
				'key'       => $order->get_order_key(),
			],
			home_url( '/' )
		);
	}

	public static function show(): void {
		if ( ! isset( $_GET[ self::QUERY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$order = wc_get_order( absint( wp_unslash( $_GET[ self::QUERY ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key   = sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $order instanceof WC_Order || (string) $order->get_meta( Invoice::NUMBER ) === '' || ! self::allowed( $order, $key ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		$brand = Brand::get();
		wc_get_template(
			'invoice.php',
			[
				'invoice' => Invoice::build( $order ),
				'palette' => Palette::from( $brand ),
				'heading' => Fonts::get( $brand['headingFont'] ),
				'body'    => Fonts::get( $brand['bodyFont'] ),
				'logo'    => (int) get_option( 'site_logo' ),
			],
			'',
			dirname( __DIR__, 2 ) . '/templates/'
		);
		exit;
	}

	public static function link( WC_Order $order ): void {
		$number = (string) $order->get_meta( Invoice::NUMBER );
		if ( $number === '' ) {
			return;
		}
		printf(
			'<p class="sw-invoice-link"><a class="wp-element-button" href="%1$s" target="_blank" rel="noopener">%2$s</a></p>',
			esc_url( self::url( $order ) ),
			/* translators: %s: invoice number */
			esc_html( sprintf( __( 'Invoice %s', 'storewright' ), $number ) )
		);
	}

	public static function rest( WP_REST_Response $response, mixed $order ): WP_REST_Response {
		if ( ! $order instanceof WC_Order ) {
			return $response;
		}
		$number                      = (string) $order->get_meta( Invoice::NUMBER );
		$data                        = $response->get_data();
		$data['storewright_invoice'] = $number === '' ? null : [
			'number' => $number,
			'url'    => self::url( $order ),
		];
		$response->set_data( $data );
		return $response;
	}

	private static function allowed( WC_Order $order, string $key ): bool {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}
		if ( $order->get_customer_id() > 0 && $order->get_customer_id() === get_current_user_id() ) {
			return true;
		}
		return $key !== '' && hash_equals( $order->get_order_key(), $key );
	}
}
