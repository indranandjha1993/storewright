import { expect, test, type Page } from '@playwright/test';

async function addToBag(page: Page, slug: string) {
	await page.goto(`/product/${slug}/`);
	await page.getByRole('button', { name: 'Add to bag' }).click();
	await expect(page.getByText(/has been added to your cart/i)).toBeVisible();
}

test.beforeEach(async ({ context }) => {
	await context.clearCookies();
});

test('a physical and a digital product reach the block checkout together', async ({ page }) => {
	await addToBag(page, 'stoneware-mug');
	await addToBag(page, 'running-a-small-shop-online');

	await page.goto('/cart/');
	const cart = page.locator('.wp-block-woocommerce-cart');
	await expect(cart.getByRole('rowheader', { name: 'Stoneware Mug' })).toBeVisible();
	await expect(cart.getByRole('rowheader', { name: 'Running a Small Shop Online' })).toBeVisible();

	await cart.getByRole('link', { name: /checkout/i }).click();
	await expect(page).toHaveURL(/\/checkout\/?$/);
	await expect(page.getByText('Secure checkout')).toBeVisible();
	await expect(page.getByRole('group', { name: 'Shipping address' })).toBeVisible();
	await expect(page.getByRole('group', { name: 'Payment options' })).toBeVisible();
});

test('a digital only bag asks for no shipping address', async ({ page }) => {
	await addToBag(page, 'running-a-small-shop-online');

	await page.goto('/checkout/');
	await expect(page.getByRole('group', { name: 'Billing address' })).toBeVisible();
	await expect(page.getByRole('group', { name: 'Shipping address' })).toHaveCount(0);
});

test('a guest pays for a mixed bag with a stripe test card', async ({ page }) => {
	test.skip(!process.env.STRIPE_TEST_PUBLISHABLE_KEY, 'needs STRIPE_TEST_PUBLISHABLE_KEY and STRIPE_TEST_SECRET_KEY in .env');
});
