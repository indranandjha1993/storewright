import { defineConfig, devices } from '@playwright/test';

const ci = !!process.env.CI;
// The admin specs change store settings that the storefront specs read, so they run after them.
const adminSpecs = /\/(account|admin|areas|brand|operator|permissions)\.spec\.ts$/;
const local = ci ? {} : { channel: 'chrome', headless: false, launchOptions: { slowMo: Number(process.env.E2E_SLOWMO ?? 150) } };

export default defineConfig({
	testDir: 'tests/e2e',
	globalSetup: './tests/e2e/support/global-setup.ts',
	outputDir: 'tests/e2e/results',
	fullyParallel: false,
	timeout: 60_000,
	workers: ci ? undefined : 1,
	retries: ci ? 1 : 0,
	failOnFlakyTests: ci,
	reporter: [['list'], ['html', { outputFolder: 'tests/e2e/report', open: 'never' }]],
	use: {
		baseURL: process.env.STORE_URL ?? `http://localhost:${process.env.WEB_PORT ?? '8600'}`,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{ name: 'mobile', use: { ...devices['Pixel 7'], ...local }, testIgnore: adminSpecs },
		{ name: 'desktop', use: { ...devices['Desktop Chrome'], ...local }, testIgnore: adminSpecs },
		{ name: 'admin', use: { ...devices['Desktop Chrome'], ...local }, testMatch: adminSpecs, dependencies: ['mobile', 'desktop'] },
	],
});
