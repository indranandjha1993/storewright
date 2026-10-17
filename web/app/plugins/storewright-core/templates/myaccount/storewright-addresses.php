<?php

defined( 'ABSPATH' ) || exit;
?>
<div class="sw-addresses">
	<header class="sw-account__section-head">
		<h2><?php esc_html_e( 'Delivery addresses', 'storewright' ); ?></h2>
		<p><?php esc_html_e( 'Save the places you order to. Each one shows whether we deliver there.', 'storewright' ); ?></p>
	</header>
	<?php if ( $addresses === [] ) : ?>
		<p class="sw-account__empty"><?php esc_html_e( 'No delivery address yet. Add one below or at your next checkout.', 'storewright' ); ?></p>
	<?php endif; ?>
	<ul class="sw-addresses__list">
		<?php foreach ( $addresses as $storewright_id => $storewright_address ) : ?>
			<li class="sw-address<?php echo $storewright_id === 'default' ? ' is-default' : ''; ?>">
				<?php if ( $storewright_id === 'default' ) : ?>
					<span class="sw-address__tag"><?php esc_html_e( 'Default', 'storewright' ); ?></span>
				<?php endif; ?>
				<address class="sw-address__text"><?php echo wp_kses_post( $storewright_address['formatted'] ); ?></address>
				<?php if ( $storewright_address['phone'] !== '' ) : ?>
					<p class="sw-address__phone"><?php echo esc_html( $storewright_address['phone'] ); ?></p>
				<?php endif; ?>
				<p class="sw-deliverability sw-deliverability--<?php echo esc_attr( $storewright_address['status'] ); ?>"><?php echo esc_html( $storewright_address['badge'] ); ?></p>
				<div class="sw-address__actions">
					<?php if ( $storewright_id === 'default' ) : ?>
						<a class="sw-address__link" href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address', 'shipping' ) ); ?>"><?php esc_html_e( 'Edit', 'storewright' ); ?></a>
					<?php else : ?>
						<form method="post">
							<?php wp_nonce_field( $nonce ); ?>
							<input type="hidden" name="address" value="<?php echo esc_attr( (string) $storewright_id ); ?>">
							<button class="sw-address__link" type="submit" name="storewright_address_action" value="default"><?php esc_html_e( 'Make default', 'storewright' ); ?></button>
							<button class="sw-address__link is-quiet" type="submit" name="storewright_address_action" value="remove"><?php esc_html_e( 'Remove', 'storewright' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>

	<details class="sw-addresses__add"<?php echo $addresses === [] ? ' open' : ''; ?>>
		<summary class="wp-element-button"><?php esc_html_e( 'Add an address', 'storewright' ); ?></summary>
		<form method="post" class="sw-addresses__form" aria-label="<?php esc_attr_e( 'New address', 'storewright' ); ?>">
			<?php wp_nonce_field( $nonce ); ?>
			<input type="hidden" name="shipping[country]" value="IN">
			<p><label for="sw-first-name"><?php esc_html_e( 'First name', 'storewright' ); ?></label><input id="sw-first-name" name="shipping[first_name]" autocomplete="given-name" required></p>
			<p><label for="sw-last-name"><?php esc_html_e( 'Last name', 'storewright' ); ?></label><input id="sw-last-name" name="shipping[last_name]" autocomplete="family-name"></p>
			<p class="is-wide"><label for="sw-address-1"><?php esc_html_e( 'House, street and area', 'storewright' ); ?></label><input id="sw-address-1" name="shipping[address_1]" autocomplete="address-line1" required></p>
			<p class="is-wide"><label for="sw-address-2"><?php esc_html_e( 'Landmark or flat (optional)', 'storewright' ); ?></label><input id="sw-address-2" name="shipping[address_2]" autocomplete="address-line2"></p>
			<p><label for="sw-postcode"><?php esc_html_e( 'PIN code', 'storewright' ); ?></label><input id="sw-postcode" name="shipping[postcode]" inputmode="numeric" maxlength="6" autocomplete="postal-code" required></p>
			<p><label for="sw-city"><?php esc_html_e( 'City', 'storewright' ); ?></label><input id="sw-city" name="shipping[city]" autocomplete="address-level2" required></p>
			<p><label for="sw-state"><?php esc_html_e( 'State', 'storewright' ); ?></label>
				<select id="sw-state" name="shipping[state]" autocomplete="address-level1">
					<?php foreach ( $states as $storewright_code => $storewright_state ) : ?>
						<option value="<?php echo esc_attr( (string) $storewright_code ); ?>"><?php echo esc_html( $storewright_state ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p><label for="sw-phone"><?php esc_html_e( 'Phone', 'storewright' ); ?></label><input id="sw-phone" name="shipping[phone]" type="tel" autocomplete="tel"></p>
			<p class="is-wide"><button class="wp-element-button" type="submit" name="storewright_address_action" value="add"><?php esc_html_e( 'Save address', 'storewright' ); ?></button></p>
		</form>
	</details>

	<section class="sw-addresses__billing">
		<header class="sw-account__section-head">
			<h2><?php esc_html_e( 'Billing address', 'storewright' ); ?></h2>
			<a class="sw-address__link" href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address', 'billing' ) ); ?>"><?php esc_html_e( 'Edit', 'storewright' ); ?></a>
		</header>
		<address class="sw-address__text"><?php echo $billing ? wp_kses_post( $billing ) : esc_html__( 'Not set yet. It is filled in at your first checkout.', 'storewright' ); ?></address>
	</section>
</div>
