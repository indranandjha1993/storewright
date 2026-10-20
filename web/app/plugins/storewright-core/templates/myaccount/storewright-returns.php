<?php

use Storewright\Core\Returns\ReturnRequests;

defined( 'ABSPATH' ) || exit;

$storewright_states = [
	'requested' => __( 'Requested', 'storewright' ),
	'approved'  => __( 'Approved, pickup on the way', 'storewright' ),
	'received'  => __( 'Received, refund coming', 'storewright' ),
	'refunded'  => __( 'Refunded', 'storewright' ),
	'declined'  => __( 'Declined', 'storewright' ),
];
?>
<section class="sw-returns" aria-label="<?php esc_attr_e( 'Returns', 'storewright' ); ?>">
	<h2><?php esc_html_e( 'Returns', 'storewright' ); ?></h2>
	<?php foreach ( $requests as $storewright_request ) : ?>
		<div class="sw-return sw-return--<?php echo esc_attr( $storewright_request['status'] ); ?>">
			<p class="sw-return__head">
				<strong><?php echo esc_html( $storewright_states[ $storewright_request['status'] ] ?? $storewright_request['status'] ); ?></strong>
				<span><?php echo esc_html( wc_format_datetime( wc_string_to_datetime( $storewright_request['created'] ) ) ); ?></span>
			</p>
			<p><?php echo esc_html( ReturnRequests::summary( $order, $storewright_request['items'] ) ); ?></p>
			<?php if ( isset( $pickups[ $storewright_request['id'] ] ) && $pickups[ $storewright_request['id'] ]['awb'] !== '' ) : ?>
				<p class="sw-return__pickup">
					<?php
					/* translators: 1: courier name, 2: waybill number */
					echo esc_html( sprintf( __( '%1$s collects it, waybill %2$s.', 'storewright' ), $pickups[ $storewright_request['id'] ]['courier'], $pickups[ $storewright_request['id'] ]['awb'] ) );
					?>
				</p>
			<?php endif; ?>
			<?php if ( ( $storewright_request['answer'] ?? '' ) !== '' ) : ?>
				<p class="sw-return__answer"><?php echo esc_html( $storewright_request['answer'] ); ?></p>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
	<?php if ( $left !== [] ) : ?>
		<details class="sw-return-form">
			<summary class="wp-element-button"><?php esc_html_e( 'Return items', 'storewright' ); ?></summary>
			<form method="post" aria-label="<?php esc_attr_e( 'Return items', 'storewright' ); ?>">
				<?php wp_nonce_field( $nonce ); ?>
				<input type="hidden" name="storewright_return_order" value="<?php echo esc_attr( (string) $order->get_id() ); ?>">
				<p class="sw-return-form__lead">
					<?php
					/* translators: %s: last date for returns */
					echo esc_html( sprintf( __( 'You can send items back until %s. We collect them from your door and refund the way you paid.', 'storewright' ), wp_date( get_option( 'date_format' ), (int) $deadline ) ) );
					?>
				</p>
				<fieldset>
					<legend><?php esc_html_e( 'What is coming back', 'storewright' ); ?></legend>
					<?php foreach ( $left as $storewright_id => $storewright_max ) : ?>
						<?php $storewright_item = $order->get_item( $storewright_id ); ?>
						<label class="sw-return-form__item">
							<span><?php echo esc_html( $storewright_item ? $storewright_item->get_name() : '' ); ?></span>
							<select name="storewright_return_items[<?php echo esc_attr( (string) $storewright_id ); ?>]" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: product name */ __( 'How many of %s', 'storewright' ), $storewright_item ? $storewright_item->get_name() : '' ) ); ?>">
								<?php for ( $storewright_qty = 0; $storewright_qty <= $storewright_max; $storewright_qty++ ) : ?>
									<option value="<?php echo esc_attr( (string) $storewright_qty ); ?>"><?php echo esc_html( (string) $storewright_qty ); ?></option>
								<?php endfor; ?>
							</select>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<p>
					<label for="storewright_return_reason"><?php esc_html_e( 'Why', 'storewright' ); ?></label>
					<select id="storewright_return_reason" name="storewright_return_reason" required>
						<option value=""><?php esc_html_e( 'Choose a reason', 'storewright' ); ?></option>
						<?php foreach ( ReturnRequests::REASONS as $storewright_reason ) : ?>
							<option value="<?php echo esc_attr( $storewright_reason ); ?>"><?php echo esc_html( ReturnRequests::reason( $storewright_reason ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label for="storewright_return_note"><?php esc_html_e( 'Anything we should know (optional)', 'storewright' ); ?></label>
					<textarea id="storewright_return_note" name="storewright_return_note" rows="3" maxlength="500"></textarea>
				</p>
				<button type="submit" class="wp-element-button"><?php esc_html_e( 'Request the return', 'storewright' ); ?></button>
			</form>
		</details>
	<?php endif; ?>
</section>
