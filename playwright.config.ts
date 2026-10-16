import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
	testDir: 'tests/e2e',
	outputDir: 'tests/e2e/results',
	fullyParallel: false,
	retries: process.env.CI ? 1 : 0,
	reporter: [['list'], ['html', { outputFolder: 'tests/e2e/report', open: 'never' }]],
	use: {
		baseURL: process.env.STORE_URL ?? `http://localhost:${process.env.WEB_PORT ?? '8600'}`,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{ name: 'mobile', use: { ...devices['Pixel 7'] } },
		{ name: 'desktop', use: { ...devices['Desktop Chrome'] } },
	],
});
