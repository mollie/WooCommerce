/**
 * Internal dependencies
 */
import { createStorageStates, resetEnvironment, test as setup } from '../../utils';

// --- Reset env ---

setup.describe( 'env:reset;', async () => {
	setup( 'Setup: Reset Environment', async () => {
		await resetEnvironment();
	} );

	setup( 'Setup: Create storage state', async () => {
		await createStorageStates();
	} );
} );
