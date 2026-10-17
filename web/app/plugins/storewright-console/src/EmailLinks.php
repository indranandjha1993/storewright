<?php

declare(strict_types=1);

namespace Storewright\Console;

use WC_Order;

final class EmailLinks {

	public static function register(): void {
		add_action( 'woocommerce_email_header', [ self::class, 'start' ] );
		add_action( 'woocommerce_email_footer', [ self::class, 'stop' ], 99 );
	}

	public static function start(): void {
		add_filter( 'woocommerce_get_edit_order_url', [ self::class, 'orderUrl' ], 10, 2 );
	}

	public static function stop(): void {
		remove_filter( 'woocommerce_get_edit_order_url', [ self::class, 'orderUrl' ] );
	}

	public static function orderUrl( string $url, WC_Order $order ): string {
		return add_query_arg( 'order', $order->get_id(), Console::url( '/orders' ) );
	}
}
