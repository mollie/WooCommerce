/**
 * Internal dependencies
 */
import { test as setup } from '../../utils';
import {
	shopConfigDefault,
	shopConfigNetherlands,
	mollieApiKeys,
	products,
	ShopConfig,
} from '../../resources';

// Checkout layout is left out on purpose: it comes from the composed
// setup:checkout:block/classic; task (02-woocommerce), which runs first.
const withoutLayout = ( {
	enableClassicPages,
	...config
}: ShopConfig ): ShopConfig => config;

// =============================================================
// Mollie states (one per distinct precondition, shared by many specs)
// =============================================================

// --- Mollie Germany (installed + cleaned + reconnected) ---

setup( 'setup:mollie;', async ( { utils } ) => {
	await utils.configureStore( withoutLayout( shopConfigDefault ) );
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();
} );

// --- Mollie Netherlands (Billink) ---

setup( 'setup:mollie:nl;', async ( { utils } ) => {
	await utils.configureStore( withoutLayout( shopConfigNetherlands ) );
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie( mollieApiKeys.nl );
} );

// --- Credit card with Mollie Components disabled ---

setup( 'setup:mollie:card-disabled;', async ( { utils, mollieApi } ) => {
	await utils.configureStore( withoutLayout( shopConfigDefault ) );
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();
	await mollieApi.updateMollieGateway( 'creditcard', {
		mollie_components_enabled: 'no',
	} );
} );

// --- WC Subscriptions active + subscription product ---

setup( 'setup:mollie:subscription;', async ( { utils } ) => {
	setup.setTimeout( 2 * 60_000 );
	await utils.configureStore( {
		...withoutLayout( shopConfigDefault ),
		enableSubscriptionsPlugin: true,
		products: [ products.mollieSubscription100 ],
	} );
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();
} );

// =============================================================
// Specific Mollie API (assumes Mollie is already installed)
// =============================================================

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
