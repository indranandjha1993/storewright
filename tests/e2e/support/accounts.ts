import { randomBytes } from 'node:crypto';
import { expect, type Page } from '@playwright/test';
import { wp } from './wp';

export type Account = { email: string; password: string };

const roster = {
	'account.e2e': ['customer', 'Meera Rao'],
	'areas.e2e': ['shop_manager', 'Areas Staff'],
	'brand.e2e': ['shop_manager', 'Brand Staff'],
	'console.e2e': ['shop_manager', 'Console Staff'],
	'operator.e2e': ['shop_manager', 'Store Operator'],
	'owner.e2e': ['administrator', 'Store Owner'],
	'returns.e2e': ['shop_manager', 'Returns Staff'],
	'shopper.e2e': ['customer', 'Shopper'],
};

export type Login = keyof typeof roster;

// Changing a password signs that user out everywhere, so the roster is set once per run, never per worker.
export function createAccounts(): string {
	const password = randomBytes(18).toString('base64url');
	wp('eval', `add_filter( "send_password_change_email", "__return_false" ); foreach ( json_decode( '${JSON.stringify(roster)}', true ) as $login => [ $role, $name ] ) { $data = [ "user_pass" => "${password}", "role" => $role, "display_name" => $name ]; $user = get_user_by( "email", "$login@example.com" ); if ( $user ) { wp_update_user( $data + [ "ID" => $user->ID ] ); } else { wp_insert_user( $data + [ "user_login" => $login, "user_email" => "$login@example.com" ] ); } }`);
	return password;
}

export function account(login: Login): Account {
	return { email: `${login}@example.com`, password: process.env.E2E_PASSWORD ?? '' };
}

export async function logIn(page: Page, who: Account) {
	await page.goto('/sign-in/');
	const username = page.getByLabel('Username or Email Address');
	// The sign-in page focuses and selects this field 200 ms after load, which would catch the password typed next.
	await expect(username).toBeFocused();
	await username.fill(who.email);
	await page.getByLabel('Password', { exact: true }).fill(who.password);
	await page.getByRole('button', { name: 'Log In' }).click();
	await page.waitForURL((url) => !url.pathname.includes('/sign-in'));
}
