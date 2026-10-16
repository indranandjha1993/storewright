import { defineConfig, devices } from '@playwright/test';

const ci = !!process.env.CI;
const local = ci ? {} : { channel: 'chrome', headless: false, launchOptions: { slowMo: Number(process.env.E2E_SLOWMO ?? 150) } };

export default defineConfig({
	testDir: 'tests/e2e',
	outputDir: 'tests/e2e/results',
	fullyParallel: false,
	workers: ci ? undefined : 1,
	retries: ci ? 1 : 0,
	reporter: [['list'], ['html', { outputFolder: 'tests/e2e/report', open: 'never' }]],
	use: {
		baseURL: process.env.STORE_URL ?? `http://localhost:${process.env.WEB_PORT ?? '8600'}`,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{ name: 'mobile', use: { ...devices['Pixel 7'], ...local } },
		{ name: 'desktop', use: { ...devices['Desktop Chrome'], ...local } },
	],
});
