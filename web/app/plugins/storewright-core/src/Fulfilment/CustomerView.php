<?php

declare(strict_types=1);

namespace Storewright\Core\Fulfilment;

use Automattic\WooCommerce\Admin\Features\Fulfillments\DataStore\FulfillmentsDataStore;
use Automattic\WooCommerce\Admin\Features\Fulfillments\Fulfillment;
use Automattic\WooCommerce\Admin\Features\Fulfillments\FulfillmentsRenderer;
use WC_Order;

final class CustomerView {

	public static function register(): void {
		add_action( 'init', [ self::class, 'replace' ], 20 );
		add_filter( 'wc_get_template', [ self::class, 'plainTable' ], 10, 2 );
		add_filter( 'woocommerce_order_tracking_status', [ self::class, 'status' ], 20, 2 );
	}

	public static function plainTable( string $located, string $name ): string {
		return $name === 'order/order-details-fulfillments.php' ? wc_locate_template( 'order/order-details.php' ) : $located;
	}

	public static function status( string $text, mixed $order ): string {
		if ( ! $order instanceof WC_Order ) {
			return $text;
		}
		$state = match ( $order->get_status() ) {
			'pending'    => __( 'is waiting for payment', 'storewright' ),
			'on-hold'    => __( 'is on hold while we check something', 'storewright' ),
			'processing' => __( 'is being prepared', 'storewright' ),
			'completed'  => __( 'is complete', 'storewright' ),
			'cancelled'  => __( 'was cancelled', 'storewright' ),
			'refunded'   => __( 'was refunded', 'storewright' ),
			'failed'     => __( 'did not go through', 'storewright' ),
			default      => wc_get_order_status_name( $order->get_status() ),
		};
		$sent = match ( (string) $order->get_meta( '_fulfillment_status' ) ) {
			'fulfilled'           => ' ' . __( 'Everything in it has been sent.', 'storewright' ),
			'partially_fulfilled' => ' ' . __( 'Part of it has been sent.', 'storewright' ),
			default               => '',
		};
		return sprintf(
			/* translators: 1: order number, 2: order date, 3: order state such as "is being prepared" */
			__( 'Order %1$s, placed on %2$s, %3$s.', 'storewright' ),
			'<strong>#' . esc_html( $order->get_order_number() ) . '</strong>',
			esc_html( wc_format_datetime( $order->get_date_created() ) ),
			'<strong>' . esc_html( $state ) . '</strong>'
		) . esc_html( $sent );
	}

	public static function replace(): void {
		$stock = [ wc_get_container()->get( FulfillmentsRenderer::class ), 'render_fulfillment_customer_details' ];
		if ( has_action( 'woocommerce_order_details_before_order_table', $stock ) === false ) {
			return;
		}
		remove_action( 'woocommerce_order_details_before_order_table', $stock );
		add_action( 'woocommerce_order_details_before_order_table', [ self::class, 'render' ] );
	}

	public static function render( WC_Order $order ): void {
		$shipments = wc_get_container()->get( FulfillmentsDataStore::class )->read_fulfillments( WC_Order::class, (string) $order->get_id() );
		if ( $shipments === [] ) {
			return;
		}
		$sent = [];
		foreach ( $shipments as $shipment ) {
			foreach ( $shipment->get_items() as $item ) {
				$sent[ (int) $item['item_id'] ] = ( $sent[ (int) $item['item_id'] ] ?? 0 ) + (int) $item['qty'];
			}
		}
		$waiting = [];
		foreach ( $order->get_items() as $id => $item ) {
			$left = $item->get_quantity() - ( $sent[ $id ] ?? 0 );
			if ( $left > 0 ) {
				$waiting[] = self::line( $left, $item->get_name() );
			}
		}
		?>
		<section class="sw-order-shipments" aria-label="<?php esc_attr_e( 'Shipments', 'storewright' ); ?>">
			<ol>
				<?php foreach ( $shipments as $shipment ) : ?>
					<?php self::card( $order, $shipment ); ?>
				<?php endforeach; ?>
			</ol>
			<?php if ( $waiting !== [] ) : ?>
				<p class="sw-order-shipments__waiting">
					<?php
					/* translators: %s: list of items */
					echo esc_html( sprintf( __( 'Still to ship: %s', 'storewright' ), implode( ', ', $waiting ) ) );
					?>
				</p>
			<?php endif; ?>
		</section>
		<?php
	}

	private static function card( WC_Order $order, Fulfillment $shipment ): void {
		$carrier = Carriers::name( $shipment );
		$number  = (string) $shipment->get_tracking_number();
		$link    = (string) $shipment->get_tracking_url();
		$date    = $shipment->get_date_fulfilled();
		$items   = array_map(
			static fn( array $item ): string => self::line( (int) $item['qty'], (string) $order->get_item( (int) $item['item_id'] )?->get_name() ),
			$shipment->get_items()
		);
		$online  = $carrier === '' && $number === '' && $link === '';
		?>
		<li class="sw-order-shipment">
			<p class="sw-order-shipment__head">
				<strong><?php echo esc_html( $online ? __( 'Delivered online', 'storewright' ) : __( 'Shipped', 'storewright' ) ); ?></strong>
				<?php if ( $date ) : ?>
					<span><?php echo esc_html( wc_format_datetime( wc_string_to_datetime( $date ) ) ); ?></span>
				<?php endif; ?>
			</p>
			<?php if ( $carrier !== '' || $number !== '' ) : ?>
				<p>
					<?php echo esc_html( $carrier ); ?>
					<?php if ( $number !== '' ) : ?>
						<span class="sw-order-shipment__number"><?php echo esc_html( $number ); ?></span>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<p class="sw-order-shipment__items"><?php echo esc_html( implode( ', ', $items ) ); ?></p>
			<?php if ( $link !== '' ) : ?>
				<a class="wp-element-button sw-order-shipment__track" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Track parcel', 'storewright' ); ?></a>
			<?php endif; ?>
		</li>
		<?php
	}

	private static function line( int $qty, string $name ): string {
		/* translators: 1: quantity, 2: product name */
		return sprintf( __( '%1$d x %2$s', 'storewright' ), $qty, $name );
	}
}
