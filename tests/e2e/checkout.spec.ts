import { expect, test, type Page, type Request } from '@playwright/test';

const storeApiInFlight = new WeakMap<Page, Set<Request>>();

test.beforeEach(({ page }) => {
	const open = new Set<Request>();
	page.on('request', (request) => /\/wc\/store\/v1\//.test(request.url()) && open.add(request));
	page.on('requestfinished', (request) => open.delete(request));
	page.on('requestfailed', (request) => open.delete(request));
	storeApiInFlight.set(page, open);
});

// A background address update still running at Place Order can overwrite the order's billing address.
async function placeOrder(page: Page) {
	const open = storeApiInFlight.get(page) ?? new Set();
	let quietSince = Date.now();
	await expect
		.poll(() => {
			if (open.size > 0) {
				quietSince = Date.now();
			}
			return Date.now() - quietSince;
		}, { timeout: 15_000, intervals: [100] })
		.toBeGreaterThan(1500);
	const button = page.getByRole('button', { name: 'Place order' });
	await button.scrollIntoViewIfNeeded();
	await button.click();
}

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

const places = {
	mgRoad: { country: 'India', city: 'Bengaluru', state: 'Karnataka', pin: '560001' },
	yelahanka: { country: 'India', city: 'Bengaluru', state: 'Karnataka', pin: '560064' },
	pune: { country: 'India', city: 'Pune', state: 'Maharashtra', pin: '411001' },
	sanFrancisco: { country: 'United States (US)', city: 'San Francisco', state: 'California', pin: '94103' },
};

async function fillDeliveryAddress(page: Page, place = places.mgRoad) {
	await page.getByLabel('Email address').fill('asha.e2e@example.com');
	const shipping = page.getByRole('group', { name: 'Shipping address' });
	if (place.country !== 'India') {
		await shipping.getByLabel('Country/Region').selectOption({ label: place.country });
	}
	await shipping.getByLabel('First name').fill('Asha');
	await shipping.getByLabel('Last name').fill('Menon');
	await shipping.getByLabel('Address', { exact: true }).fill('12 Market Street');
	await shipping.getByLabel('City').fill(place.city);
	await shipping.getByLabel('State').selectOption({ label: place.state });
	await shipping.getByLabel(/PIN Code|ZIP Code/).fill(place.pin);
	await shipping.getByLabel('Phone').fill('9123456780');
	await shipping.getByLabel('Phone').blur();
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
	await fillDeliveryAddress(page, places.pune);

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
	await fillDeliveryAddress(page, places.pune);

	await expect(page.getByRole('group', { name: 'Shipping options' }).getByText('Free delivery')).toBeVisible();
});

test('an address within 12 km of the store gets same-day delivery', async ({ page }) => {
	await addToBag(page, products.mug);

	await page.goto('/checkout/');
	await fillDeliveryAddress(page, places.mgRoad);

	const options = page.getByRole('group', { name: 'Shipping options' });
	await expect(options.getByText('Same-day delivery')).toBeVisible();
	await expect(options.getByText('₹49.00')).toBeVisible();
	await expect(options.getByText('Standard delivery')).toHaveCount(0);
});

test('a bengaluru pin beyond the same-day radius falls back to standard delivery', async ({ page }) => {
	await addToBag(page, products.mug);

	await page.goto('/checkout/');
	await fillDeliveryAddress(page, places.yelahanka);

	const options = page.getByRole('group', { name: 'Shipping options' });
	await expect(options.getByText('Standard delivery')).toBeVisible();
	await expect(options.getByText('Same-day delivery')).toHaveCount(0);
});

test('an address outside every delivery area is refused with a reason', async ({ page }) => {
	await addToBag(page, products.mug);

	await page.goto('/checkout/');
	await fillDeliveryAddress(page, places.sanFrancisco);

	await expect(page.getByText('We do not deliver to 94103 yet. Please use another delivery address.')).toBeVisible();
});

