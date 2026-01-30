/**
 * Internal dependencies
 */
import { test as setup } from '../../utils';
import { shopConfigGermany } from '../../resources';

setup( 'Setup Mollie', async ( { utils, wooCommerceApi }, testInfo ) => {
	if ( ! [ 'setup-refund', 'refund' ].includes( testInfo.project.name ) ) {
		return;
	}
	
	await utils.configureStore( {
		...shopConfigGermany,
		enableClassicPages: true,
	} );
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();
	await wooCommerceApi.deleteAllOrders();
} );
