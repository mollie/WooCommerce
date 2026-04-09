/**
 * Internal dependencies
 */
import { createStorageStates, resetEnvironment, test as setup, setupWooCommerce } from '../../utils';
import {
	taxSettings,
	mollieApiKeys,
	shopSettings,
} from '../../resources';

// --- Reset env ---

setup.describe( 'env:reset;', async () => {
	setup( 'Setup: Reset Environment', async () => {
		await resetEnvironment();
	} );

	setup( 'Setup: Create storage state', async () => {
		await createStorageStates();
	} );

	await setupWooCommerce();
} );

// --- Mollie Germany ---

setup( 'setup:mollie:germany;', async ( { utils, mollieApi } ) => {
	await utils.configureStore( shopSettings.germany.general );
	await utils.installAndActivateMollie();
	await mollieApi.setMollieApiKeys( mollieApiKeys.default );
	await mollieApi.cleanMollieDb();
	await mollieApi.setMollieApiKeys( mollieApiKeys.default );
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