import { expect, test } from '@playwright/test';
import { account, logIn, type Account } from './support/accounts';
import { mailFor } from './support/mail';
import { wp } from './support/wp';

let staff: Account;
let shopper: Account;

test.beforeAll(() => {
	staff = account('console.e2e');
	shopper = account('shopper.e2e');
	wp('eval', '$order = wc_create_order(); $order->add_product( wc_get_product( wc_get_product_id_by_sku( "SW-MUG" ) ), 1 ); $order->set_address( [ "first_name" => "Console", "last_name" => "Check", "email" => "console.check@example.com", "city" => "Bengaluru", "postcode" => "560001", "country" => "IN", "state" => "KA" ], "billing" ); $order->set_payment_method_title( "Cash on delivery" ); $order->calculate_totals(); $order->set_status( "processing" ); $order->save();');
});

test('staff land on the dashboard and see what needs doing today', async ({ page }) => {
	await logIn(page, staff);

	await expect(page).toHaveURL(/\/wp\/wp-admin\/$/);
	await expect(page).toHaveTitle('Dashboard ‹ Storewright');
	const today = page.locator('#storewright_today');
	await expect(today).toContainText('Sales');
	for (const queue of ['to fulfil', 'awaiting payment', 'on hold']) {
		await expect(today.getByRole('link', { name: new RegExp(`^\\d+ ${queue}$`) })).toBeVisible();
	}
	await expect(today.getByRole('link', { name: /to fulfil/ })).toHaveAttribute('href', /page=wc-orders&status=wc-processing$/);
	await expect(today).toContainText('Best sellers');
	const payments = page.locator('#storewright_payments');
	for (const name of ['Razorpay', 'Stripe', 'Cash on delivery']) {
		await expect(payments).toContainText(name);
	}
	const insights = page.locator('#storewright_insights');
	for (const block of ['Payment mix', 'Customers', 'Where orders go', 'Delivery methods', 'Days of stock']) {
		await expect(insights).toContainText(block);
	}

	await today.getByRole('link', { name: '7 days' }).click();
	await expect(page).toHaveURL(/period=week/);
	await expect(page.locator('#storewright_today .sw-dash__periods .is-active')).toHaveText('7 days');
	await expect(page.locator('#woocommerce_dashboard_status')).toHaveCount(0);
});

