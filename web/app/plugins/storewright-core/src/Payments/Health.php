<?php

declare(strict_types=1);

namespace Storewright\Core\Payments;

use Storewright\Platform\GatewayCredentials;

final class Health {

	private const STUCK_AFTER_MINUTES = 10;

	public static function describe(): array {
		global $wpdb;
		$modes  = class_exists( GatewayCredentials::class ) ? GatewayCredentials::modes( wp_get_environment_type() ) : [];
		$rows   = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT gateway,
					SUM(received_at >= UTC_TIMESTAMP() - INTERVAL 1 DAY) AS day,
					SUM(status = %s) AS failed,
					SUM(status = %s AND received_at < UTC_TIMESTAMP() - INTERVAL %d MINUTE) AS stuck,
					MAX(received_at) AS last
				FROM %i GROUP BY gateway',
				WebhookGuard::FAILED,
				WebhookGuard::RECEIVED,
				self::STUCK_AFTER_MINUTES,
				$wpdb->prefix . 'storewright_payment_events'
			),
			ARRAY_A
		);
		$events = array_column( (array) $rows, null, 'gateway' );
		$out    = [];
		foreach ( [ 'razorpay', 'stripe' ] as $gateway ) {
			$row   = $events[ $gateway ] ?? [];
			$out[] = [
				'gateway' => $gateway,
				'webhook' => $gateway === 'stripe' ? rest_url( 'storewright/v1/webhooks/stripe' ) : admin_url( 'admin-post.php?action=rzp_wc_webhook' ),
				'mode'    => $modes[ $gateway ] ?? 'off',
				'day'     => (int) ( $row['day'] ?? 0 ),
				'failed'  => (int) ( $row['failed'] ?? 0 ),
				'stuck'   => (int) ( $row['stuck'] ?? 0 ),
				'last'    => isset( $row['last'] ) ? get_date_from_gmt( (string) $row['last'], 'c' ) : null,
			];
		}
		$cod = (array) get_option( 'woocommerce_cod_settings', [] );
		return [
			'gateways'       => $out,
			'cashOnDelivery' => ( $cod['enabled'] ?? 'no' ) === 'yes',
		];
	}
}
