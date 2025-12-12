/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { products, shopSettings } from '../../resources';

test.beforeAll( async ( { utils, wooCommerceUtils } ) => {
	await utils.configureStore( {
		settings: {
			general: shopSettings.germany.general,
		},
		enableClassicPages: true,
		enableSubscriptionsPlugin: true,
	} );
	await wooCommerceUtils.createProduct( products.mollieSubscription100 );
	await utils.installActivateMollie();
	await utils.cleanReconnectMollie();
} );

// TODO: implement when automation is approved by client (estimation sent in Nov 2025)
