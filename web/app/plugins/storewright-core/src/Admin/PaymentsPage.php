<?php

declare(strict_types=1);

namespace Storewright\Core\Admin;

use Storewright\Core\Payments\EventLog;
use Storewright\Core\Payments\Health;
use Storewright\Core\Payments\Settlement;
use WC_Order;

final class PaymentsPage {

	public const SLUG = 'storewright-payments';

	private const NAMES = [
		'razorpay' => 'Razorpay',
		'stripe'   => 'Stripe',
	];

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ], 20 );
		Action::on( 'payment_check', [ self::class, 'check' ] );
	}

	public static function url( array $args = [] ): string {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . self::SLUG ) );
	}

	public static function menu(): void {
		add_submenu_page( 'woocommerce', __( 'Payments', 'storewright' ), __( 'Payments', 'storewright' ), 'manage_woocommerce', self::SLUG, [ self::class, 'render' ] );
	}

	public static function check(): void {
		$order = wc_get_order( (int) Action::query( 'id' ) );
		if ( ! $order instanceof WC_Order ) {
			Flash::error( __( 'That order does not exist.', 'storewright' ) );
			Action::back( self::url() );
		}
		$answer = Settlement::check( $order );
		/* translators: 1: order number, 2: gateway message */
		$text = sprintf( __( 'Order #%1$s: %2$s', 'storewright' ), $order->get_order_number(), $answer['message'] );
		if ( $answer['status'] === 'paid' ) {
			Flash::success( $text );
		} else {
			Flash::error( $text );
		}
		Action::back( self::url() );
	}

	public static function render(): void {
		echo '<div class="wrap sw-payments">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Payments', 'storewright' ) . '</h1>';
		echo '<p class="sw-lead">' . esc_html__( 'Gateway keys come from the server environment. This page shows whether each gateway is working and which payments need a look.', 'storewright' ) . '</p>';
		self::gateways( Health::describe() );
		self::unsettled( Settlement::pending() );
		self::events();
		echo '</div>';
	}

	private static function gateways( array $health ): void {
		$modes = [
			'live'    => [ __( 'Live', 'storewright' ), 'is-good' ],
			'sandbox' => [ __( 'Sandbox', 'storewright' ), 'is-quiet' ],
			'refused' => [ __( 'Live keys refused here', 'storewright' ), 'is-bad' ],
			'off'     => [ __( 'Not set up', 'storewright' ), 'is-bad' ],
		];
		echo '<div class="sw-cards">';
		foreach ( $health['gateways'] as $gateway ) {
			[ $label, $tone ] = $modes[ $gateway['mode'] ] ?? $modes['off'];
			$healthy          = $gateway['failed'] === 0 && $gateway['stuck'] === 0;
			printf( '<section class="sw-card" aria-label="%1$s"><header class="sw-card__head"><h2>%1$s</h2><span class="sw-pill %2$s">%3$s</span></header>', esc_attr( self::NAMES[ $gateway['gateway'] ] ?? $gateway['gateway'] ), esc_attr( $tone ), esc_html( $label ) );
			echo '<dl class="sw-facts">';
			printf( '<dt>%s</dt><dd>%s</dd>', esc_html__( 'Last webhook', 'storewright' ), $gateway['last'] ? esc_html( sprintf( /* translators: %s: relative time */ __( '%s ago', 'storewright' ), human_time_diff( (int) strtotime( $gateway['last'] ) ) ) ) : esc_html__( 'None yet', 'storewright' ) );
			/* translators: %s: number of events */
			printf( '<dt>%s</dt><dd>%s</dd>', esc_html__( 'Last 24 hours', 'storewright' ), esc_html( sprintf( __( '%s events', 'storewright' ), number_format_i18n( $gateway['day'] ) ) ) );
			printf(
				'<dt>%s</dt><dd class="%s">%s</dd>',
				esc_html__( 'Problems', 'storewright' ),
				$healthy ? '' : 'is-bad',
				/* translators: 1: failed events, 2: stuck events */
				$healthy ? esc_html__( 'None', 'storewright' ) : esc_html( sprintf( __( '%1$s failed, %2$s waiting', 'storewright' ), number_format_i18n( $gateway['failed'] ), number_format_i18n( $gateway['stuck'] ) ) )
			);
			echo '</dl>';
			printf( '<p class="sw-card__note">%s<br><code>%s</code></p></section>', esc_html__( 'Webhook address', 'storewright' ), esc_html( $gateway['webhook'] ) );
		}
		printf(
			'<section class="sw-card" aria-label="%1$s"><header class="sw-card__head"><h2>%1$s</h2><span class="sw-pill %2$s">%3$s</span></header><p class="sw-card__note">%4$s</p></section>',
			esc_attr__( 'Cash on delivery', 'storewright' ),
			$health['cashOnDelivery'] ? 'is-good' : 'is-quiet',
			$health['cashOnDelivery'] ? esc_html__( 'On', 'storewright' ) : esc_html__( 'Off', 'storewright' ),
			esc_html__( 'Offered for goods that ship. Orders stay unpaid until you mark them completed after delivery.', 'storewright' )
		);
		echo '</div>';
	}

	private static function unsettled( array $orders ): void {
		echo '<section class="sw-section" aria-label="' . esc_attr__( 'Waiting for payment', 'storewright' ) . '"><h2>' . esc_html__( 'Waiting for payment', 'storewright' ) . '</h2>';
		echo '<p class="sw-lead">' . esc_html__( 'Online orders still unpaid after 15 minutes, from the last 7 days. Ask the gateway whether the money arrived; a paid order is recorded straight away.', 'storewright' ) . '</p>';
		if ( $orders === [] ) {
			echo '<p class="sw-empty">' . esc_html__( 'Every online order is settled.', 'storewright' ) . '</p></section>';
			return;
		}
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
		foreach ( [ __( 'Order', 'storewright' ), __( 'Customer', 'storewright' ), __( 'Gateway', 'storewright' ), __( 'Total', 'storewright' ), '' ] as $head ) {
			echo '<th scope="col">' . esc_html( $head ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $orders as $order ) {
			$name = self::NAMES[ $order['gateway'] ] ?? $order['title'];
			printf(
				'<tr><td><a class="row-title" href="%s">#%s</a><br><small>%s</small></td><td>%s</td><td>%s</td><td>%s</td><td><a class="button" href="%s">%s</a></td></tr>',
				esc_url( admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order['id'] ) ),
				esc_html( $order['number'] ),
				/* translators: %s: relative time */
				esc_html( sprintf( __( '%s ago', 'storewright' ), human_time_diff( (int) strtotime( (string) $order['created'] ) ) ) ),
				esc_html( $order['customer'] ),
				esc_html( $name ),
				wp_kses_post( wc_price( (float) $order['total'] ) ),
				esc_url( Action::url( 'payment_check', [ 'id' => $order['id'] ] ) ),
				/* translators: %s: gateway name */
				esc_html( sprintf( __( 'Check with %s', 'storewright' ), $name ) )
			);
		}
		echo '</tbody></table></section>';
	}

	private static function events(): void {
		$status = Action::query( 'status' );
		$page   = max( 1, (int) Action::query( 'paged' ) );
		$log    = EventLog::page( '', $status, $page );
		$views  = [
			''         => __( 'All', 'storewright' ),
			'failed'   => __( 'Failed', 'storewright' ),
			'received' => __( 'Waiting', 'storewright' ),
		];
		$labels = [
			'processed' => [ __( 'Processed', 'storewright' ), 'is-good' ],
			'received'  => [ __( 'Waiting', 'storewright' ), 'is-quiet' ],
			'failed'    => [ __( 'Failed', 'storewright' ), 'is-bad' ],
		];
		echo '<section class="sw-section" aria-label="' . esc_attr__( 'Webhook log', 'storewright' ) . '"><h2>' . esc_html__( 'Webhook log', 'storewright' ) . '</h2>';
		echo '<ul class="subsubsub">';
		$links = [];
		foreach ( $views as $key => $label ) {
			$links[] = sprintf( '<li><a href="%s" class="%s">%s</a></li>', esc_url( self::url( $key === '' ? [] : [ 'status' => $key ] ) ), $key === $status ? 'current' : '', esc_html( $label ) );
		}
		echo implode( ' | ', $links ) . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $log['items'] === [] ) {
			echo '<p class="sw-empty">' . esc_html__( 'No webhook events here.', 'storewright' ) . '</p></section>';
			return;
		}
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
		foreach ( [ __( 'Received', 'storewright' ), __( 'Gateway', 'storewright' ), __( 'Event', 'storewright' ), __( 'Status', 'storewright' ), __( 'Deliveries', 'storewright' ) ] as $head ) {
			echo '<th scope="col">' . esc_html( $head ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $log['items'] as $event ) {
			[ $label, $tone ] = $labels[ $event['status'] ] ?? [ $event['status'], '' ];
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s<br><small>%s</small></td><td><span class="sw-pill %s">%s</span></td><td>%d</td></tr>',
				/* translators: %s: relative time */
				esc_html( sprintf( __( '%s ago', 'storewright' ), human_time_diff( (int) strtotime( $event['received'] ) ) ) ),
				esc_html( self::NAMES[ $event['gateway'] ] ?? $event['gateway'] ),
				esc_html( $event['type'] ),
				esc_html( $event['id'] ),
				esc_attr( $tone ),
				esc_html( $label ),
				(int) $event['deliveries']
			);
		}
		echo '</tbody></table>';
		$pages = paginate_links(
			[
				'base'      => self::url( $status === '' ? [] : [ 'status' => $status ] ) . '%_%',
				'format'    => '&paged=%#%',
				'current'   => $page,
				'total'     => $log['pages'],
				'prev_text' => __( 'Previous', 'storewright' ),
				'next_text' => __( 'Next', 'storewright' ),
			]
		);
		if ( is_string( $pages ) ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $pages ) . '</div></div>';
		}
		echo '</section>';
	}
}
