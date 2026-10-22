/**
 * Internal dependencies
 */
import { test as setup } from '../../utils';
import { setupWooCommerce } from '../../utils/helpers/';
import {
	taxSettings,
	customerBillingCountryOnly,
	products,
	negativeLineItemPlugin,
	negative001FeePlugin,
	thirdPartyFeesPlugin,
} from '../../resources';

setup.describe( 'setup:wc;', async () => {
	await setupWooCommerce();
} );

// --- Manual Express tests (8.2.0) ---

// Recreated so a checkout as this customer cannot leave more address data behind
setup( 'setup:customer:billing-country-only;', async ( { wooCommerceUtils } ) => {
	await wooCommerceUtils.deleteCustomer( customerBillingCountryOnly );
	await wooCommerceUtils.createCustomer( customerBillingCountryOnly );
} );

// Unlike setup:mollie:subscription; this keeps the Mollie settings (no reset, test mode untouched)
setup( 'setup:subscription-product;', async ( { utils } ) => {
	await utils.configureStore( {
		enableSubscriptionsPlugin: true,
		products: [ products.mollieSubscription100 ],
	} );
} );

// Inactive on purpose: each case activates its helper and deactivates it after
setup( 'setup:express-helper-plugins;', async ( { requestUtils, plugins } ) => {
	for ( const plugin of [ negativeLineItemPlugin, negative001FeePlugin, thirdPartyFeesPlugin ] ) {
		if ( ! ( await requestUtils.isPluginInstalled( plugin.slug ) ) ) {
			await plugins.installPluginFromFile( plugin.zipFilePath );
		}
		await requestUtils.deactivatePlugin( plugin.slug );
	}
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
