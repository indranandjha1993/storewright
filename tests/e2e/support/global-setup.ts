import { createAccounts } from './accounts';
import { wp } from './wp';

export default function globalSetup() {
	wp('storewright', 'seed');
	process.env.E2E_PASSWORD = createAccounts();
}
