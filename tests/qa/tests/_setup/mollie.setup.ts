/**
 * Internal dependencies
 */
import { test as setup } from '../../utils';
import { shopConfigGermany } from '../../resources';

// --- Mollie Germany ---

setup( 'setup:mollie;', async ( { utils } ) => {
	await utils.configureStore( shopConfigGermany );
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();
} );

// --- Setup specific Mollie API (assumes Mollie is already installed) ---

setup( 'setup:payment-api;', async ( { mollieApi } ) => {
	await mollieApi.setAdvancedSettings( {
		apiMethod: 'payment',
	} );
} );

setup( 'setup:order-api;', async ( { mollieApi } ) => {
	await mollieApi.setAdvancedSettings( {
		apiMethod: 'order',
	} );
} );
