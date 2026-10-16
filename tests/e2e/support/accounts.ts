import { randomBytes } from 'node:crypto';
import type { Page } from '@playwright/test';
import { wp } from './wp';

export type Account = { email: string; password: string };

export function account(login: string, role: string, name: string): Account {
	const email = `${login}@example.com`;
	const password = randomBytes(18).toString('base64url');
	try {
		wp('user', 'update', email, `--user_pass=${password}`, `--role=${role}`, '--skip-email');
	} catch {
		wp('user', 'create', login, email, `--role=${role}`, `--display_name=${name}`, `--user_pass=${password}`);
	}
	return { email, password };
}

export async function logIn(page: Page, who: Account) {
	await page.goto('/sign-in/');
	await page.getByLabel('Username or Email Address').fill(who.email);
	await page.getByLabel('Password', { exact: true }).fill(who.password);
	await page.getByRole('button', { name: 'Log In' }).click();
	await page.waitForURL((url) => !url.pathname.includes('/sign-in'));
}
