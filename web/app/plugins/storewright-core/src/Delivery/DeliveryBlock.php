<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

use Storewright\Core\Asset;

final class DeliveryBlock {

	public static function register(): void {
		register_block_type(
			'storewright/delivery',
			[
				'api_version'     => '3',
				'attributes'      => [
					'variant' => [
						'type'    => 'string',
						'default' => 'header',
					],
				],
				'render_callback' => [ self::class, 'render' ],
			]
		);
		add_action( 'template_redirect', [ self::class, 'acceptFormWithoutScript' ] );
	}

	public static function acceptFormWithoutScript(): void {
		if ( ! isset( $_GET['storewright_pin'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		ShopperCheck::byPin( sanitize_text_field( wp_unslash( $_GET['storewright_pin'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		wp_safe_redirect( remove_query_arg( 'storewright_pin' ) );
		exit;
	}

	public static function render( array $attributes ): string {
		$current = function_exists( 'WC' ) && WC()->session ? ShopperCheck::current() : null;
		$product = ( $attributes['variant'] ?? 'header' ) === 'product';
		wp_enqueue_script(
			'storewright-delivery',
			Asset::url( 'assets/js/delivery.js' ),
			[],
			Asset::version( 'assets/js/delivery.js' ),
			[
				'in_footer' => true,
				'strategy'  => 'defer',
			]
		);
		wp_localize_script(
			'storewright-delivery',
			'storewrightDelivery',
			[
				'endpoint' => esc_url_raw( rest_url( 'storewright/v1/delivery' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'locating' => __( 'Finding your location…', 'storewright' ),
				'denied'   => __( 'Location was not shared. Enter your PIN code instead.', 'storewright' ),
			]
		);

		$field   = wp_unique_id( 'sw-pin-' );
		$label   = $current ? $current['pin'] : __( 'your area', 'storewright' );
		$message = $current['message'] ?? '';
		$status  = $current['status'] ?? 'unknown';
		$result  = sprintf(
			'<p class="sw-delivery__result sw-delivery__result--%1$s" role="status" aria-live="polite" data-sw-delivery-result>%2$s</p>',
			esc_attr( $status ),
			esc_html( $message )
		);
		ob_start();
		?>
		<div class="sw-delivery sw-delivery--<?php echo esc_attr( $product ? 'product' : 'header' ); ?>">
			<details class="sw-delivery__details">
				<summary class="sw-delivery__summary">
					<span class="sw-delivery__lead"><?php esc_html_e( 'Deliver to', 'storewright' ); ?></span>
					<strong class="sw-delivery__pin" data-sw-delivery-label><?php echo esc_html( $label ); ?></strong>
					<?php if ( $product ) : ?>
						<span class="sw-delivery__change"><?php esc_html_e( 'Change', 'storewright' ); ?></span>
					<?php endif; ?>
				</summary>
				<div class="sw-delivery__panel">
					<form class="sw-delivery__form" method="get" data-sw-delivery-form>
						<label for="<?php echo esc_attr( $field ); ?>"><?php esc_html_e( 'PIN code', 'storewright' ); ?></label>
						<div class="sw-delivery__row">
							<input id="<?php echo esc_attr( $field ); ?>" type="text" name="storewright_pin" inputmode="numeric" pattern="[1-8][0-9]{5}" maxlength="6" autocomplete="postal-code" required value="<?php echo esc_attr( $current['pin'] ?? '' ); ?>">
							<button type="submit" class="wp-element-button"><?php esc_html_e( 'Check', 'storewright' ); ?></button>
						</div>
					</form>
					<button type="button" class="sw-delivery__locate" data-sw-delivery-locate hidden><?php esc_html_e( 'Use my location', 'storewright' ); ?></button>
					<?php echo $product ? '' : $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</details>
			<?php if ( $product ) : ?>
				<?php echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<ul class="sw-delivery__options" data-sw-delivery-options>
					<?php foreach ( $current['options'] ?? [] as $storewright_option ) : ?>
						<li><span><?php echo esc_html( $storewright_option['title'] ); ?></span><span><?php echo esc_html( $storewright_option['charge'] ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
