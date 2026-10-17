import { expect, test, type Page } from '@playwright/test';
import { account, logIn, type Account } from './support/accounts';
import { wp } from './support/wp';

let shopper: Account;

test.beforeEach(({}, testInfo) => {
	test.skip(testInfo.project.name !== 'desktop', 'the account is checked at desktop width');
	shopper = account('account.e2e', 'customer', 'Meera Rao');
	wp('eval', '$id = get_user_by( "login", "account.e2e" )->ID; delete_user_meta( $id, "storewright_addresses" ); $c = new WC_Customer( $id ); $c->set_first_name( "Meera" ); $c->set_shipping_first_name( "Meera" ); $c->set_shipping_last_name( "Rao" ); $c->set_shipping_address_1( "4 Lavelle Road" ); $c->set_shipping_address_2( "" ); $c->set_shipping_city( "Bengaluru" ); $c->set_shipping_state( "KA" ); $c->set_shipping_postcode( "560001" ); $c->set_shipping_country( "IN" ); $c->save(); WC()->cart?->empty_cart();');
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
	for (const item of ['Overview', 'Orders', 'Downloads', 'Addresses', 'Account details', 'Sign out']) {
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
