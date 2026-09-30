/**
 * Internal dependencies
 */
import { test as setup } from '../../utils';
import { setupWooCommerce } from '../../utils/helpers/';
import { taxSettings } from '../../resources';

setup.describe( 'setup:wc;', async () => {
	await setupWooCommerce();
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
