<?php
$storewright_promises = [
	[ 'truck', __( 'Tracked delivery', 'storewright' ), __( 'Follow every parcel from dispatch to your door, with same-day options where we are close by.', 'storewright' ) ],
	[ 'return', __( 'Simple returns', 'storewright' ), __( 'Changed your mind? Request a return from your account in a minute and we collect it.', 'storewright' ) ],
	[ 'wallet', __( 'Pay your way', 'storewright' ), __( 'UPI, cards, netbanking, wallets and cash on delivery. Every payment on a secure page.', 'storewright' ) ],
	[ 'shield', __( 'Made to last', 'storewright' ), __( 'We choose things we would keep ourselves and stand behind every one of them.', 'storewright' ) ],
];
?>
<section class="sw-section sw-promises alignfull">
	<div class="sw-promises__inner">
		<?php foreach ( $storewright_promises as [ $storewright_icon, $storewright_title, $storewright_text ] ) : ?>
			<div class="sw-promise">
				<span class="sw-promise__icon"><?php echo storewright_icon( $storewright_icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<h3 class="sw-promise__title"><?php echo esc_html( $storewright_title ); ?></h3>
				<p class="sw-promise__text"><?php echo esc_html( $storewright_text ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
</section>
