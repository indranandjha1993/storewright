import { expect, test, type Page } from '@playwright/test';
import { account, logIn, type Account } from './support/accounts';
import { mailFor } from './support/mail';
import { wp } from './support/wp';

let shopper: Account;

test.beforeEach(({}, testInfo) => {
	test.skip(testInfo.project.name !== 'desktop', 'the account is checked at desktop width');
	shopper = account('account.e2e', 'customer', 'Meera Rao');
	wp('eval', '$id = get_user_by( "login", "account.e2e" )->ID; delete_user_meta( $id, "storewright_addresses" ); delete_user_meta( $id, "storewright_saved" ); $c = new WC_Customer( $id ); $c->set_first_name( "Meera" ); $c->set_shipping_first_name( "Meera" ); $c->set_shipping_last_name( "Rao" ); $c->set_shipping_address_1( "4 Lavelle Road" ); $c->set_shipping_address_2( "" ); $c->set_shipping_city( "Bengaluru" ); $c->set_shipping_state( "KA" ); $c->set_shipping_postcode( "560001" ); $c->set_shipping_country( "IN" ); $c->save(); WC()->cart?->empty_cart();');
});

async function addAddress(page: Page, street: string, pin: string, city: string, state: string) {
	await page.goto('/my-account/addresses/');
	await page.getByText('Add an address').click();
	const form = page.getByRole('form', { name: 'New address' });
	await form.getByLabel('First name').fill('Meera');
	await form.getByLabel('House, street and area').fill(street);
	await form.getByLabel('PIN code').fill(pin);
	await form.getByLabel('City').fill(city);
	await form.getByLabel('State').selectOption(state);
	await form.getByRole('button', { name: 'Save address' }).click();
}

test('the overview greets the shopper and links their account', async ({ page }) => {
	await logIn(page, shopper);
	await page.goto('/my-account/');

	await expect(page.getByRole('heading', { name: 'Hello, Meera' })).toBeVisible();
	const menu = page.getByRole('navigation', { name: /account/i });
	for (const item of ['Overview', 'Orders', 'Saved', 'Downloads', 'Addresses', 'Account details', 'Sign out']) {
		await expect(menu.getByRole('link', { name: item })).toBeVisible();
	}
	await expect(page.getByRole('link', { name: /560001\s*Delivery address/ })).toHaveAttribute('href', /\/my-account\/addresses\/$/);
});

test('saved addresses say whether we deliver and can become the default', async ({ page }) => {
	await logIn(page, shopper);
	await addAddress(page, '12 FC Road', '411004', 'Pune', 'MH');

	await expect(page.getByText('Address saved.')).toBeVisible();
	const cards = page.locator('.sw-address');
	await expect(cards).toHaveCount(2);
	const pune = cards.filter({ hasText: '12 FC Road' });
	await expect(pune).toContainText('We deliver here.');

	await pune.getByRole('button', { name: 'Make default' }).click();
	await expect(page.locator('.sw-address.is-default')).toContainText('12 FC Road');
	await expect(cards.filter({ hasText: '4 Lavelle Road' }).getByRole('button', { name: 'Remove' })).toBeVisible();

	await cards.filter({ hasText: '4 Lavelle Road' }).getByRole('button', { name: 'Remove' }).click();
	await expect(page.getByText('Address removed.')).toBeVisible();
	await expect(cards).toHaveCount(1);
});

test('an address with a wrong pin code is refused', async ({ page }) => {
	await logIn(page, shopper);
	await addAddress(page, '9 Unknown Lane', '56001', 'Bengaluru', 'KA');

	await expect(page.getByText('Enter the 6 digit PIN code of the address.')).toBeVisible();
	await expect(page.locator('.sw-address')).toHaveCount(1);
});

test('checkout offers the saved addresses', async ({ page }) => {
	await logIn(page, shopper);
	await addAddress(page, '12 FC Road', '411004', 'Pune', 'MH');

	await page.goto('/product/stoneware-mug/');
	const added = page.waitForResponse((r) => r.request().method() === 'POST' && /\/wc\/store\/v1\/(cart\/add-item|batch)/.test(r.url()));
	await page.getByRole('button', { name: 'Add to bag: “Stoneware Mug”' }).click();
	expect((await added).ok()).toBe(true);
	await page.goto('/checkout/');

	const chooser = page.getByRole('group', { name: 'Deliver to a saved address' });
	await expect(chooser.getByRole('button')).toHaveCount(2);
	await chooser.getByRole('button', { name: /12 FC Road/ }).click();
	const shipping = page.getByRole('group', { name: 'Shipping address' });
	await expect(shipping.getByLabel(/PIN Code/)).toHaveValue('411004');
	await expect(shipping.getByLabel('City')).toHaveValue('Pune');
	await expect(chooser.getByRole('button', { name: /12 FC Road/ })).toHaveAttribute('aria-pressed', 'true');
});

