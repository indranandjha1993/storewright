import { expect, test } from '@playwright/test';
import { account, logIn, type Account } from './support/accounts';
import { mailFor } from './support/mail';
import { wp } from './support/wp';

let staff: Account;
let shopper: Account;

test.beforeAll(() => {
	staff = account('console.e2e', 'shop_manager', 'Console Staff');
	shopper = account('shopper.e2e', 'customer', 'Shopper');
	wp('eval', '$order = wc_create_order(); $order->add_product( wc_get_product( wc_get_product_id_by_sku( "SW-MUG" ) ), 1 ); $order->set_address( [ "first_name" => "Console", "last_name" => "Check", "email" => "console.check@example.com", "city" => "Bengaluru", "postcode" => "560001", "country" => "IN", "state" => "KA" ], "billing" ); $order->set_payment_method_title( "Cash on delivery" ); $order->calculate_totals(); $order->set_status( "processing" ); $order->save();');
});

test.beforeEach(({}, testInfo) => {
	test.skip(testInfo.project.name !== 'desktop', 'the console is checked at desktop width');
});

test('staff land on the console and see what needs doing today', async ({ page }) => {
	await logIn(page, staff);

	await expect(page).toHaveURL(/admin\.php\?page=storewright/);
	await expect(page).toHaveTitle('Today ‹ Storewright');
	await expect(page.getByRole('heading', { name: 'Today' })).toBeVisible();
	for (const queue of ['To fulfil', 'Awaiting payment', 'On hold']) {
		await expect(page.getByRole('link', { name: new RegExp(`^\\d+ ${queue}$`) })).toBeVisible();
	}
	await expect(page.getByRole('link', { name: /To fulfil/ })).toHaveAttribute('href', /page=storewright&p=%2Forders&status=processing$/);
});

test('the dashboard shows sales, recent orders, payments and stock', async ({ page }) => {
	await logIn(page, staff);

	await expect(page.getByText('Sales', { exact: true })).toBeVisible();
	const orders = page.getByRole('region', { name: 'Recent orders' });
	await expect(orders.getByRole('columnheader', { name: 'Customer' }).or(orders.getByText('No orders yet. The first one shows up here.'))).toBeVisible();
	await expect(orders.getByRole('link', { name: 'All orders' })).toHaveAttribute('href', /page=storewright&p=%2Forders$/);
	const payments = page.getByRole('region', { name: 'Payments' });
	await expect(payments).toContainText('Razorpay');
	await expect(payments).toContainText('Stripe');
	await expect(payments).toContainText('Cash on delivery');
	await expect(page.getByRole('region', { name: 'Stock' })).toContainText(/running low/);

	await page.getByRole('button', { name: '7 days' }).click();
	await expect(page.getByRole('img', { name: 'Sales by day' })).toBeVisible();
	await expect(page.locator('.sw-dash__bar')).toHaveCount(7);
});

test('the advanced link opens the full admin and the admin leads back', async ({ page }) => {
	await logIn(page, staff);

	await page.getByText('Advanced', { exact: true }).click();
	await expect(page).toHaveURL(/\/wp\/wp-admin\/$/);
	await page.locator('#adminmenu').getByRole('link', { name: 'Console', exact: true }).click();
	await expect(page.getByRole('heading', { name: 'Today' })).toBeVisible();
});

test('shoppers cannot open the console', async ({ page }) => {
	await logIn(page, shopper);
	await page.goto('/wp/wp-admin/admin.php?page=storewright');

	await expect(page.getByText('Sorry, you are not allowed to access this page.')).toBeVisible();
	await expect(page.getByRole('heading', { name: 'Today' })).toHaveCount(0);
});