test('an indian checkout offers razorpay first and cash on delivery for goods that ship', async ({ page }) => {
	test.skip(!/^rzp_test_/.test(process.env.RAZORPAY_KEY_ID ?? ''), 'needs sandbox RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET in .env');
	await addToBag(page, products.mug);

	await page.goto('/checkout/');
	await fillDeliveryAddress(page);

	const options = page.getByRole('group', { name: 'Payment options' }).getByRole('radio');
	await expect(options.first()).toHaveAccessibleName(/Razorpay/);
	await expect(options.first()).toBeChecked();
	await expect(page.getByRole('radio', { name: /Cash on delivery/ })).toBeVisible();
});

test('cash on delivery is not offered when the bag holds a download', async ({ page }) => {
	await addToBag(page, products.mug);
	await addToBag(page, products.guide);

	await page.goto('/checkout/');
	await fillDeliveryAddress(page);

	await expect(page.getByRole('group', { name: 'Payment options' })).toBeVisible();
	await expect(page.getByRole('radio', { name: /Cash on delivery/ })).toHaveCount(0);
});

test('a guest pays on razorpay by netbanking', async ({ page }) => {
	test.skip(!/^rzp_test_/.test(process.env.RAZORPAY_KEY_ID ?? ''), 'needs sandbox RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET in .env');
	test.setTimeout(120_000);

	await addToBag(page, products.mug);
	await addToBag(page, products.guide);

	await page.goto('/checkout/');
	await fillDeliveryAddress(page);
	await page.getByRole('radio', { name: /Razorpay/ }).check();
	await placeOrder(page);

	await page.waitForURL(/order-pay/, { timeout: 30_000 });
	const razorpay = page.locator('iframe.razorpay-checkout-frame').filter({ visible: true }).contentFrame();
	await expect(razorpay.getByText(/Using as \+91/)).toBeVisible({ timeout: 20_000 });
	await expect(razorpay.getByTestId('contact-overlay-container')).toBeHidden({ timeout: 20_000 });
	await razorpay.getByRole('button', { name: /^Netbanking/ }).click();
	const bank = page.waitForEvent('popup');
	await razorpay.getByRole('button', { name: /^Canara Bank/ }).first().click();
	await (await bank).getByRole('button', { name: 'Success' }).click();

	await expect(page).toHaveURL(/order-received/, { timeout: 60_000 });
	await expect(page.getByText(/thank you/i).first()).toBeVisible();
	await expect(page.getByRole('link', { name: 'guide.pdf' })).toBeVisible();
});

test('a guest pays for a mixed bag on the stripe checkout page', async ({ page }) => {
	test.skip(!/^(sk|rk)_test_/.test(process.env.STRIPE_SECRET_KEY ?? ''), 'needs sandbox STRIPE_PUBLISHABLE_KEY and STRIPE_SECRET_KEY in .env');
	test.setTimeout(120_000);

	await addToBag(page, products.mug);
	await addToBag(page, products.guide);

	await page.goto('/checkout/');
	await fillDeliveryAddress(page);
	await page.getByRole('radio', { name: /^Stripe/ }).check();
	await expect(page.locator('iframe[title="Secure payment input frame"]')).toHaveCount(0);

	await placeOrder(page);

	await page.waitForURL(/checkout\.stripe\.com/, { timeout: 30_000 });
	await expect(page.getByText(/at Storewright/)).toBeVisible();
	await page.getByRole('textbox', { name: 'Card number' }).fill('4242424242424242');
	await page.getByRole('textbox', { name: 'Expiration' }).fill('12 / 34');
	await page.getByRole('textbox', { name: /CVC/ }).fill('123');
	await page.getByRole('textbox', { name: 'Cardholder name' }).fill('Asha Menon');
	await page.getByRole('button', { name: 'Pay' }).click();

	await expect(page).toHaveURL(/order-received/, { timeout: 60_000 });
	await expect(page.getByText(/thank you/i).first()).toBeVisible();
	await expect(page.getByRole('link', { name: 'guide.pdf' })).toBeVisible();
});
