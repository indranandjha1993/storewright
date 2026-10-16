import { expect, test, type Page } from '@playwright/test';

async function addToBag(page: Page, slug: string) {
	await page.goto(`/product/${slug}/`);
	await page.getByRole('button', { name: 'Add to bag' }).click();
	await expect(page.getByText(/has been added to your cart/i)).toBeVisible();
}

async function fillDeliveryAddress(page: Page) {
	await page.getByLabel('Email address').fill('asha.e2e@example.com');
	const shipping = page.getByRole('group', { name: 'Shipping address' });
	await shipping.getByLabel('First name').fill('Asha');
	await shipping.getByLabel('Last name').fill('Menon');
	await shipping.getByLabel('Address', { exact: true }).fill('12 Market Street');
	await shipping.getByLabel('City').fill('Bengaluru');
	await shipping.getByLabel('State').selectOption({ label: 'Karnataka' });
	await shipping.getByLabel('PIN Code').fill('560001');
	await shipping.getByLabel('PIN Code').blur();
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

test('an indian address below 999 is offered standard delivery only', async ({ page }) => {
	await addToBag(page, 'stoneware-mug');
	await addToBag(page, 'running-a-small-shop-online');

	await page.goto('/checkout/');
	await fillDeliveryAddress(page);

	const options = page.getByRole('group', { name: 'Shipping options' });
	await expect(options.getByText('Standard delivery')).toBeVisible();
	await expect(options.getByText('₹79.00')).toBeVisible();
	await expect(options.getByText('Free delivery')).toHaveCount(0);
});

test('free delivery is offered from 999', async ({ page }) => {
	await addToBag(page, 'stoneware-mug');
	await addToBag(page, 'dot-grid-notebook');
	await addToBag(page, 'running-a-small-shop-online');

	await page.goto('/checkout/');
	await fillDeliveryAddress(page);

	await expect(page.getByRole('group', { name: 'Shipping options' }).getByText('Free delivery')).toBeVisible();
});

test('a guest pays for a mixed bag with a stripe test card', async ({ page }) => {
	test.skip(!process.env.STRIPE_TEST_PUBLISHABLE_KEY, 'needs STRIPE_TEST_PUBLISHABLE_KEY and STRIPE_TEST_SECRET_KEY in .env');

	await addToBag(page, 'stoneware-mug');
	await addToBag(page, 'running-a-small-shop-online');

	await page.goto('/checkout/');
	await fillDeliveryAddress(page);

	const card = page.frameLocator('iframe[title="Secure payment input frame"]').first();
	await card.getByLabel('Card number').fill('4242424242424242');
	await card.getByLabel(/Expiration/).fill('12 / 34');
	await card.getByLabel(/Security code|CVC/).fill('123');

	await page.getByRole('button', { name: 'Place order' }).click();

	await expect(page).toHaveURL(/order-received/, { timeout: 30_000 });
	await expect(page.getByText(/thank you/i).first()).toBeVisible();
	await expect(page.getByRole('link', { name: 'guide.pdf' })).toBeVisible();
});
