import { expect, test } from '@playwright/test';
import { account, logIn, type Account } from './support/accounts';

let staff: Account;
let shopper: Account;

test.beforeAll(() => {
	staff = account('console.e2e', 'shop_manager', 'Console Staff');
	shopper = account('shopper.e2e', 'customer', 'Shopper');
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
	await expect(page.getByRole('link', { name: /To fulfil/ })).toHaveAttribute('href', /page=wc-orders&status=wc-processing$/);
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