test('staff work orders from the console', async ({ page }) => {
	await logIn(page, staff);
	await page.getByRole('link', { name: 'Orders' }).click();

	await expect(page.getByRole('heading', { name: 'Orders' })).toBeVisible();
	await page.getByRole('button', { name: /^To fulfil/ }).click();
	const first = page.locator('.sw-orders__table tbody tr').first();
	await expect(first).toContainText('To fulfil');
	await first.locator('.sw-orders__open').click();
	const panel = page.getByRole('complementary', { name: /^Order #/ });
	await expect(panel.getByRole('heading', { name: 'Items' })).toBeVisible();
	await panel.getByPlaceholder('Add a note').fill('Packed in the blue box.');
	await panel.getByRole('button', { name: 'Add note' }).click();
	await expect(panel.locator('.sw-order__notes')).toContainText('Packed in the blue box.');
});

test('staff ship an order in parts with carrier and tracking', async ({ page }) => {
	const id = wp('eval', '$order = wc_create_order(); $order->add_product( wc_get_product( wc_get_product_id_by_sku( "SW-MUG" ) ), 2 ); $order->add_product( wc_get_product( wc_get_product_id_by_sku( "SW-NOTE" ) ), 1 ); $order->set_address( [ "first_name" => "Ship", "last_name" => "Check", "email" => "ship.check@example.com", "city" => "Bengaluru", "postcode" => "560001", "country" => "IN", "state" => "KA" ], "billing" ); $order->calculate_totals(); $order->set_status( "processing" ); $order->save(); echo $order->get_id();');
	await logIn(page, staff);
	await page.goto(`/wp/wp-admin/admin.php?page=storewright&p=%2Forders&order=${id}`);
	const panel = page.getByRole('complementary', { name: /^Order #/ });
	const shipments = panel.getByRole('region', { name: 'Shipments' });
	await expect(shipments).toContainText('Nothing has been sent yet.');

	await shipments.getByRole('button', { name: 'Ship items' }).click();
	const form = shipments.getByRole('form', { name: 'Ship items' });
	await form.getByLabel('Quantity of Stoneware Mug').fill('1');
	await form.getByLabel('Quantity of Dot Grid Notebook').fill('0');
	await form.getByLabel('Carrier').selectOption({ label: 'Delhivery' });
	await form.getByLabel('Tracking number').fill('1490110012345');
	await form.getByLabel('Tracking link').fill('https://track.example.com/1490110012345');
	await form.getByRole('button', { name: 'Mark as shipped' }).click();

	await expect(shipments).toContainText('Part of the order has been sent.');
	const first = shipments.locator('.sw-shipment').first();
	await expect(first).toContainText('Delhivery');
	await expect(first.getByRole('link', { name: '1490110012345' })).toHaveAttribute('href', 'https://track.example.com/1490110012345');
	await expect(first).toContainText('1 × Stoneware Mug');

	const shipped = await mailFor(page, `to:"ship.check@example.com" subject:"order ${id} is on its way"`);
	expect(shipped.html).toContain('Your parcel has left us.');
	expect(shipped.html).toContain('Delhivery');
	expect(shipped.html).toContain('https://track.example.com/1490110012345');
	expect(shipped.html).not.toMatch(/Woo!|fulfill/i);

	const received = wp('eval', `echo wc_get_order( ${id} )->get_checkout_order_received_url();`);
	const shopper = await page.context().newPage();
	await shopper.goto(received);
	const card = shopper.getByRole('region', { name: 'Shipments' });
	await expect(card).toContainText('Delhivery');
	await expect(card.getByRole('link', { name: 'Track parcel' })).toHaveAttribute('href', 'https://track.example.com/1490110012345');
	await expect(card).toContainText('Still to ship: 1 x Stoneware Mug, 1 x Dot Grid Notebook');
	await shopper.close();

	await shipments.getByRole('button', { name: 'Ship items' }).click();
	await expect(form.getByLabel('Quantity of Stoneware Mug')).toHaveValue('1');
	await form.getByLabel('Carrier').selectOption({ label: 'Our own delivery' });
	await form.getByRole('checkbox', { name: 'Email the customer that it is on its way' }).uncheck();
	await form.getByRole('button', { name: 'Mark as shipped' }).click();
	await expect(shipments).toContainText('Everything has been sent.');
	await expect(shipments.getByRole('button', { name: 'Ship items' })).toHaveCount(0);

	await shipments.locator('.sw-shipment').nth(1).getByRole('button', { name: 'Remove' }).click();
	await expect(shipments).toContainText('Part of the order has been sent.');
});

test('staff change a price without leaving the console', async ({ page }) => {
	await logIn(page, staff);
	await page.getByRole('link', { name: 'Products' }).click();

	const row = page.locator('.sw-products__table tbody tr', { hasText: 'Dot Grid Notebook' });
	await row.getByRole('button', { name: 'Edit' }).click();
	const editor = page.getByRole('form', { name: 'Edit Dot Grid Notebook' });
	await editor.getByLabel('Price', { exact: true }).fill('359');
	await editor.getByRole('button', { name: 'Save' }).click();
	await expect(row).toContainText('₹359.00');
	await row.getByRole('button', { name: 'Edit' }).click();
	await editor.getByLabel('Price', { exact: true }).fill('349');
	await editor.getByRole('button', { name: 'Save' }).click();
	await expect(row).toContainText('₹349.00');
});

test('customers and content screens list what staff expect', async ({ page }) => {
	await logIn(page, staff);
	await page.getByRole('link', { name: 'Customers' }).click();
	await expect(page.locator('.sw-customers__table')).toContainText('Asha Menon');
	await expect(page.locator('.sw-customers__table')).not.toContainText('console.e2e@example.com');

	await page.getByRole('link', { name: 'Pages and journal' }).click();
	const pages = page.getByRole('region', { name: 'Pages' });
	for (const title of ['About', 'Contact', 'Shipping and returns', 'Terms of sale', 'Privacy Policy']) {
		await expect(pages).toContainText(title);
	}
	await expect(pages).not.toContainText('Checkout');
	await expect(page.getByRole('region', { name: 'Journal' })).toContainText('Caring for stoneware');
});
