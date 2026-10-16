import { expect, test } from '@playwright/test';

test('the usual probes answer 404', async ({ request }) => {
	for (const path of ['/wp/wp-login.php', '/wp/readme.html', '/wp/license.txt', '/wp/wp-cron.php', '/wp/wp-admin/install.php', '/wp/wp-includes/version.php', '/composer.json', '/package.json']) {
		expect((await request.get(path, { maxRedirects: 0 })).status(), path).toBe(404);
	}
	expect((await request.get('/wp/xmlrpc.php')).status()).toBe(403);
});

test('the home page gives nothing away', async ({ request }) => {
	const response = await request.get('/');
	const html = await response.text();
	const headers = response.headers();

	for (const mark of ['name="generator"', 'wlwmanifest', 'xmlrpc.php?rsd', 'api.w.org', 'rel="shortlink"', 'oembed/1.0', 'ver=7.']) {
		expect(html, mark).not.toContain(mark);
	}
	expect(headers['x-powered-by']).toBeUndefined();
	expect(headers['link']).toBeUndefined();
	expect(headers['x-content-type-options']).toBe('nosniff');
	expect(headers['permissions-policy']).toContain('camera=()');
	expect(headers['server']).toBe('nginx');
});

test('the rest index is quiet for visitors and users are hidden', async ({ request }) => {
	const index = await (await request.get('/wp-json/')).json();
	expect(index.routes).toBeUndefined();
	expect(index.namespaces).toBeUndefined();
	expect((await request.get('/wp-json/wp/v2/users')).status()).toBe(404);
	expect((await request.get('/wp/?author=1', { maxRedirects: 0 })).status()).toBe(404);
	expect((await request.get('/wp-json/wc/store/v1/products?per_page=1')).status()).toBe(200);
});

test('the sign-in page works and wrong passwords are throttled', async ({ page }) => {
	await page.goto('/sign-in/');
	await expect(page.getByLabel('Username or Email Address')).toBeVisible();
	await expect(page.locator('form#loginform')).toHaveAttribute('action', /\/sign-in\/$/);

	const name = `nobody-${Date.now()}`;
	const attempt = async () => {
		const response = await page.request.post('/sign-in/', { form: { log: name, pwd: 'wrong-password', 'wp-submit': 'Log In', testcookie: '1' }, maxRedirects: 0 });
		return response.text();
	};
	expect(await attempt()).toContain('Check your username and password');
	for (let i = 0; i < 4; i++) {
		await attempt();
	}
	const locked = await attempt();
	expect(locked).toContain('Too many attempts');
	expect(locked).not.toContain('Check your username and password');
});
