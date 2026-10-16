import { expect, test, type Page } from '@playwright/test';

const products = {
	mug: { slug: 'stoneware-mug', name: 'Stoneware Mug' },
	notebook: { slug: 'dot-grid-notebook', name: 'Dot Grid Notebook' },
	guide: { slug: 'running-a-small-shop-online', name: 'Running a Small Shop Online' },
	tee: { slug: 'everyday-tee', name: 'Everyday Tee' },
};

async function addToBag(page: Page, product: { slug: string; name: string }, options: string[] = []) {
	await page.goto(`/product/${product.slug}/`);
	for (const option of options) {
		await page.getByRole('radio', { name: option, exact: true }).check();
	}
	const button = options.length
		? page.getByRole('button', { name: 'Add to bag', exact: true })
		: page.getByRole('button', { name: `Add to bag: “${product.name}”` });
	const added = page.waitForResponse(
		(response) => response.request().method() === 'POST' && /\/wc\/store\/v1\/(cart\/add-item|batch)/.test(response.url())
	);
	await button.click();
	expect((await added).ok()).toBe(true);
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
	await addToBag(page, products.mug);
	await addToBag(page, products.guide);

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

test('a shopper picks a size and colour before adding the tee', async ({ page }) => {
	await addToBag(page, products.tee, ['M', 'Black']);

	await page.goto('/cart/');
	const row = page.getByRole('row', { name: /Everyday Tee/ });
	await expect(row.getByText('M', { exact: true })).toBeVisible();
	await expect(row.getByText('Black', { exact: true })).toBeVisible();
});

test('the bag drawer stays out of reach until it is opened', async ({ page }) => {
	await addToBag(page, products.mug);

	const drawer = page.getByRole('dialog');
	await expect(drawer).toBeHidden();
	await expect(page.locator('.wc-block-mini-cart__drawer button:visible')).toHaveCount(0);

	await page.getByRole('button', { name: /Number of items in the cart/ }).click();
	await expect(drawer).toBeVisible();
	await expect(drawer.getByRole('link', { name: 'Stoneware Mug' }).last()).toBeVisible();

	await page.keyboard.press('Escape');
	await expect(drawer).toBeHidden();
});

test('a digital only bag asks for no shipping address', async ({ page }) => {
	await addToBag(page, products.guide);

	await page.goto('/checkout/');
	await expect(page.getByRole('group', { name: 'Billing address' })).toBeVisible();
	await expect(page.getByRole('group', { name: 'Shipping address' })).toHaveCount(0);
});

test('an indian address below 999 is offered standard delivery only', async ({ page }) => {
	await addToBag(page, products.mug);
	await addToBag(page, products.guide);

	await page.goto('/checkout/');
	await fillDeliveryAddress(page);

	const options = page.getByRole('group', { name: 'Shipping options' });
	await expect(options.getByText('Standard delivery')).toBeVisible();
	await expect(options.getByText('₹79.00')).toBeVisible();
	await expect(options.getByText('Free delivery')).toHaveCount(0);
});

test('free delivery is offered from 999', async ({ page }) => {
	await addToBag(page, products.mug);
	await addToBag(page, products.notebook);
	await addToBag(page, products.guide);

	await page.goto('/checkout/');
	await fillDeliveryAddress(page);

	await expect(page.getByRole('group', { name: 'Shipping options' }).getByText('Free delivery')).toBeVisible();
});

test('a guest pays for a mixed bag with a stripe test card', async ({ page }) => {
	test.skip(!process.env.STRIPE_SECRET_KEY?.startsWith('sk_test_') && !process.env.STRIPE_SECRET_KEY?.startsWith('rk_test_'), 'needs sandbox STRIPE_PUBLISHABLE_KEY and STRIPE_SECRET_KEY in .env');

	await addToBag(page, products.mug);
	await addToBag(page, products.guide);

	await page.goto('/checkout/');
	await fillDeliveryAddress(page);

	await page.getByRole('radio', { name: 'Card or wallet' }).check();
	const card = page.frameLocator('iframe[title="Secure payment input frame"]').first();
	await card.getByRole('textbox', { name: 'Card number' }).fill('4242424242424242');
	await card.getByRole('textbox', { name: /Expiration date/ }).fill('12 / 34');
	await card.getByRole('textbox', { name: 'Security code' }).fill('123');
	const placeOrder = page.getByRole('button', { name: 'Place order' });
	// The Stripe element drops a click that Playwright scrolls and fires in one step; a shopper scrolls first.
	await placeOrder.scrollIntoViewIfNeeded();
	await placeOrder.click();

	await expect(page).toHaveURL(/order-received/, { timeout: 45_000 });
	await expect(page.getByText(/thank you/i).first()).toBeVisible();
	await expect(page.getByRole('link', { name: 'guide.pdf' })).toBeVisible();
});
