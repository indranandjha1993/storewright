import { expect, test } from '@playwright/test';
import { account, logIn, type Account } from './support/accounts';

const productNames = /WooCommerce|WordPress/;
let operator: Account;

test.beforeAll(() => {
	operator = account('operator.e2e', 'shop_manager', 'Store Operator');
});

test.beforeEach(({}, testInfo) => {
	test.skip(testInfo.project.name !== 'desktop', 'the admin is checked at desktop width');
});

test('the login page carries the store brand only', async ({ page }) => {
	await page.goto('/sign-in/');

	await expect(page).toHaveTitle('Log In ‹ Storewright');
	await expect(page.getByRole('link', { name: 'Storewright' }).first()).toHaveAttribute('href', /localhost:\d+\/$/);
	await expect(page.locator('body')).not.toContainText(productNames, { useInnerText: true });
});

test('an operator never reads WordPress or WooCommerce in the admin', async ({ page }) => {
	await logIn(page, operator);

	for (const path of ['index.php', 'admin.php?page=wc-orders', 'edit.php?post_type=product', 'admin.php?page=wc-settings', 'admin.php?page=wc-settings&tab=checkout', 'admin.php?page=wc-admin']) {
		await page.goto(`/wp/wp-admin/${path}`);
		await expect(page.locator('#wpbody-content')).not.toBeEmpty();
		await expect(page).toHaveTitle(/‹ Storewright$/);
		await expect(page.locator('body')).not.toContainText(productNames, { useInnerText: true });
		await expect(page.locator('#contextual-help-link')).toHaveCount(0);
		await expect(page.locator('#wpfooter')).toHaveText('Storewright');
	}
});

test('the operator menu shows the journal and leaves out comments, tools and the marketplace', async ({ page }) => {
	await logIn(page, operator);
	await page.goto('/wp/wp-admin/index.php');
	const menu = page.locator('#adminmenu');

	await expect(menu.getByRole('link', { name: 'Store', exact: true })).toBeVisible();
	await expect(menu.getByRole('link', { name: 'Journal', exact: true })).toBeVisible();
	for (const hidden of ['Posts', 'Comments', 'Tools', 'Extensions', 'Status']) {
		await expect(menu.getByRole('link', { name: hidden, exact: true })).toHaveCount(0);
	}
	await expect(menu.getByRole('link', { name: 'Marketing', exact: true })).toHaveAttribute('href', 'edit.php?post_type=shop_coupon');
	await expect(page.locator('#wp-admin-bar-wp-logo')).toHaveCount(0);
	await expect(page.locator('#wp-admin-bar-my-account > a')).toHaveText(/^Store Operator/);
});

test('the plugins and the theme a store needs cannot be switched off', async ({ page }) => {
	await logIn(page, account('owner.e2e', 'administrator', 'Store Owner'));
	await page.goto('/wp/wp-admin/plugins.php');
	for (const slug of ['woocommerce', 'woo-razorpay', 'storewright-core', 'storewright-console']) {
		const row = page.locator(`tr[data-slug="${slug}"]`);
		await expect(row).toContainText('Required by Storewright');
		await expect(row.getByRole('link', { name: /^Deactivate/ })).toHaveCount(0);
	}
	await expect(page.locator('#bulk-action-selector-top option[value="deactivate-selected"]')).toHaveCount(0);
	await expect(page.getByRole('link', { name: /Add New Plugin|Add New/ })).toHaveCount(0);

	await page.goto('/wp/wp-admin/themes.php');
	await expect(page.locator('.theme')).toHaveCount(1);
	await expect(page.locator('.theme.active')).toContainText('Storewright');
});
