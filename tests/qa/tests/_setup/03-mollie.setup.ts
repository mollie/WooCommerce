/**
 * Internal dependencies
 */
import { test as setup } from '../../utils';
import {
	mollieApiKeys,
	molliePlugin,
	products,
} from '../../resources';

// =============================================================
// API method selectors (orthogonal to the states below)
// =============================================================

setup( 'setup:payment-api;', async ( { mollieApi } ) => {
	await mollieApi.setAdvancedSettings( { apiMethod: 'payment' } );
} );

setup( 'setup:order-api;', async ( { mollieApi } ) => {
	await mollieApi.setAdvancedSettings( { apiMethod: 'order' } );
} );

// =============================================================
// Mollie states (one per distinct precondition; shared by many specs)
// =============================================================

// cleaned + uninstalled  ->  01-plugin-foundation
setup( 'setup:mollie:uninstalled;', async ( { requestUtils, mollieApi } ) => {
	if ( await requestUtils.isPluginInstalled( molliePlugin.slug ) ) {
		await requestUtils.activatePlugin( molliePlugin.slug );
		await mollieApi.setMollieApiKeys( mollieApiKeys.default );
		await mollieApi.cleanMollieDb();
		await requestUtils.deactivatePlugin( molliePlugin.slug );
		await requestUtils.deletePlugin( molliePlugin.slug, true );
	}
} );

// installed, not connected to the Mollie API  ->  03 api-keys
setup( 'setup:mollie:disconnected;', async ( { requestUtils, mollieApi, utils } ) => {
	if ( await requestUtils.isPluginInstalled( molliePlugin.slug ) ) {
		await requestUtils.activatePlugin( molliePlugin.slug );
		await mollieApi.setMollieApiKeys( mollieApiKeys.default );
		await mollieApi.cleanMollieDb();
	} else {
		await utils.installAndActivateMollie();
	}
} );

// installed + cleaned + reconnected (layout-agnostic; layout comes from the
// composed setup:checkout:block/classic; task)  ->  block/classic states,
// merchant-setup, gateway
setup( 'setup:mollie:reconnected;', async ( { utils } ) => {
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();
} );

// =============================================================
// Specific states
// =============================================================

// Subscriptions: WC Subscriptions on + product. Layout comes from the
// composed setup:checkout:classic; task.  ->  07 order, 07 renewal, 07 ui
setup( 'setup:mollie:subscription;', async ( { utils, wooCommerceUtils } ) => {
	setup.setTimeout( 2 * 60_000 );
	await utils.configureStore( { enableSubscriptionsPlugin: true } );
	await wooCommerceUtils.createProduct( products.mollieSubscription100 );
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();
} );

// Credit card with Mollie Components disabled. Layout comes from the composed
// setup:checkout:block/classic; task.  ->  05 *-credit-card-disabled
setup( 'setup:mollie:card-disabled;', async ( { utils, mollieApi } ) => {
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();
	await mollieApi.updateMollieGateway( 'creditcard', {
		mollie_components_enabled: 'no',
	} );
} );