test('a product saved for later waits in the account until it is removed', async ({ page }) => {
	await logIn(page, shopper);
	await page.goto('/product/stoneware-mug/');
	const save = page.getByRole('button', { name: 'Save for later' });
	await save.click();
	await expect(page.getByRole('button', { name: 'Saved' })).toHaveAttribute('aria-pressed', 'true');

	await page.reload();
	await expect(page.getByRole('button', { name: 'Saved' })).toBeVisible();

	await page.goto('/my-account/saved/');
	const item = page.locator('.sw-saved__item').filter({ hasText: 'Stoneware Mug' });
	await expect(item).toBeVisible();
	await expect(item.getByRole('link', { name: 'Add to bag' })).toBeVisible();

	await item.getByRole('button', { name: 'Remove' }).click();
	await expect(page.getByText('Removed from your saved products.')).toBeVisible();
	await expect(page.locator('.sw-saved__item')).toHaveCount(0);
	await expect(page.getByText('Nothing saved yet.')).toBeVisible();
});

test('a guest is asked to sign in before saving', async ({ page }) => {
	await page.goto('/product/stoneware-mug/');
	await expect(page.getByRole('link', { name: 'Sign in to save' })).toHaveAttribute('href', /\/my-account\/$/);
});

test('a delivered order can be returned and refunded', async ({ page, browser }) => {
	const id = wp('eval', '$user = get_user_by( "login", "account.e2e" ); $order = wc_create_order( [ "customer_id" => $user->ID ] ); $order->add_product( wc_get_product( wc_get_product_id_by_sku( "SW-MUG" ) ), 2 ); $order->set_address( [ "first_name" => "Meera", "last_name" => "Rao", "email" => $user->user_email, "state" => "KA", "country" => "IN" ], "billing" ); $order->set_payment_method( "cod" ); $order->set_payment_method_title( "Cash on delivery" ); $order->calculate_totals(); $order->save(); $order->update_status( "completed" ); echo $order->get_id();');
	await logIn(page, shopper);
	await page.goto(`/my-account/view-order/${id}/`);

	const returns = page.getByRole('region', { name: 'Returns' });
	await returns.getByText('Return items').click();
	const form = returns.getByRole('form', { name: 'Return items' });
	await form.getByLabel('How many of Stoneware Mug').selectOption('1');
	await form.getByLabel('Why').selectOption({ label: 'Arrived damaged' });
	await form.getByRole('button', { name: 'Request the return' }).click();
	await expect(page.getByText('Return requested. We will email you about the pickup.')).toBeVisible();
	await expect(page.locator('.sw-return')).toContainText('Requested');
	await expect(page.locator('.sw-return')).toContainText('1 x Stoneware Mug');
	const ack = await mailFor(page, `to:"${shopper.email}" subject:"We have your return request"`);
	expect(ack.html).toContain('1 x Stoneware Mug');

	const staffContext = await browser.newContext();
	const staffPage = await staffContext.newPage();
	await logIn(staffPage, account('returns.e2e', 'shop_manager', 'Returns Staff'));
	await staffPage.goto('/wp/wp-admin/admin.php?page=storewright&p=%2Freturns');
	const row = staffPage.locator('.sw-return-row').filter({ hasText: `#${id}` });
	await row.getByRole('button', { name: 'Approve and arrange pickup' }).click();
	await expect(row).toContainText('Pickup arranged');
	await row.getByRole('button', { name: 'Mark as received' }).click();
	await expect(row).toContainText('Back with us');
	await row.getByRole('button', { name: 'Refund' }).click();
	await expect(row).toContainText('Refunded');
	await staffContext.close();

	expect(wp('eval', `echo wc_get_order( ${id} )->get_total_refunded();`)).toBe('449');
	await page.reload();
	await expect(page.locator('.sw-return')).toContainText('Refunded');
});

