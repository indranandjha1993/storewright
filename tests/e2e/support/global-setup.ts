import { createAccounts } from './accounts';

export default function globalSetup() {
	process.env.E2E_PASSWORD = createAccounts();
}
