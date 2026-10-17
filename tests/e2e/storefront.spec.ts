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

test('the shop narrows by category and clears again', async ({ page, isMobile }) => {
	await page.goto('/shop/');
	const products = page.locator('.wc-block-product-template > li');
	await expect(products).toHaveCount(5);

	if (isMobile) {
		await page.getByRole('button', { name: 'Filter products' }).click();
	}
	await page.getByRole('checkbox', { name: 'Kitchen and Desk' }).check();
	if (isMobile) {
		await page.getByRole('button', { name: /Apply/ }).click();
	}
	await expect(page).toHaveURL(/categories=kitchen-and-desk/);
	await expect(products).toHaveCount(2);
	await expect(page.getByRole('link', { name: 'Stoneware Mug' }).first()).toBeVisible();

	await page.goto('/shop/?categories=kitchen-and-desk');
	if (isMobile) {
		await page.getByRole('button', { name: 'Filter products' }).click();
	}
	await page.getByRole('button', { name: 'Clear all' }).click();
	if (isMobile) {
		await page.getByRole('button', { name: /Apply/ }).click();
	}
	await expect(products).toHaveCount(5);
});

test('search suggests products as the shopper types', async ({ page }) => {
	await page.goto('/');
	const search = page.locator('.sw-header__actions .wp-block-search');
	await search.getByRole('button', { name: 'Search' }).click();
	await search.getByRole('searchbox').fill('mug');

	const suggestions = page.getByRole('listbox', { name: 'Search suggestions' });
	await expect(suggestions.getByRole('option', { name: /Stoneware Mug/ })).toBeVisible();
	await expect(suggestions.getByRole('option', { name: 'See all results for "mug"' })).toBeVisible();

	await search.getByRole('searchbox').press('ArrowDown');
	await page.keyboard.press('Enter');
	await expect(page).toHaveURL(/\/product\/stoneware-mug\//);
});

test('a search with no matches offers a way back into the catalogue', async ({ page }) => {
	await page.goto('/?s=zzzz&post_type=product');
	await expect(page.getByRole('heading', { level: 1 })).toHaveText('\u201czzzz\u201d');
	await expect(page.getByText('Nothing matches "zzzz" yet.')).toBeVisible();
	await page.getByRole('link', { name: 'Books' }).last().click();
	await expect(page.getByRole('heading', { level: 1, name: 'Books' })).toBeVisible();
});

test('a product shows its rating summary and verified reviews', async ({ page }) => {
	await page.goto('/product/stoneware-mug/');
	await page.getByRole('tab', { name: 'Reviews (3)' }).click();

	const summary = page.getByRole('complementary', { name: 'Rating summary' });
	await expect(summary).toContainText('4.7');
	await expect(summary).toContainText('Based on 3 reviews');
	await expect(summary.getByText('67%')).toBeVisible();
	await expect(page.getByRole('heading', { name: '3 reviews' })).toBeVisible();
	await expect(page.locator('.commentlist > li').first()).toContainText('verified owner');
	await expect(page.getByText('Only logged in customers who have purchased this product may leave a review.')).toBeVisible();
});

