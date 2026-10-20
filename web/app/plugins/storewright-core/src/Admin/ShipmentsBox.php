<?php

declare(strict_types=1);

namespace Storewright\Core\Admin;

use Storewright\Core\Fulfilment\CarrierException;
use Storewright\Core\Fulfilment\Quote;
use Storewright\Core\Fulfilment\ShipmentStore;
use Storewright\Core\Fulfilment\Shipping;
use Storewright\Core\Fulfilment\TrackingEvent;
use Storewright\Platform\CarrierCredentials;
use WC_Order;
use WP_Post;

final class ShipmentsBox {

	private const QUOTES_TTL = 10 * MINUTE_IN_SECONDS;

	public static function register(): void {
		add_action( 'add_meta_boxes', [ self::class, 'box' ] );
		Action::on( 'shipment_book', [ self::class, 'book' ] );
		Action::on( 'shipment_cancel', [ self::class, 'cancel' ] );
		Action::on( 'shipment_retry', [ self::class, 'retry' ] );
		Action::on( 'shipment_label', [ self::class, 'label' ] );
	}

	public static function box(): void {
		foreach ( array_unique( [ wc_get_page_screen_id( 'shop-order' ), 'shop_order' ] ) as $screen ) {
			add_meta_box( 'storewright-shipping', __( 'Shipping with Shiprocket', 'storewright' ), [ self::class, 'render' ], $screen, 'normal', 'default' );
		}
	}

	public static function render( mixed $screenObject ): void {
		$order = $screenObject instanceof WC_Order ? $screenObject : wc_get_order( $screenObject instanceof WP_Post ? $screenObject->ID : 0 );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$mode = CarrierCredentials::modes( wp_get_environment_type() )['shiprocket'];
		echo '<div class="sw-shipping">';
		if ( $mode === CarrierCredentials::OFF ) {
			echo '<p>' . esc_html__( 'Shiprocket is off: add SHIPROCKET_EMAIL and SHIPROCKET_PASSWORD to the server settings to book shipments from here.', 'storewright' ) . '</p></div>';
			return;
		}
		if ( $mode === CarrierCredentials::REFUSED ) {
			echo '<p>' . esc_html__( 'Live Shiprocket credentials are refused outside production, so booking is off here.', 'storewright' ) . '</p></div>';
			return;
		}
		self::bookings( $order );
		self::offer( $order, $mode );
		echo '</div>';
	}

	public static function book(): void {
		$order = wc_get_order( (int) Action::text( 'order' ) );
		if ( ! $order instanceof WC_Order ) {
			Flash::error( __( 'That order does not exist.', 'storewright' ) );
			Action::back( admin_url( 'admin.php?page=wc-orders' ) );
		}
		try {
			Shipping::ship( $order, Action::text( 'service' ) );
			Flash::success( __( 'Booking the shipment with Shiprocket; the waybill appears here within a minute.', 'storewright' ) );
		} catch ( CarrierException $e ) {
			Flash::error( $e->getMessage() );
		}
		Action::back( $order->get_edit_order_url() );
	}

	public static function cancel(): void {
		self::act(
			static function ( int $id ): void {
				Shipping::cancel( $id );
				Flash::success( __( 'Cancelled with Shiprocket.', 'storewright' ) );
			}
		);
	}

	public static function retry(): void {
		self::act(
			static function ( int $id ): void {
				Shipping::retry( $id );
				Flash::success( __( 'Trying again in the background.', 'storewright' ) );
			}
		);
	}

	public static function label(): void {
		self::act(
			static function ( int $id ): void {
				Shipping::label( $id, Shipping::LAST_TRY );
				Flash::success( __( 'The label is ready.', 'storewright' ) );
			}
		);
	}

	public static function where( array $row ): string {
		$status = (string) $row['status'];
		$text   = match ( $status ) {
			ShipmentStore::BOOKING   => __( 'Booking with Shiprocket', 'storewright' ),
			ShipmentStore::BOOKED    => $row['pickup_at'] !== '' && $row['pickup_at'] !== null ? __( 'Pickup requested', 'storewright' ) : __( 'Waybill allotted, pickup being arranged', 'storewright' ),
			ShipmentStore::FAILED    => __( 'Booking failed', 'storewright' ),
			ShipmentStore::CANCELLED => __( 'Cancelled', 'storewright' ),
			default                  => TrackingEvent::words( $status ),
		};
		if ( $row['last_error'] !== '' && $row['last_error'] !== null ) {
			$text .= ': ' . $row['last_error'];
		}
		return $text;
	}

	private static function act( callable $work ): void {
		$id  = (int) Action::query( 'id' );
		$row = Shipping::store()->find( $id );
		if ( $row === null ) {
			Flash::error( __( 'That shipment does not exist.', 'storewright' ) );
			Action::back( admin_url( 'admin.php?page=wc-orders' ) );
		}
		try {
			$work( $id );
		} catch ( CarrierException $e ) {
			Flash::error( $e->getMessage() );
		}
		$back  = Action::query( 'back' );
		$order = wc_get_order( (int) $row['order_id'] );
		Action::back( $back !== '' ? admin_url( $back ) : ( $order instanceof WC_Order ? $order->get_edit_order_url() : admin_url( 'admin.php?page=wc-orders' ) ) );
	}

