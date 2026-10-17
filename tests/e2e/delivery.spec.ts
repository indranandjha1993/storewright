import { expect, test } from '@playwright/test';

test.beforeEach(async ({ context }) => {
	await context.clearCookies();
});

test('a shopper checks a pin code from the header', async ({ page }) => {
	await page.goto('/');
	const chip = page.locator('.sw-delivery--header');

	await chip.locator('summary').click();
	await chip.getByLabel('PIN code').fill('560001');
	await chip.getByRole('button', { name: 'Check' }).click();

	await expect(chip.getByRole('status')).toHaveText('We deliver to 560001, Bengaluru Urban.');
	await expect(chip.locator('[data-sw-delivery-label]')).toHaveText('560001');
});

test('an unknown pin code is reported as not found', async ({ page }) => {
	await page.goto('/');
	const chip = page.locator('.sw-delivery--header');

	await chip.locator('summary').click();
	await chip.getByLabel('PIN code').fill('100000');
	await chip.getByRole('button', { name: 'Check' }).click();

	await expect(chip.getByRole('status')).toHaveText('We could not find PIN 100000. Check the number and try again.');
});

test.describe('with a shared location near MG Road', () => {
	test.use({ geolocation: { latitude: 12.95, longitude: 77.6 }, permissions: ['geolocation'] });

	test('use my location finds the nearest pin and prefills checkout', async ({ page }) => {
		await page.goto('/product/stoneware-mug/');
		const chip = page.locator('.sw-delivery--header');

		await chip.locator('summary').click();
		await chip.getByRole('button', { name: 'Use my location' }).click();
		await expect(chip.getByRole('status')).toHaveText('We deliver to 560030, Bengaluru Urban.');

		const added = page.waitForResponse((r) => r.request().method() === 'POST' && /wc\/store\/v1\/(cart\/add-item|batch)/.test(r.url()));
		await page.getByRole('button', { name: 'Add to bag: “Stoneware Mug”' }).click();
		expect((await added).ok()).toBe(true);
		await page.goto('/checkout/');
		await expect(page.getByRole('group', { name: 'Shipping address' }).getByLabel('PIN Code')).toHaveValue('560030');
		await expect(page.getByRole('group', { name: 'Shipping options' }).getByText('Same-day delivery')).toBeVisible();
	});
});

test('the product page shows the answer for the chosen pin code', async ({ page }) => {
	await page.goto('/');
	const chip = page.locator('.sw-delivery--header');
	await chip.locator('summary').click();
	await chip.getByLabel('PIN code').fill('411001');
	await chip.getByRole('button', { name: 'Check' }).click();
	await expect(chip.getByRole('status')).toHaveText('We deliver to 411001, Pune.');

	await page.goto('/product/stoneware-mug/');
	const product = page.locator('.sw-delivery--product');
	await expect(product.getByRole('status')).toHaveText('We deliver to 411001, Pune.');
	await expect(product.locator('.sw-delivery__options li')).toHaveText(['Standard delivery₹79.00', 'Free deliveryFree on orders of ₹999.00 or more']);
});

test.describe('without javascript', () => {
	test.use({ javaScriptEnabled: false });

	test('the pin code form still answers', async ({ page }) => {
		await page.goto('/shop/');
		const chip = page.locator('.sw-delivery--header');

		await chip.locator('summary').click();
		await chip.getByLabel('PIN code').fill('560034');
		await chip.getByRole('button', { name: 'Check' }).click();

		await expect(page).toHaveURL(/\/shop\/$/);
		await expect(chip.locator('[data-sw-delivery-label]')).toHaveText('560034');
		await expect(chip.getByRole('button', { name: 'Use my location' })).toBeHidden();
	});
});
