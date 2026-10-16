import { expect, test } from '@playwright/test';

const pages = ['/', '/shop/', '/product/stoneware-mug/', '/product/everyday-tee/', '/cart/', '/checkout/', '/my-account/'];

for (const path of pages) {
	test(`${path} fits the screen without sideways scrolling`, async ({ page }) => {
		await page.goto(path);
		const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
		expect(overflow).toBeLessThanOrEqual(0);
	});
}

test('the header shows the bag and account on every screen', async ({ page }) => {
	await page.goto('/');
	await expect(page.getByRole('button', { name: /Number of items in the cart/ })).toBeInViewport();
	await expect(page.locator('.wp-block-woocommerce-customer-account')).toHaveCount(1);
});
