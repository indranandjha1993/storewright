<?php

use Storewright\Platform\Brand\Fonts;

defined( 'ABSPATH' ) || exit;

$storewright_tax   = $invoice['registered'];
$storewright_money = static fn( float $amount ): string => wp_strip_all_tags( wc_price( $amount ) );
$storewright_date  = $invoice['date'] !== '' ? wc_format_datetime( wc_string_to_datetime( $invoice['date'] ) ) : '';
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $invoice['number'] . ' - ' . $invoice['seller']['name'] ); ?></title>
	<style>
		<?php echo Fonts::fontFaceCss( [ ...$heading['faces'], ...$body['faces'] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		:root {
			--accent: <?php echo esc_html( $palette['accent'] ); ?>;
			--text: <?php echo esc_html( $palette['contrast'] ); ?>;
			--muted: <?php echo esc_html( $palette['muted'] ); ?>;
			--line: <?php echo esc_html( $palette['line'] ); ?>;
			--soft: <?php echo esc_html( $palette['accentSoft'] ); ?>;
		}
		* { box-sizing: border-box; }
		body { margin: 0; background: #f4f4f2; color: var(--text); font: 14px/1.5 <?php echo $body['family']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>; }
		.sheet { max-width: 820px; margin: 24px auto; padding: 48px; background: #fff; border-radius: 8px; }
		.head { display: flex; justify-content: space-between; gap: 32px; padding-bottom: 24px; border-bottom: 2px solid var(--text); }
		.brand { font: 700 26px/1.1 <?php echo $heading['family']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>; margin: 0 0 8px; }
		.brand img { max-height: 56px; width: auto; }
		.kind { margin: 0 0 12px; color: var(--accent); font-size: 12px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; text-align: right; }
		dl { display: grid; grid-template-columns: auto auto; gap: 2px 16px; margin: 0; }
		dt { color: var(--muted); }
		dd { margin: 0; font-weight: 600; text-align: right; }
		p { margin: 0; }
		.muted { color: var(--muted); }
		.parties { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; padding: 24px 0; border-bottom: 1px solid var(--line); }
		h2 { margin: 0 0 6px; color: var(--muted); font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
		.table { margin: 24px 0; overflow-x: auto; }
		.lines { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
		.lines th { padding: 8px 6px; border-bottom: 1px solid var(--text); color: var(--muted); font-size: 11px; font-weight: 700; letter-spacing: .06em; text-align: right; text-transform: uppercase; }
		.lines td { padding: 10px 6px; border-bottom: 1px solid var(--line); text-align: right; vertical-align: top; }
		.lines th:first-child, .lines td:first-child, .lines th:nth-child(2), .lines td:nth-child(2) { text-align: left; }
		.totals { display: grid; justify-content: end; }
		.totals dl { min-width: 280px; grid-template-columns: 1fr auto; gap: 6px 0; }
		.totals dd { padding-left: 32px; }
		.totals .grand { margin-top: 4px; padding-top: 10px; border-top: 2px solid var(--text); color: var(--text); font-size: 18px; font-weight: 700; }
		.words { margin-top: 16px; padding: 12px 16px; border-radius: 6px; background: var(--soft); }
		.foot { display: flex; justify-content: space-between; gap: 24px; margin-top: 32px; padding-top: 16px; border-top: 1px solid var(--line); color: var(--muted); font-size: 12px; }
		.print { position: fixed; right: 24px; bottom: 24px; padding: 12px 20px; border: 0; border-radius: 999px; background: var(--accent); color: #fff; font: 600 14px/1 inherit; cursor: pointer; }
		@media (max-width: 640px) { .sheet { margin: 0; padding: 20px 20px 96px; border-radius: 0; } .head, .parties { grid-template-columns: 1fr; flex-direction: column; } .kind { text-align: left; } .foot { flex-direction: column; } }
		@media print { body { background: #fff; } .sheet { margin: 0; padding: 0; max-width: none; } .print { display: none; } @page { margin: 16mm; } }
	</style>
</head>
<body>
<main class="sheet">
	<header class="head">
		<div>
			<p class="brand">
				<?php if ( $logo ) : ?>
					<?php echo wp_get_attachment_image( $logo, 'medium', false, [ 'alt' => esc_attr( $invoice['seller']['name'] ) ] ); ?>
				<?php else : ?>
					<?php echo esc_html( $invoice['seller']['name'] ); ?>
				<?php endif; ?>
			</p>
			<?php foreach ( $invoice['seller']['address'] as $storewright_line ) : ?>
				<p class="muted"><?php echo esc_html( $storewright_line ); ?></p>
			<?php endforeach; ?>
			<?php if ( $invoice['seller']['gstin'] !== '' ) : ?>
				<p><?php esc_html_e( 'GSTIN', 'storewright' ); ?> <strong><?php echo esc_html( $invoice['seller']['gstin'] ); ?></strong></p>
			<?php endif; ?>
			<p class="muted"><?php echo esc_html( implode( ' / ', array_filter( [ $invoice['seller']['phone'], $invoice['seller']['email'] ] ) ) ); ?></p>
		</div>
		<div>
			<p class="kind"><?php echo esc_html( $storewright_tax ? __( 'Tax invoice', 'storewright' ) : __( 'Invoice', 'storewright' ) ); ?></p>
			<dl>
				<dt><?php esc_html_e( 'Invoice number', 'storewright' ); ?></dt>
				<dd><?php echo esc_html( $invoice['number'] ); ?></dd>
				<dt><?php esc_html_e( 'Invoice date', 'storewright' ); ?></dt>
				<dd><?php echo esc_html( $storewright_date ); ?></dd>
				<dt><?php esc_html_e( 'Order', 'storewright' ); ?></dt>
				<dd>#<?php echo esc_html( $invoice['order'] ); ?></dd>
				<?php if ( $storewright_tax ) : ?>
					<dt><?php esc_html_e( 'Place of supply', 'storewright' ); ?></dt>
					<dd><?php echo esc_html( $invoice['supply'] ); ?></dd>
				<?php endif; ?>
			</dl>
		</div>
	</header>

	<section class="parties">
		<div>
			<h2><?php esc_html_e( 'Billed to', 'storewright' ); ?></h2>
			<?php if ( $invoice['buyer']['business'] !== '' ) : ?>
				<p><strong><?php echo esc_html( $invoice['buyer']['business'] ); ?></strong></p>
			<?php endif; ?>
			<p><?php echo wp_kses_post( $invoice['buyer']['billing'] ?: $invoice['buyer']['name'] ); ?></p>
			<?php if ( $invoice['buyer']['gstin'] !== '' ) : ?>
				<p><?php esc_html_e( 'GSTIN', 'storewright' ); ?> <strong><?php echo esc_html( $invoice['buyer']['gstin'] ); ?></strong></p>
			<?php endif; ?>
			<p class="muted"><?php echo esc_html( implode( ' / ', array_filter( [ $invoice['buyer']['phone'], $invoice['buyer']['email'] ] ) ) ); ?></p>
		</div>
		<?php if ( $invoice['buyer']['shipping'] !== '' ) : ?>
			<div>
				<h2><?php esc_html_e( 'Shipped to', 'storewright' ); ?></h2>
				<p><?php echo wp_kses_post( $invoice['buyer']['shipping'] ); ?></p>
			</div>
		<?php endif; ?>
	</section>

	<div class="table">
	<table class="lines">
		<thead>
			<tr>
				<th>#</th>
				<th><?php esc_html_e( 'Item', 'storewright' ); ?></th>
				<?php if ( $storewright_tax ) : ?>
					<th><?php esc_html_e( 'HSN/SAC', 'storewright' ); ?></th>
				<?php endif; ?>
				<th><?php esc_html_e( 'Qty', 'storewright' ); ?></th>
				<?php if ( $storewright_tax ) : ?>
					<th><?php esc_html_e( 'Taxable value', 'storewright' ); ?></th>
					<?php if ( $invoice['sameState'] ) : ?>
						<th><?php esc_html_e( 'CGST', 'storewright' ); ?></th>
						<th><?php esc_html_e( 'SGST', 'storewright' ); ?></th>
					<?php else : ?>
						<th><?php esc_html_e( 'IGST', 'storewright' ); ?></th>
					<?php endif; ?>
				<?php endif; ?>
				<th><?php esc_html_e( 'Amount', 'storewright' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $invoice['lines'] as $storewright_index => $storewright_row ) : ?>
				<tr>
					<td><?php echo esc_html( (string) ( $storewright_index + 1 ) ); ?></td>
					<td><?php echo esc_html( $storewright_row['name'] ); ?></td>
					<?php if ( $storewright_tax ) : ?>
						<td><?php echo esc_html( $storewright_row['hsn'] ); ?></td>
					<?php endif; ?>
					<td><?php echo esc_html( (string) $storewright_row['quantity'] ); ?></td>
					<?php if ( $storewright_tax ) : ?>
						<td><?php echo esc_html( $storewright_money( $storewright_row['taxable'] ) ); ?></td>
						<?php if ( $invoice['sameState'] ) : ?>
							<td><?php echo esc_html( $storewright_money( $storewright_row['cgst'] ) ); ?><br><span class="muted"><?php echo esc_html( ( $storewright_row['rate'] / 2 ) . '%' ); ?></span></td>
							<td><?php echo esc_html( $storewright_money( $storewright_row['sgst'] ) ); ?><br><span class="muted"><?php echo esc_html( ( $storewright_row['rate'] / 2 ) . '%' ); ?></span></td>
						<?php else : ?>
							<td><?php echo esc_html( $storewright_money( $storewright_row['igst'] ) ); ?><br><span class="muted"><?php echo esc_html( $storewright_row['rate'] . '%' ); ?></span></td>
						<?php endif; ?>
					<?php endif; ?>
					<td><?php echo esc_html( $storewright_money( $storewright_row['gross'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>

	<section class="totals">
		<dl>
			<?php if ( $storewright_tax ) : ?>
				<dt><?php esc_html_e( 'Taxable value', 'storewright' ); ?></dt>
				<dd><?php echo esc_html( $storewright_money( $invoice['totals']['taxable'] ) ); ?></dd>
				<?php if ( $invoice['sameState'] ) : ?>
					<dt><?php esc_html_e( 'CGST', 'storewright' ); ?></dt>
					<dd><?php echo esc_html( $storewright_money( $invoice['totals']['cgst'] ) ); ?></dd>
					<dt><?php esc_html_e( 'SGST', 'storewright' ); ?></dt>
					<dd><?php echo esc_html( $storewright_money( $invoice['totals']['sgst'] ) ); ?></dd>
				<?php else : ?>
					<dt><?php esc_html_e( 'IGST', 'storewright' ); ?></dt>
					<dd><?php echo esc_html( $storewright_money( $invoice['totals']['igst'] ) ); ?></dd>
				<?php endif; ?>
			<?php endif; ?>
			<dt class="grand"><?php esc_html_e( 'Total', 'storewright' ); ?></dt>
			<dd class="grand"><?php echo esc_html( $storewright_money( $invoice['totals']['total'] ) ); ?></dd>
		</dl>
	</section>
	<p class="words"><?php echo esc_html( $invoice['totals']['words'] ); ?></p>

	<footer class="foot">
		<p>
			<?php
			if ( $invoice['payment'] !== '' && $invoice['paid'] ) {
				/* translators: %s: payment method */
				echo esc_html( sprintf( __( 'Paid by %s.', 'storewright' ), $invoice['payment'] ) );
			} elseif ( $invoice['payment'] !== '' ) {
				/* translators: %s: payment method */
				echo esc_html( sprintf( __( 'To be paid by %s.', 'storewright' ), $invoice['payment'] ) );
			}
			if ( ! $storewright_tax ) {
				echo ' ' . esc_html__( 'The seller is not registered under GST.', 'storewright' );
			}
			?>
		</p>
		<p><?php esc_html_e( 'Computer generated invoice, no signature needed.', 'storewright' ); ?></p>
	</footer>
</main>
<button type="button" class="print" onclick="window.print()"><?php esc_html_e( 'Print or save as PDF', 'storewright' ); ?></button>
</body>
</html>
