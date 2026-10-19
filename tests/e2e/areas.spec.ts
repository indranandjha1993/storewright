import { expect, test, type Locator, type Page } from '@playwright/test';
import { account, logIn, type Account } from './support/accounts';
import { wp } from './support/wp';

let staff: Account;

function removeTestAreas() {
	wp('eval', 'foreach ( WC_Shipping_Zones::get_zones() as $zone ) { if ( str_starts_with( $zone["zone_name"], "E2E " ) ) { WC_Shipping_Zones::delete_zone( $zone["id"] ); } }');
}

test.beforeAll(() => {
	staff = account('areas.e2e');
	removeTestAreas();
});

test.afterAll(() => removeTestAreas());

async function openAreas(page: Page) {
	await logIn(page, staff);
	await page.getByRole('link', { name: 'Delivery areas' }).click();
	await expect(page.getByRole('heading', { name: 'Delivery areas' })).toBeVisible();
	await expect(page).toHaveTitle('Delivery areas ‹ Storewright');
}

const names = (page: Page) => page.locator('.sw-areas__items > li h2');

async function testPin(page: Page, pin: string): Promise<Locator> {
	const tester = page.getByRole('form', { name: 'Test an address' });
	await tester.getByLabel('PIN code to test').fill(pin);
	await tester.getByRole('button', { name: 'Check' }).click();
	const answer = tester.getByRole('status');
	await expect(answer).toContainText(pin);
	return answer;
}

async function showPin(editor: Locator, pin: string) {
	await editor.getByLabel('Show a PIN code on the map').fill(pin);
	await editor.getByRole('button', { name: 'Show', exact: true }).click();
	await expect(editor.getByText(new RegExp(`^Showing ${pin}, `))).toBeVisible();
}

test('the list shows areas in the order addresses are checked', async ({ page }) => {
	await openAreas(page);

	await expect(names(page)).toHaveText(['Bengaluru same-day', 'India', 'Everywhere else']);
	const sameDay = page.getByRole('listitem', { name: 'Bengaluru same-day' });
	await expect(sameDay).toContainText('560*');
	await expect(sameDay).toContainText('Within 12 km of 12.9716, 77.5946');
	await expect(sameDay).toContainText('Same-day delivery: ₹49.00');
	await expect(page.getByRole('listitem', { name: 'India' })).toContainText('Free delivery: Free on orders of ₹999.00 or more');
	await expect(page.getByRole('listitem', { name: 'Everywhere else' })).toContainText('None, so these addresses are refused at checkout');
});

test('testing a pin code answers the way the storefront does', async ({ page }) => {
	await openAreas(page);

	const near = await testPin(page, '560001');
	await expect(near).toContainText('We deliver to 560001, Bengaluru Urban.');
	await expect(near).toContainText('Decided by Bengaluru same-day.');
	await expect(near).toContainText('Same-day delivery: ₹49.00');
	await expect(page.getByRole('listitem', { name: 'Bengaluru same-day' })).toHaveClass(/is-highlighted/);

	const far = await testPin(page, '560064');
	await expect(far).toContainText('Decided by India.');
	await expect(far).toContainText('Standard delivery: ₹79.00');
});

