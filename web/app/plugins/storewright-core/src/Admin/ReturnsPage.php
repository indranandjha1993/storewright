<?php

declare(strict_types=1);

namespace Storewright\Core\Admin;

use InvalidArgumentException;
use Storewright\Core\Returns\ReturnDesk;
use Storewright\Core\Returns\ReturnRequests;

final class ReturnsPage {

	public const SLUG = 'storewright-returns';

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ], 20 );
		Action::on( 'return_move', [ self::class, 'move' ] );
	}

	public static function url( bool $all = false ): string {
		return admin_url( 'admin.php?page=' . self::SLUG . ( $all ? '&view=all' : '' ) );
	}

	public static function menu(): void {
		add_submenu_page( 'woocommerce', __( 'Returns', 'storewright' ), __( 'Returns', 'storewright' ), 'manage_woocommerce', self::SLUG, [ self::class, 'render' ] );
	}

	public static function move(): void {
		$all = Action::text( 'view' ) === 'all';
		try {
			$moved = ReturnDesk::move( (int) Action::text( 'order' ), Action::text( 'id' ), Action::text( 'status' ), Action::lines( 'note' ) );
			/* translators: 1: order number, 2: return state */
			Flash::success( sprintf( __( 'Return on order #%1$s is now %2$s.', 'storewright' ), $moved['number'], self::stateLabel( $moved['status'] ) ) );
		} catch ( InvalidArgumentException $e ) {
			Flash::error( $e->getMessage() );
		}
		Action::back( self::url( $all ) );
	}

	public static function render(): void {
		$all  = Action::query( 'view' ) === 'all';
		$rows = ReturnDesk::rows( $all );
		echo '<div class="wrap sw-returns">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Returns', 'storewright' ) . '</h1>';
		echo '<p class="sw-lead">' . esc_html__( 'Customers ask from their order page within the return window. Approve, collect, then refund: online payments go back automatically, cash on delivery is refunded by you.', 'storewright' ) . '</p>';
		printf(
			'<ul class="subsubsub"><li><a href="%s" class="%s">%s</a> |</li><li><a href="%s" class="%s">%s</a></li></ul>',
			esc_url( self::url() ),
			$all ? '' : 'current',
			esc_html__( 'Open', 'storewright' ),
			esc_url( self::url( true ) ),
			$all ? 'current' : '',
			esc_html__( 'All', 'storewright' )
		);
		if ( $rows === [] ) {
			echo '<p class="sw-empty">' . esc_html( $all ? __( 'No returns yet.', 'storewright' ) : __( 'No returns waiting.', 'storewright' ) ) . '</p></div>';
			return;
		}
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
		foreach ( [ __( 'Order', 'storewright' ), __( 'Items', 'storewright' ), __( 'Reason', 'storewright' ), __( 'State', 'storewright' ), __( 'Next step', 'storewright' ) ] as $head ) {
			echo '<th scope="col">' . esc_html( $head ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			self::row( $row, $all );
		}
		echo '</tbody></table></div>';
	}

	private static function row( array $row, bool $all ): void {
		[ $label, $tone ] = self::state( $row['status'] );
		echo '<tr>';
		printf(
			'<td><a class="row-title" href="%s">#%s</a><br>%s<br><small>%s</small></td>',
			esc_url( admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $row['order'] ) ),
			esc_html( $row['number'] ),
			esc_html( $row['customer'] ),
			/* translators: 1: amount, 2: payment method, 3: relative time */
			esc_html( sprintf( __( '%1$s paid by %2$s, asked %3$s ago', 'storewright' ), wp_strip_all_tags( wc_price( $row['amount'] ) ), $row['payment'] ?: '-', human_time_diff( (int) strtotime( $row['created'] ) ) ) )
		);
		printf( '<td>%s</td>', esc_html( $row['summary'] ) );
		printf( '<td>%s%s</td>', esc_html( $row['reasonText'] ), $row['note'] ? '<br><small>' . esc_html( $row['note'] ) . '</small>' : '' );
		printf( '<td><span class="sw-pill %s">%s</span></td>', esc_attr( $tone ), esc_html( $label ) );
		echo '<td>';
		if ( $row['next'] !== [] ) {
			printf( '<form method="post" action="%s" class="sw-returns__form">', esc_url( admin_url( 'admin-post.php' ) ) );
			Action::fields( 'return_move' );
			printf( '<input type="hidden" name="order" value="%d"><input type="hidden" name="id" value="%s"><input type="hidden" name="view" value="%s">', (int) $row['order'], esc_attr( $row['id'] ), $all ? 'all' : '' );
			printf( '<label class="screen-reader-text" for="sw-note-%1$s">%2$s</label><input type="text" id="sw-note-%1$s" name="note" placeholder="%2$s">', esc_attr( $row['id'] ), esc_attr__( 'Message to the customer (optional)', 'storewright' ) );
			echo '<span class="sw-returns__buttons">';
			foreach ( $row['next'] as $status ) {
				printf( '<button type="submit" name="status" value="%s" class="button %s">%s</button>', esc_attr( $status ), $status === 'declined' ? 'button-link-delete' : 'button-primary', esc_html( self::actionLabel( $status ) ) );
			}
			echo '</span></form>';
		}
		echo '</td></tr>';
	}

	private static function state( string $status ): array {
		$tone = match ( $status ) {
			'requested' => 'is-good',
			'approved', 'received' => 'is-quiet',
			'declined' => 'is-bad',
			default => '',
		};
		return [ self::stateLabel( $status ), $tone ];
	}

	private static function stateLabel( string $status ): string {
		return match ( $status ) {
			'requested' => __( 'New', 'storewright' ),
			'approved'  => __( 'Pickup arranged', 'storewright' ),
			'received'  => __( 'Back with us', 'storewright' ),
			'refunded'  => __( 'Refunded', 'storewright' ),
			'declined'  => __( 'Declined', 'storewright' ),
			default     => $status,
		};
	}

	private static function actionLabel( string $status ): string {
		return match ( $status ) {
			'approved' => __( 'Approve and arrange pickup', 'storewright' ),
			'received' => __( 'Mark as received', 'storewright' ),
			'refunded' => __( 'Refund', 'storewright' ),
			'declined' => __( 'Decline', 'storewright' ),
			default    => $status,
		};
	}
}
