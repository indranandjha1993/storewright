import { execFileSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { expect, test, type Page } from '@playwright/test';

const operator = { email: 'operator.e2e@example.com', password: randomBytes(18).toString('base64url') };
const productNames = /WooCommerce|WordPress/;

function wp(...args: string[]) {
	execFileSync('docker', ['compose', '--progress', 'quiet', 'run', '--rm', 'cli', 'wp', ...args], { stdio: 'pipe' });
}

async function logIn(page: Page) {
	await page.goto('/wp/wp-login.php');
	await page.getByLabel('Username or Email Address').fill(operator.email);
	await page.getByLabel('Password', { exact: true }).fill(operator.password);
	await page.getByRole('button', { name: 'Log In' }).click();
	await page.waitForURL(/wp-admin/);
}

test.beforeAll(() => {
	try {
		wp('user', 'update', operator.email, `--user_pass=${operator.password}`, '--role=shop_manager', '--skip-email');
	} catch {
		wp('user', 'create', 'e2e-operator', operator.email, '--role=shop_manager', '--display_name=Store Operator', `--user_pass=${operator.password}`);
	}
});

test.beforeEach(({}, testInfo) => {
	test.skip(testInfo.project.name !== 'desktop', 'the admin is checked at desktop width');
});

test('the login page carries the store brand only', async ({ page }) => {
	await page.goto('/wp/wp-login.php');

	await expect(page).toHaveTitle('Log In ‹ Storewright');
	await expect(page.getByRole('link', { name: 'Storewright' }).first()).toHaveAttribute('href', /localhost:\d+\/$/);
	await expect(page.locator('body')).not.toContainText(productNames, { useInnerText: true });
});

test('an operator never reads WordPress or WooCommerce in the admin', async ({ page }) => {
	await logIn(page);

	for (const path of ['index.php', 'admin.php?page=wc-orders', 'edit.php?post_type=product', 'admin.php?page=wc-settings', 'admin.php?page=wc-settings&tab=checkout', 'admin.php?page=wc-admin']) {
		await page.goto(`/wp/wp-admin/${path}`);
		await expect(page.locator('#wpbody-content')).not.toBeEmpty();
		await expect(page).toHaveTitle(/‹ Storewright$/);
		await expect(page.locator('body')).not.toContainText(productNames, { useInnerText: true });
		await expect(page.locator('#contextual-help-link')).toHaveCount(0);
		await expect(page.locator('#wpfooter')).toHaveText('Storewright');
	}
});

test('the operator menu leaves out posts, comments, tools and the marketplace', async ({ page }) => {
	await logIn(page);
	await page.goto('/wp/wp-admin/index.php');
	const menu = page.locator('#adminmenu');

	await expect(menu.getByRole('link', { name: 'Store', exact: true })).toBeVisible();
	for (const hidden of ['Posts', 'Comments', 'Tools', 'Extensions', 'Status']) {
		await expect(menu.getByRole('link', { name: hidden, exact: true })).toHaveCount(0);
	}
	await expect(menu.getByRole('link', { name: 'Marketing', exact: true })).toHaveAttribute('href', 'edit.php?post_type=shop_coupon');
	await expect(page.locator('#wp-admin-bar-wp-logo')).toHaveCount(0);
	await expect(page.locator('#wp-admin-bar-my-account > a')).toHaveText(/^Store Operator/);
});
