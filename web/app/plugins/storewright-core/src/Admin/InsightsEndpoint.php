<?php

declare(strict_types=1);

namespace Storewright\Core\Admin;

use DateTimeImmutable;
use DateTimeZone;
use Storewright\Core\Returns\ReturnRequests;
use WP_REST_Request;
use WP_REST_Response;

final class InsightsEndpoint {

	private const PAID = [ 'wc-processing', 'wc-completed' ];

	public static function register(): void {
		register_rest_route(
			'storewright/v1',
			'/insights',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'respond' ],
				'permission_callback' => static fn(): bool => current_user_can( 'manage_woocommerce' ),
				'args'                => [
					'after'  => [ 'type' => 'string', 'required' => true ],
					'before' => [ 'type' => 'string', 'required' => true ],
				],
			]
		);
	}

	public static function respond( WP_REST_Request $request ): WP_REST_Response {
		$after  = self::gmt( (string) $request['after'] );
		$before = self::gmt( (string) $request['before'] );
		return new WP_REST_Response(
			[
				'payments'  => self::payments( $after, $before ),
				'customers' => self::customers( $after, $before ),
				'cities'    => self::cities( $after, $before ),
				'delivery'  => self::delivery( $after, $before ),
				'stock'     => self::stock(),
				'returns'   => self::openReturns(),
			]
		);
	}

	private static function gmt( string $local ): string {
		$date = new DateTimeImmutable( $local, wp_timezone() );
		return $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	private static function statuses(): string {
		return "'" . implode( "','", self::PAID ) . "'";
	}

	private static function payments( string $after, string $before ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COALESCE(NULLIF(payment_method, ''), payment_method_title) AS method, MAX(payment_method_title) AS title, COUNT(*) AS orders, SUM(total_amount) AS total
				FROM {$wpdb->prefix}wc_orders
				WHERE type = 'shop_order' AND status IN (" . self::statuses() . ") AND date_created_gmt BETWEEN %s AND %s
				GROUP BY COALESCE(NULLIF(payment_method, ''), payment_method_title) ORDER BY total DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$after,
				$before
			),
			ARRAY_A
		);
		return array_map(
			static fn( array $row ): array => [
				'method' => (string) $row['method'],
				'title'  => (string) $row['title'] ?: (string) $row['method'],
				'orders' => (int) $row['orders'],
				'total'  => (float) $row['total'],
			],
			$rows
		);
	}

	private static function customers( string $after, string $before ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COALESCE(NULLIF(customer_id, 0), billing_email) AS who, MIN(date_created_gmt) AS first_order,
					SUM(date_created_gmt BETWEEN %s AND %s) AS in_period
				FROM {$wpdb->prefix}wc_orders
				WHERE type = 'shop_order' AND status IN (" . self::statuses() . ")
				GROUP BY who HAVING in_period > 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$after,
				$before
			),
			ARRAY_A
		);
		$new = 0;
		foreach ( $rows as $row ) {
			if ( (string) $row['first_order'] >= $after ) {
				++$new;
			}
		}
		return [
			'new'       => $new,
			'returning' => count( $rows ) - $new,
		];
	}

	private static function cities( string $after, string $before ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.city AS city, a.state AS state, COUNT(*) AS orders, SUM(o.total_amount) AS total
				FROM {$wpdb->prefix}wc_orders o
				JOIN {$wpdb->prefix}wc_order_addresses a ON a.order_id = o.id AND a.address_type = 'shipping'
				WHERE o.type = 'shop_order' AND o.status IN (" . self::statuses() . ") AND o.date_created_gmt BETWEEN %s AND %s AND a.city <> ''
				GROUP BY a.city, a.state ORDER BY orders DESC LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$after,
				$before
			),
			ARRAY_A
		);
		return array_map(
			static fn( array $row ): array => [
				'city'   => (string) $row['city'],
				'state'  => (string) $row['state'],
				'orders' => (int) $row['orders'],
				'total'  => (float) $row['total'],
			],
			$rows
		);
	}

	private static function delivery( string $after, string $before ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.order_item_name AS title, COUNT(*) AS orders
				FROM {$wpdb->prefix}woocommerce_order_items i
				JOIN {$wpdb->prefix}wc_orders o ON o.id = i.order_id
				WHERE i.order_item_type = 'shipping' AND o.type = 'shop_order' AND o.status IN (" . self::statuses() . ") AND o.date_created_gmt BETWEEN %s AND %s
				GROUP BY i.order_item_name ORDER BY orders DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$after,
				$before
			),
			ARRAY_A
		);
		return array_map(
			static fn( array $row ): array => [
				'title'  => (string) $row['title'],
				'orders' => (int) $row['orders'],
			],
			$rows
		);
	}

	private static function stock(): array {
		global $wpdb;
		$days  = 30;
		$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.product_id AS product_id, SUM(p.product_qty) AS sold
				FROM {$wpdb->prefix}wc_order_product_lookup p
				JOIN {$wpdb->prefix}wc_orders o ON o.id = p.order_id
				WHERE o.status IN (" . self::statuses() . ") AND p.date_created >= %s
				GROUP BY p.product_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since
			),
			ARRAY_A
		);
		$items = [];
		foreach ( $rows as $row ) {
			$product = wc_get_product( (int) $row['product_id'] );
			if ( ! $product || ! $product->managing_stock() || (int) $row['sold'] <= 0 ) {
				continue;
			}
			$perDay   = (int) $row['sold'] / $days;
			$quantity = (int) $product->get_stock_quantity();
			$items[]  = [
				'id'       => $product->get_id(),
				'name'     => $product->get_name(),
				'quantity' => $quantity,
				'perDay'   => round( $perDay, 2 ),
				'daysLeft' => (int) floor( $quantity / $perDay ),
			];
		}
		usort( $items, static fn( array $a, array $b ): int => $a['daysLeft'] <=> $b['daysLeft'] );
		return array_slice( $items, 0, 5 );
	}

	private static function openReturns(): int {
		return count(
			wc_get_orders(
				[
					'limit'      => -1,
					'return'     => 'ids',
					'meta_key'   => ReturnRequests::OPEN, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				]
			)
		);
	}
}