test('staff add a radius area, move it and delete it', async ({ page }) => {
	await openAreas(page);
	await page.getByRole('button', { name: 'Add an area' }).click();
	const editor = page.getByRole('form', { name: 'New delivery area' });

	await editor.getByLabel('Name', { exact: true }).fill('E2E Jaipur centre');
	await editor.getByLabel('PIN codes', { exact: true }).fill('302001-302039');
	await editor.getByLabel('A radius around a point').check();
	await editor.getByLabel('Radius in km').fill('4');
	await showPin(editor, '302001');
	await page.getByLabel('Delivery area map').click();
	await expect(editor.getByText(/^Centre at 26\.\d{4}, 75\.\d{4}\./)).toBeVisible();
	const method = editor.getByRole('group', { name: 'Delivery method 1' });
	await method.getByLabel('Name shoppers see').fill('Jaipur same-day');
	await method.getByLabel('Charge').fill('59');
	await editor.getByRole('button', { name: 'Save' }).click();

	const jaipur = page.getByRole('listitem', { name: 'E2E Jaipur centre' });
	await expect(names(page)).toHaveText(['E2E Jaipur centre', 'Bengaluru same-day', 'India', 'Everywhere else']);
	await expect(jaipur).toContainText('302001...302039');
	await expect(jaipur).toContainText(/Within 4 km of 26\.\d{4}, 75\.\d{4}/);
	await expect(jaipur).toContainText('Jaipur same-day: ₹59.00');
	const answer = await testPin(page, '302001');
	await expect(answer).toContainText('Decided by E2E Jaipur centre.');
	await expect(answer).toContainText('Jaipur same-day: ₹59.00');

	await jaipur.getByRole('button', { name: 'Move down' }).click();
	await expect(names(page)).toHaveText(['Bengaluru same-day', 'E2E Jaipur centre', 'India', 'Everywhere else']);

	await jaipur.getByRole('button', { name: 'Edit' }).click();
	const edit = page.getByRole('form', { name: 'E2E Jaipur centre' });
	await edit.getByRole('button', { name: 'Delete area' }).click();
	await edit.getByRole('button', { name: 'Delete', exact: true }).click();
	await expect(names(page)).toHaveText(['Bengaluru same-day', 'India', 'Everywhere else']);
});

test('staff draw an area on the map and it decides delivery', async ({ page }) => {
	await openAreas(page);
	await page.getByRole('button', { name: 'Add an area' }).click();
	const editor = page.getByRole('form', { name: 'New delivery area' });

	await editor.getByLabel('Name', { exact: true }).fill('E2E Kothrud');
	await editor.getByLabel('PIN codes', { exact: true }).fill('411038');
	await editor.getByLabel('An area drawn on the map').check();
	await showPin(editor, '411038');
	const map = page.getByLabel('Delivery area map');
	const box = (await map.boundingBox())!;
	for (const [dx, dy] of [[-90, -70], [90, -70], [0, 90]]) {
		await map.click({ position: { x: box.width / 2 + dx, y: box.height / 2 + dy } });
	}
	await expect(editor.getByText(/^3 corners so far\./)).toBeVisible();
	await editor.getByRole('button', { name: 'Undo last corner' }).click();
	await editor.getByRole('button', { name: 'Save' }).click();
	await expect(editor.getByText('Click the map at least three times to draw the area.')).toBeVisible();
	await map.click({ position: { x: box.width / 2, y: box.height / 2 + 90 } });
	await editor.getByRole('group', { name: 'Delivery method 1' }).getByLabel('Charge').fill('39');
	await editor.getByRole('button', { name: 'Save' }).click();

	await expect(page.getByRole('listitem', { name: 'E2E Kothrud' })).toContainText('Inside a drawn area with 3 corners');
	const answer = await testPin(page, '411038');
	await expect(answer).toContainText('Decided by E2E Kothrud.');
	await expect(answer).toContainText('Delivery: ₹39.00');
	const outside = await testPin(page, '411001');
	await expect(outside).toContainText('Decided by India.');
});

test('a typo in the pin codes is explained and nothing is saved', async ({ page }) => {
	await openAreas(page);
	await page.getByRole('button', { name: 'Add an area' }).click();
	const editor = page.getByRole('form', { name: 'New delivery area' });

	await editor.getByLabel('Name', { exact: true }).fill('E2E Typo');
	await editor.getByLabel('PIN codes', { exact: true }).fill('560001\n56O*');
	await editor.getByRole('button', { name: 'Save' }).click();

	await expect(editor.getByText('56O* is not a PIN code, a prefix such as 560* or a range such as 560001...560099.')).toBeVisible();
	await editor.getByRole('button', { name: 'Cancel' }).click();
	await expect(page.getByRole('listitem', { name: 'E2E Typo' })).toHaveCount(0);
});

test('the areas api is closed to anyone who is not staff', async ({ request }) => {
	expect((await request.get('/wp-json/storewright/v1/areas')).status()).toBe(401);
	expect((await request.post('/wp-json/storewright/v1/areas/test', { data: { pin: '560001' } })).status()).toBe(401);
});
