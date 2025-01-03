/**
 * Internal dependencies
 */
import { test as setup } from '../../utils';

setup( 'Setup and reconnect Mollie', async ( { utils } ) => {
	await utils.installActivateMollie();
	await utils.cleanReconnectMollie();
} );
