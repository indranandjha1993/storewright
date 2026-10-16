import { expect, test } from '@playwright/test';
import { account, logIn, type Account } from './support/accounts';
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
