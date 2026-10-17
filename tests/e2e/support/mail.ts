import { expect, type Page } from '@playwright/test';

const mailpit = `http://localhost:${process.env.MAIL_PORT ?? '8601'}/api/v1`;

export async function mailFor(page: Page, query: string): Promise<{ subject: string; html: string }> {
	let id = '';
	await expect
		.poll(async () => {
			const found = await (await page.request.get(`${mailpit}/search?query=${encodeURIComponent(query)}`)).json();
			id = found.messages?.[0]?.ID ?? '';
			return id;
		}, { timeout: 20_000 })
		.not.toBe('');
	const message = await (await page.request.get(`${mailpit}/message/${id}`)).json();
	return { subject: message.Subject, html: message.HTML };
}
