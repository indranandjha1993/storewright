import { expect, test, type Browser, type Page } from '@playwright/test';
import { account, logIn, type Login } from './support/accounts';

type Who = 'guest' | 'shopper' | 'staff' | 'owner';

const logins: Record<Exclude<Who, 'guest'>, Login> = { shopper: 'shopper.e2e', staff: 'operator.e2e', owner: 'owner.e2e' };

async function open(browser: Browser, who: Who): Promise<Page> {
	const page = await (await browser.newContext()).newPage();
	if (who !== 'guest') {
		await logIn(page, account(logins[who]));
	}
	return page;
}

async function outcome(page: Page, path: string): Promise<string> {
	await page.goto(`/wp/wp-admin/${path}`);
	const url = new URL(page.url());
	if (url.pathname.includes('/sign-in')) {
		return 'sign-in';
	}
	if (url.pathname.startsWith('/my-account')) {
		return 'account';
	}
	if ((await page.locator('#wpbody-content').count()) === 0) {
		return 'refused';
	}
	return 'page';
}

const screens: Array<[string, Record<Who, string>]> = [
	['', { guest: 'sign-in', shopper: 'account', staff: 'page', owner: 'page' }],
	['admin.php?page=wc-orders', { guest: 'sign-in', shopper: 'account', staff: 'page', owner: 'page' }],
	['admin.php?page=storewright-returns', { guest: 'sign-in', shopper: 'account', staff: 'page', owner: 'page' }],
	['admin.php?page=storewright-payments', { guest: 'sign-in', shopper: 'account', staff: 'page', owner: 'page' }],
	['admin.php?page=storewright-areas', { guest: 'sign-in', shopper: 'account', staff: 'page', owner: 'page' }],
	['admin.php?page=storewright-brand', { guest: 'sign-in', shopper: 'account', staff: 'page', owner: 'page' }],
	['admin.php?page=wc-settings&tab=charges', { guest: 'sign-in', shopper: 'account', staff: 'page', owner: 'page' }],
	['edit.php?post_type=product', { guest: 'sign-in', shopper: 'account', staff: 'page', owner: 'page' }],
	['plugins.php', { guest: 'sign-in', shopper: 'account', staff: 'refused', owner: 'page' }],
	['themes.php', { guest: 'sign-in', shopper: 'account', staff: 'refused', owner: 'page' }],
	['options-general.php', { guest: 'sign-in', shopper: 'account', staff: 'refused', owner: 'page' }],
	['site-editor.php', { guest: 'sign-in', shopper: 'account', staff: 'refused', owner: 'page' }],
	['admin.php?page=wc-status', { guest: 'sign-in', shopper: 'account', staff: 'refused', owner: 'page' }],
];

for (const who of ['guest', 'shopper', 'staff', 'owner'] as Who[]) {
	test(`a ${who} reaches exactly the admin screens their role allows`, async ({ browser }) => {
		test.setTimeout(120_000);
		const page = await open(browser, who);
		const seen: Record<string, string> = {};
		for (const [path] of screens) {
			seen[path] = await outcome(page, path);
		}
		expect(seen).toEqual(Object.fromEntries(screens.map(([path, expected]) => [path, expected[who]])));
		await page.context().close();
	});
}

const routes: Array<[string, string, Record<string, unknown> | undefined, Record<Who, number>]> = [
	['POST', '/storewright/v1/delivery', { pin: '560001' }, { guest: 200, shopper: 200, staff: 200, owner: 200 }],
	['POST', '/storewright/v1/areas/test', { pin: '560001' }, { guest: 401, shopper: 403, staff: 200, owner: 200 }],
	['GET', '/wc/v3/orders', undefined, { guest: 401, shopper: 403, staff: 200, owner: 200 }],
	['GET', '/wc/v3/system_status', undefined, { guest: 401, shopper: 403, staff: 403, owner: 200 }],
	['GET', '/wp/v2/settings', undefined, { guest: 401, shopper: 403, staff: 403, owner: 200 }],
];

for (const who of ['guest', 'shopper', 'staff', 'owner'] as Who[]) {
	test(`the api answers a ${who} according to their role`, async ({ browser }) => {
		const page = await open(browser, who);
		await page.goto('/cart/');
		const seen: Record<string, number> = {};
		for (const [method, path, data] of routes) {
			seen[`${method} ${path}`] = await page.evaluate(async ([m, p, d]) => {
				const api = (window as any).wp?.apiFetch;
				if (!api) {
					return -1;
				}
				try {
					await api({ path: p, method: m, data: d });
					return 200;
				} catch (error: any) {
					return Number(error?.data?.status ?? 0);
				}
			}, [method, path, data] as const);
		}
		expect(seen).toEqual(Object.fromEntries(routes.map(([method, path, , expected]) => [`${method} ${path}`, expected[who]])));
		await page.context().close();
	});
}

test('admin actions refuse a request without a fresh nonce', async ({ browser }) => {
	for (const who of ['shopper', 'staff'] as Who[]) {
		const page = await open(browser, who);
		await page.goto('/wp/wp-admin/admin-post.php?action=storewright_job_retry&id=1&_wpnonce=forged');
		await expect(page.locator('body')).toContainText('The link you followed has expired');
		await page.context().close();
	}
});

test('health and version answer without signing in', async ({ request }) => {
	expect((await request.get('/health')).status()).toBe(200);
	expect((await request.get('/version')).status()).toBe(200);
	expect((await request.get('/wp/wp-login.php')).status()).toBe(404);
});