test('shoppers are kept out of the admin', async ({ page }) => {
	await logIn(page, shopper);
	await page.goto('/wp/wp-admin/');

	await expect(page).toHaveURL(/\/my-account\//);
});

test('the store menu holds every task once', async ({ page }) => {
	await logIn(page, staff);
	await page.goto('/wp/wp-admin/admin.php?page=wc-orders');
	const store = page.locator('#adminmenu .wp-has-current-submenu .wp-submenu');

	await expect(store.getByRole('link')).toHaveText([/^Orders/, 'Returns', 'Customers', 'Payments', 'Delivery areas', 'Brand', 'Settings', 'Reports']);
	await expect(page.locator('#adminmenu').getByRole('link', { name: 'Home', exact: true })).toHaveCount(0);
	await expect(page.locator('#adminmenu').getByRole('link', { name: 'Payments', exact: true })).toHaveCount(1);

	await page.goto('/wp/wp-admin/admin.php?page=wc-settings&tab=shipping');
	await expect(page).toHaveURL(/page=storewright-areas$/);
});

test('staff ship an order in parts with carrier and tracking', async ({ page }) => {
	const id = wp('eval', '$order = wc_create_order(); $order->add_product( wc_get_product( wc_get_product_id_by_sku( "SW-MUG" ) ), 2 ); $order->add_product( wc_get_product( wc_get_product_id_by_sku( "SW-NOTE" ) ), 1 ); $order->set_address( [ "first_name" => "Ship", "last_name" => "Check", "email" => "ship.check@example.com", "city" => "Bengaluru", "postcode" => "560001", "country" => "IN", "state" => "KA" ], "billing" ); $order->calculate_totals(); $order->set_status( "processing" ); $order->save(); echo $order->get_id();');
	const mug = wp('eval', `foreach ( wc_get_order( ${id} )->get_items() as $item ) { if ( $item->get_name() === "Stoneware Mug" ) { echo $item->get_id(); } }`);
	await logIn(page, staff);
	await page.goto('/wp/wp-admin/admin.php?page=storewright-areas');
	const shipped = await page.evaluate(([orderId, itemId]) => (window as any).wp.apiFetch({
		path: `/wc/v3/orders/${orderId}/fulfillments`,
		method: 'POST',
		data: { status: 'fulfilled', is_fulfilled: true, notify_customer: true, meta_data: [{ key: '_items', value: [{ item_id: Number(itemId), qty: 1 }] }, { key: '_shipment_provider', value: 'delhivery' }, { key: '_tracking_number', value: '1490110012345' }, { key: '_tracking_url', value: 'https://track.example.com/1490110012345' }] },
	}), [id, mug]);
	expect(shipped).toBeTruthy();

	const mail = await mailFor(page, `to:"ship.check@example.com" subject:"order ${id} is on its way"`);
	expect(mail.html).toContain('Your parcel has left us.');
	expect(mail.html).toContain('Delhivery');
	expect(mail.html).toContain('https://track.example.com/1490110012345');
	expect(mail.html).not.toMatch(/Woo!|fulfill/i);

	await page.goto(`/wp/wp-admin/admin.php?page=wc-orders&action=edit&id=${id}`);
	await expect(page.locator('#wpbody-content')).toContainText('1490110012345');

	const received = wp('eval', `echo wc_get_order( ${id} )->get_checkout_order_received_url();`);
	const customer = await page.context().newPage();
	await customer.goto(received);
	const card = customer.getByRole('region', { name: 'Shipments' });
	await expect(card).toContainText('Delhivery');
	await expect(card.getByRole('link', { name: 'Track parcel' })).toHaveAttribute('href', 'https://track.example.com/1490110012345');
	await expect(card).toContainText('Still to ship: 1 x Stoneware Mug, 1 x Dot Grid Notebook');
	await customer.close();
});

test('the payments page shows gateway health and orders still waiting for money', async ({ page }) => {
	const id = wp('eval', '$order = wc_create_order(); $order->add_product( wc_get_product( wc_get_product_id_by_sku( "SW-NOTE" ) ), 1 ); $order->set_address( [ "first_name" => "Pay", "last_name" => "Check", "email" => "pay.check@example.com" ], "billing" ); $order->set_payment_method( "razorpay" ); $order->set_payment_method_title( "Razorpay" ); $order->calculate_totals(); $order->set_date_created( time() - 20 * MINUTE_IN_SECONDS ); $order->save(); echo $order->get_order_number();');
	await logIn(page, staff);
	await page.goto('/wp/wp-admin/admin.php?page=storewright-payments');

	await expect(page.getByRole('heading', { level: 1, name: 'Payments' })).toBeVisible();
	for (const name of ['Razorpay', 'Stripe', 'Cash on delivery']) {
		await expect(page.getByRole('region', { name, exact: true })).toBeVisible();
	}
	await expect(page.getByRole('region', { name: 'Stripe', exact: true })).toContainText('/wp-json/storewright/v1/webhooks/stripe');

	const waiting = page.getByRole('region', { name: 'Waiting for payment' });
	const row = waiting.getByRole('row').filter({ hasText: `#${id}` });
	await expect(row).toContainText('Pay Check');
	await row.getByRole('link', { name: 'Check with Razorpay' }).click();
	await expect(page.locator('.notice')).toContainText(`Order #${id}:`);

	await expect(page.getByRole('region', { name: 'Webhook log' })).toBeVisible();
});

test('gst details are kept on the taxes and charges tab', async ({ page }) => {
	await logIn(page, staff);
	await page.goto('/wp/wp-admin/admin.php?page=wc-settings&tab=charges');
	await expect(page.locator('.nav-tab-active')).toHaveText('Taxes and charges');
	await expect(page.getByLabel('Enable taxes')).toHaveCount(0);

	await page.getByLabel('GSTIN').fill('29ABCDE1234F1Z5');
	await page.getByRole('button', { name: 'Save changes' }).click();
	await expect(page.locator('#message, .notice-error')).toContainText('That GSTIN does not look right.');

	await page.getByLabel('GSTIN').fill('29ABCDE1234F1ZW');
	await page.getByLabel('Invoice prefix').fill('acme');
	await page.getByRole('button', { name: 'Save changes' }).click();
	await expect(page.locator('#message')).toContainText('Your settings have been saved.');
	expect(wp('option', 'get', 'storewright_settings', '--format=json')).toContain('"invoice_prefix":"ACME"');
	expect(wp('option', 'get', 'storewright_settings', '--format=json')).toContain('"store_gstin":"29ABCDE1234F1ZW"');
	wp('eval', '$s = get_option( "storewright_settings" ); $s["invoice_prefix"] = "INV"; $s["store_gstin"] = ""; update_option( "storewright_settings", $s );');
});

test('staff see the job runner and can run a failed job again or dismiss it', async ({ page }) => {
	// The cron container runs every minute and a job without a callback fails again, so each run uses its own job numbers and accepts any outcome of the re-run.
	const tag = Date.now() % 1_000_000;
	const schedule = (order: number) => wp('eval', `$id = as_schedule_single_action( time() - 300, "storewright_e2e_job", [ "order" => ${order} ], "storewright" ); ActionScheduler_Store::instance()->mark_failure( $id ); ActionScheduler_Logger::instance()->log( $id, "action failed: the courier did not answer" ); echo $id;`);
	const status = (id: string) => wp('eval', `echo ActionScheduler_Store::instance()->get_status( ${id} );`);
	const retry = schedule(tag + 1);
	const dismiss = schedule(tag + 2);
	await logIn(page, staff);
	const jobs = page.locator('#storewright_jobs');

	await expect(jobs).toContainText(/Last run|has not run yet/);
	await expect(jobs).toContainText('waiting to run');
	const retryRow = jobs.locator('li').filter({ hasText: `"order":${tag + 1}` });
	await expect(retryRow).toContainText('storewright_e2e_job');
	await expect(retryRow).toContainText('the courier did not answer');

	await retryRow.getByRole('link', { name: 'Run again' }).click();
	await expect(page.locator('.notice')).toContainText('queued to run again');
	expect(status(retry)).toBe('canceled');
	expect(Number(wp('eval', `echo count( as_get_scheduled_actions( [ "hook" => "storewright_e2e_job", "args" => [ "order" => ${tag + 1} ], "status" => [ "pending", "in-progress", "complete", "failed" ] ], "ids" ) );`))).toBeGreaterThan(0);

	await jobs.locator('li').filter({ hasText: `"order":${tag + 2}` }).getByRole('link', { name: 'Dismiss' }).click();
	await expect(page.locator('.notice')).toContainText('dismissed');
	expect(status(dismiss)).toBe('canceled');
	wp('eval', '$store = ActionScheduler_Store::instance(); foreach ( $store->query_actions( [ "hook" => "storewright_e2e_job", "per_page" => 200, "status" => [ "pending", "in-progress", "complete", "failed", "canceled" ] ] ) as $id ) { $store->delete_action( (string) $id ); }');
});

test('settings only show fields that take effect', async ({ page }) => {
	await logIn(page, staff);

	await page.goto('/wp/wp-admin/admin.php?page=wc-settings&tab=email');
	await expect(page.locator('#woocommerce_email_base_color, #woocommerce_email_font_family, #woocommerce_email_header_image')).toHaveCount(0);
	await expect(page.locator('#wpbody-content')).toContainText('The logo, colours and typeface come from Store, Brand.');
	await expect(page.locator('#woocommerce_email_from_address')).toBeVisible();

	await page.goto('/wp/wp-admin/admin.php?page=wc-settings&tab=checkout&section=razorpay');
	await expect(page.locator('#woocommerce_razorpay_key_id, #woocommerce_razorpay_key_secret, #woocommerce_razorpay_webhook_secret, #woocommerce_razorpay_enabled')).toHaveCount(0);
	await expect(page.locator('#wpbody-content')).toContainText('come from the server settings and are not stored here.');

	await page.goto('/wp/wp-admin/admin.php?page=wc-settings');
	await expect(page.getByLabel('Enable taxes')).toHaveCount(0);

	await page.goto('/wp/wp-admin/admin.php?page=wc-settings&tab=charges');
	await page.getByLabel('Cash on delivery PIN codes').fill('560*\n411001-411099');
	await page.getByRole('button', { name: 'Save changes' }).click();
	await expect(page.locator('#message')).toContainText('Your settings have been saved.');
	expect(wp('option', 'get', 'storewright_settings', '--format=json')).toContain('"cod_postcodes":["560*","411001-411099"]');
	await expect(page.getByLabel('Cash on delivery PIN codes')).toHaveValue('560*\n411001-411099');
	wp('eval', '$s = get_option( "storewright_settings" ); $s["cod_postcodes"] = []; update_option( "storewright_settings", $s );');
});
