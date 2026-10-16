import { expect, test } from '@playwright/test';

test('the home page links to every category that has products', async ({ page }) => {
	await page.goto('/');
	const grid = page.locator('.sw-categories');
	for (const name of ['Apparel', 'Books', 'Kitchen and Desk', 'Software']) {
		await expect(grid.getByRole('link', { name })).toBeVisible();
	}
	await expect(grid.getByRole('link', { name: 'Uncategorized' })).toHaveCount(0);

	await grid.getByRole('link', { name: 'Kitchen and Desk' }).click();
	await expect(page.getByRole('heading', { level: 1, name: 'Kitchen and Desk' })).toBeVisible();
	await expect(page.getByRole('link', { name: 'Stoneware Mug' }).first()).toBeVisible();
});

test('a product breadcrumb names its category once', async ({ page }) => {
	await page.goto('/product/stoneware-mug/');
	const crumbs = page.getByRole('navigation', { name: /breadcrumb/i });
	await expect(crumbs).toContainText('Home / Kitchen and Desk / Stoneware Mug');
});
