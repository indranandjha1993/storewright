<?php

declare(strict_types=1);

namespace Storewright\Core\Admin;

final class Today {

	private const DAYS = [
		'today' => 1,
		'week'  => 7,
		'month' => 30,
	];

	public static function range( string $period, int $offset = 0 ): array {
		$days = self::DAYS[ $period ] ?? 1;
		$end  = ( new \DateTimeImmutable( 'today', wp_timezone() ) )->modify( sprintf( '-%d days', $days * $offset ) );
		return [
			'after'  => $end->modify( sprintf( '-%d days', $days - 1 ) )->format( 'Y-m-d 00:00:00' ),
			'before' => $end->format( 'Y-m-d 23:59:59' ),
			'days'   => $days,
		];
	}

	public static function describe( string $period ): array {
		$now    = self::stats( self::range( $period ) );
		$before = self::stats( self::range( $period, 1 ) );
		$kpis   = [
			self::kpi( __( 'Sales', 'storewright' ), wc_price( $now['sales'] ), $now['sales'], $before['sales'] ),
			self::kpi( __( 'Orders', 'storewright' ), number_format_i18n( $now['orders'] ), $now['orders'], $before['orders'] ),
			self::kpi( __( 'Average order', 'storewright' ), wc_price( $now['average'] ), $now['average'], $before['average'] ),
			self::kpi( __( 'Refunds', 'storewright' ), wc_price( $now['refunds'] ), $now['refunds'], $before['refunds'] ),
		];
		$queues = [];
		foreach ( [
			'processing' => __( 'to fulfil', 'storewright' ),
			'pending'    => __( 'awaiting payment', 'storewright' ),
			'on-hold'    => __( 'on hold', 'storewright' ),
		] as $status => $label ) {
			$queues[] = [
				'label' => $label,
				'count' => (int) wc_orders_count( $status ),
				'url'   => admin_url( 'admin.php?page=wc-orders&status=wc-' . $status ),
			];
		}
		return [
			'kpis'     => $kpis,
			'queues'   => $queues,
			'stock'    => self::stock(),
			'products' => self::bestSellers( self::range( $period ) ),
		];
	}

	private static function stats( array $range ): array {
		global $wpdb;
		$after  = Insights::gmt( $range['after'] );
		$before = Insights::gmt( $range['before'] );
		$row    = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(CASE WHEN parent_id = 0 THEN total_sales ELSE 0 END), 0) AS sales,
					SUM(parent_id = 0) AS orders,
					COALESCE(-SUM(CASE WHEN parent_id > 0 THEN total_sales ELSE 0 END), 0) AS refunds
				FROM {$wpdb->prefix}wc_order_stats
				WHERE status IN ('wc-processing', 'wc-completed') AND date_created_gmt BETWEEN %s AND %s",
				$after,
				$before
			),
			ARRAY_A
		);
		$sales  = (float) ( $row['sales'] ?? 0 );
		$orders = (int) ( $row['orders'] ?? 0 );
		return [
			'sales'   => $sales,
			'orders'  => $orders,
			'average' => $orders > 0 ? $sales / $orders : 0.0,
			'refunds' => (float) ( $row['refunds'] ?? 0 ),
		];
	}

	private static function kpi( string $label, string $value, float|int $now, float|int $before ): array {
		if ( ! $before ) {
			return [
				'label' => $label,
				'value' => $value,
				'note'  => __( 'No earlier period to compare', 'storewright' ),
				'tone'  => '',
			];
		}
		$delta = (int) round( ( $now - $before ) / $before * 100 );
		return [
			'label' => $label,
			'value' => $value,
			/* translators: %s: signed percentage */
			'note'  => sprintf( __( '%s%% on the period before', 'storewright' ), ( $delta > 0 ? '+' : '' ) . $delta ),
			'tone'  => $delta > 0 ? 'is-up' : ( $delta < 0 ? 'is-down' : '' ),
		];
	}

	private static function stock(): array {
		global $wpdb;
		$low   = max( 1, (int) get_option( 'woocommerce_notify_low_stock_amount', 2 ) );
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.product_id AS id, p.post_title AS name, l.stock_quantity AS quantity
				FROM {$wpdb->prefix}wc_product_meta_lookup l
				JOIN {$wpdb->posts} p ON p.ID = l.product_id
				WHERE p.post_status = 'publish' AND l.stock_quantity IS NOT NULL AND l.stock_quantity > 0 AND l.stock_quantity <= %d
				ORDER BY l.stock_quantity ASC LIMIT 5",
				$low
			),
			ARRAY_A
		);
		$out   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_product_meta_lookup l JOIN {$wpdb->posts} p ON p.ID = l.product_id WHERE p.post_status = 'publish' AND l.stock_status = 'outofstock'" );
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_product_meta_lookup l JOIN {$wpdb->posts} p ON p.ID = l.product_id WHERE p.post_status = 'publish' AND l.stock_quantity IS NOT NULL AND l.stock_quantity > 0 AND l.stock_quantity <= %d", $low ) );
		return [
			'low'   => $count,
			'out'   => $out,
			'items' => array_map(
				static fn( array $row ): array => [
					'id'       => (int) $row['id'],
					'name'     => (string) $row['name'],
					'quantity' => (int) $row['quantity'],
				],
				(array) $items
			),
		];
	}

	private static function bestSellers( array $range ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.product_id AS id, p.post_title AS name, SUM(l.product_qty) AS sold, SUM(l.product_net_revenue) AS revenue
				FROM {$wpdb->prefix}wc_order_product_lookup l
				JOIN {$wpdb->prefix}wc_order_stats s ON s.order_id = l.order_id
				JOIN {$wpdb->posts} p ON p.ID = l.product_id
				WHERE s.status IN ('wc-processing', 'wc-completed') AND s.date_created_gmt BETWEEN %s AND %s
				GROUP BY l.product_id, p.post_title ORDER BY sold DESC LIMIT 4",
				Insights::gmt( $range['after'] ),
				Insights::gmt( $range['before'] )
			),
			ARRAY_A
		);
		return array_map(
			static fn( array $row ): array => [
				'id'      => (int) $row['id'],
				'name'    => (string) $row['name'],
				'sold'    => (int) $row['sold'],
				'revenue' => (float) $row['revenue'],
			],
			(array) $rows
		);
	}
}
