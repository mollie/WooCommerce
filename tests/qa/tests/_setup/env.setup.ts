/**
 * Internal dependencies
 */
import { createStorageStates, resetEnvironment, test as setup } from '../../utils';
import { taxSettings } from '../../resources';

// --- Reset env ---

setup.describe( 'env:reset;', async () => {
	setup( 'Setup: Reset Environment', async () => {
		await resetEnvironment();
	} );

	setup( 'Setup: Create storage state', async () => {
		await createStorageStates();
	} );
} );

// --- Checkout layout ---

setup( 'setup:checkout:block;', async ( { utils } ) => {
	await utils.configureStore( { enableClassicPages: false } );
} );

setup( 'setup:checkout:classic;', async ( { utils } ) => {
	await utils.configureStore( { enableClassicPages: true } );
} );

// --- Tax ---

setup( 'setup:tax:inc;', async ( { utils } ) => {
	await utils.configureStore( { taxes: taxSettings.including } );
} );

setup( 'setup:tax:exc;', async ( { utils } ) => {
	await utils.configureStore( { taxes: taxSettings.excluding } );
} );