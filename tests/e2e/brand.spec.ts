import { expect, test, type Page } from '@playwright/test';
import { account, logIn, type Account } from './support/accounts';
import { wp } from './support/wp';

let staff: Account;

const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', 'base64');

function resetBrand() {
	wp('storewright', 'brand', 'reset');
	wp('eval', 'update_option( "site_logo", 0 ); remove_theme_mod( "custom_logo" ); foreach ( get_posts( [ "post_type" => "attachment", "title" => "logo", "post_status" => "any", "posts_per_page" => -1, "fields" => "ids" ] ) as $id ) { wp_delete_attachment( $id, true ); }');
}

test.beforeAll(() => {
	staff = account('brand.e2e');
	resetBrand();
});

test.afterAll(() => resetBrand());

async function openBrand(page: Page) {
	await logIn(page, staff);
	await page.goto('/wp/wp-admin/admin.php?page=storewright-brand');
	return brandForm(page);
}

async function brandForm(page: Page) {
	await expect(page.getByRole('heading', { name: 'Brand' })).toBeVisible();
	return page.getByRole('form', { name: 'Brand' });
}

const savedNotice = (page: Page) => page.locator('.notice-success');

const cssVariable = (page: Page, name: string) => page.evaluate((v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim(), name);

test('one save restyles the storefront, sign-in page, admin and emails', async ({ page }) => {
	const form = await openBrand(page);

	await form.getByLabel('Accent', { exact: true }).fill('#7A1F5C');
	await form.getByLabel('Headings').selectOption('fraunces');
	await form.getByLabel('Round, with pill buttons').check();
	await expect(page.locator('.sw-brand__preview-button')).toHaveCSS('border-radius', '999px');
	await form.getByRole('button', { name: 'Save brand' }).click();
	await expect(savedNotice(page)).toContainText('Saved. The store is wearing the new brand.');
	expect(await cssVariable(page, '--sw-accent')).toBe('#7a1f5c');

	await page.goto('/');
	expect(await cssVariable(page, '--wp--preset--color--accent')).toBe('#7a1f5c');
	const button = page.getByRole('link', { name: 'Shop everything' });
	await expect(button).toHaveCSS('background-color', 'rgb(122, 31, 92)');
	await expect(button).toHaveCSS('border-radius', '999px');
	await expect(page.getByRole('heading', { level: 1 })).toHaveCSS('font-family', /Fraunces Variable/);
	await expect(page.locator('meta[name="theme-color"]')).toHaveAttribute('content', '#7a1f5c');

	await page.context().clearCookies();
	await page.goto('/sign-in/');
	await expect(page.locator('#wp-submit')).toHaveCSS('background-color', 'rgb(122, 31, 92)');

	expect(wp('option', 'get', 'woocommerce_email_base_color')).toBe('#7a1f5c');
	expect(wp('option', 'get', 'woocommerce_email_font_family')).toBe('Helvetica');
});

test('a logo replaces the store name in the header', async ({ page }) => {
	const form = await openBrand(page);

	await form.getByLabel('Logo file').setInputFiles({ name: 'logo.png', mimeType: 'image/png', buffer: PNG });
	await form.getByRole('button', { name: 'Save brand' }).click();
	await expect(savedNotice(page)).toContainText('Saved. The store is wearing the new brand.');
	await expect(page.getByRole('img', { name: 'Current logo' })).toBeVisible();

	await page.goto('/');
	const header = page.locator('header .sw-header__brand');
	await expect(header.locator('.wp-block-site-logo img')).toBeVisible();
	await expect(header.locator('.wp-block-site-title')).not.toBeInViewport();

	await page.goto('/wp/wp-admin/admin.php?page=storewright-brand');
	const again = await brandForm(page);
	await again.locator('.sw-brand__image').filter({ has: page.getByRole('img', { name: 'Current logo' }) }).getByLabel('Remove').check();
	await again.getByRole('button', { name: 'Save brand' }).click();
	await expect(savedNotice(page)).toContainText('Saved. The store is wearing the new brand.');
	await page.goto('/');
	await expect(page.locator('header .wp-block-site-logo')).toHaveCount(0);
	await expect(page.locator('header .wp-block-site-title')).toBeVisible();
});

test('the store description is what search results and link previews show', async ({ page }) => {
	const before = wp('option', 'get', 'blogdescription');
	const form = await openBrand(page);
	await form.getByLabel('Store description').fill('Hand thrown mugs and paper goods from a small studio.');
	await form.getByRole('button', { name: 'Save brand' }).click();
	await expect(savedNotice(page)).toContainText('Saved.');

	await page.goto('/');
	await expect(page.locator('meta[name="description"]')).toHaveAttribute('content', 'Hand thrown mugs and paper goods from a small studio.');
	await expect(page.locator('meta[property="og:site_name"]')).toHaveAttribute('content', /.+/);
	wp('option', 'update', 'blogdescription', before);
});

test('unreadable text is refused and nothing changes', async ({ page }) => {
	const form = await openBrand(page);

	await form.getByLabel('Text', { exact: true }).fill('#cccccc');
	await form.getByRole('button', { name: 'Save brand' }).click();

	await expect(page.locator('.notice-error')).toContainText('The text colour needs more contrast against the background so the store stays readable.');
	await expect(page.getByRole('form', { name: 'Brand' }).getByLabel('Text', { exact: true })).toHaveValue('#cccccc');
	expect(wp('storewright', 'brand', 'show', '--format=json')).not.toContain('#cccccc');
});

test('the brand page is closed to anyone who is not staff', async ({ page, request }) => {
	expect((await request.get('/wp-json/storewright/v1/brand')).status()).toBe(404);
	await logIn(page, account('shopper.e2e'));
	await page.goto('/wp/wp-admin/admin.php?page=storewright-brand');
	await expect(page.getByRole('form', { name: 'Brand' })).toHaveCount(0);
});