	private static function bookings( WC_Order $order ): void {
		$rows = Shipping::store()->forOrder( $order->get_id() );
		if ( $rows === [] ) {
			return;
		}
		echo '<table class="widefat striped sw-shipping__table"><thead><tr><th>' . esc_html__( 'Courier', 'storewright' ) . '</th><th>' . esc_html__( 'Waybill', 'storewright' ) . '</th><th>' . esc_html__( 'Where it is', 'storewright' ) . '</th><th>' . esc_html__( 'Label', 'storewright' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$id      = (int) $row['id'];
			$courier = $row['courier'] !== '' ? (string) $row['courier'] : __( 'Being chosen', 'storewright' );
			if ( $row['kind'] === ShipmentStore::RETURN ) {
				/* translators: %s: courier name */
				$courier = sprintf( __( 'Return pickup, %s', 'storewright' ), $courier );
			}
			echo '<tr>';
			echo '<td>' . esc_html( $courier ) . '</td>';
			echo '<td>' . ( $row['awb'] !== '' ? '<a href="' . esc_url( 'https://shiprocket.co/tracking/' . $row['awb'] ) . '" target="_blank" rel="noopener">' . esc_html( (string) $row['awb'] ) . '</a>' : '&mdash;' ) . '</td>';
			echo '<td>' . esc_html( self::where( $row ) ) . '</td>';
			echo '<td>' . self::labelCell( $row ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td class="sw-shipping__actions">';
			if ( $row['last_error'] !== '' && $row['last_error'] !== null ) {
				echo '<a href="' . esc_url( Action::url( 'shipment_retry', [ 'id' => $id ] ) ) . '">' . esc_html__( 'Try again', 'storewright' ) . '</a> ';
			}
			if ( ! ShipmentStore::isClosed( (string) $row['status'] ) ) {
				echo '<a href="' . esc_url( Action::url( 'shipment_cancel', [ 'id' => $id ] ) ) . '">' . esc_html__( 'Cancel this shipment', 'storewright' ) . '</a>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function labelCell( array $row ): string {
		if ( $row['label_url'] !== '' && $row['label_url'] !== null ) {
			return '<a href="' . esc_url( (string) $row['label_url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Print label', 'storewright' ) . '</a>';
		}
		if ( $row['awb'] === '' || ShipmentStore::isClosed( (string) $row['status'] ) ) {
			return '&mdash;';
		}
		return '<a href="' . esc_url( Action::url( 'shipment_label', [ 'id' => (int) $row['id'] ] ) ) . '">' . esc_html__( 'Fetch label', 'storewright' ) . '</a>';
	}

	private static function offer( WC_Order $order, string $mode ): void {
		$items = Shipping::pending( $order );
		if ( $items === [] ) {
			echo '<p>' . esc_html__( 'Everything in this order that travels has a shipment.', 'storewright' ) . '</p>';
			return;
		}
		try {
			$quotes = self::quotes( $order, $items );
		} catch ( CarrierException $e ) {
			echo '<p>' . esc_html( __( 'Shiprocket could not quote this order: ', 'storewright' ) . $e->getMessage() ) . '</p>';
			return;
		}
		if ( $quotes === [] ) {
			echo '<p>' . esc_html__( 'No courier serves this address for this parcel.', 'storewright' ) . '</p>';
			return;
		}
		$count = array_sum( array_column( $items, 'qty' ) );
		// The order screen is one form already, so the button sends that form to our handler instead of nesting another.
		echo '<p class="sw-shipping__offer">';
		Action::fields( 'shipment_book' );
		echo '<input type="hidden" name="order" value="' . esc_attr( (string) $order->get_id() ) . '">';
		echo '<label for="sw-shipping-service">' . esc_html__( 'Courier', 'storewright' ) . '</label> ';
		echo '<select name="service" id="sw-shipping-service">';
		foreach ( $quotes as $quote ) {
			echo '<option value="' . esc_attr( $quote->service ) . '"' . selected( $quote->recommended, true, false ) . '>' . esc_html( self::describe( $quote ) ) . '</option>';
		}
		echo '</select> ';
		/* translators: %d: number of items */
		echo '<button type="submit" class="button button-primary" formmethod="post" formaction="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . esc_html( sprintf( _n( 'Ship %d item with Shiprocket', 'Ship %d items with Shiprocket', $count, 'storewright' ), $count ) ) . '</button>';
		if ( $mode === CarrierCredentials::SANDBOX ) {
			echo ' <span class="description">' . esc_html__( 'Sandbox: nothing is really shipped.', 'storewright' ) . '</span>';
		}
		echo '</p>';
	}

	/** @return list<Quote> */
	private static function quotes( WC_Order $order, array $items ): array {
		$key    = 'storewright_quotes_' . $order->get_id() . '_' . md5( (string) wp_json_encode( $items ) );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$carrier = Shipping::carrier();
		$quotes  = $carrier === null ? [] : $carrier->quotes( Shipping::consignment( $order, $items ) );
		set_transient( $key, $quotes, self::QUOTES_TTL );
		return $quotes;
	}

	private static function describe( Quote $quote ): string {
		$text = $quote->name . ', ' . html_entity_decode( wp_strip_all_tags( wc_price( $quote->rate ) ) );
		if ( $quote->arrives !== '' ) {
			/* translators: %s: date */
			$text .= ', ' . sprintf( __( 'by %s', 'storewright' ), $quote->arrives );
		}
		return $quote->recommended ? $text . ' ' . __( '(recommended)', 'storewright' ) : $text;
	}
}
