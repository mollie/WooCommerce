/**
 * Internal dependencies
 */
import { expect, test } from '../../utils';
import {
	flatRate,
	gateways,
	guests,
	products,
	shopSettings,
} from '../../resources';

test.beforeAll( async ( { utils, wooCommerceUtils } ) => {
	await utils.configureStore( {
		settings: {
			general: shopSettings.germany.general,
		},
		classicPages: true,
		enableSubscriptionsPlugin: true,
	} );
	await wooCommerceUtils.createProduct( products.mollieSubscription100 );
	await utils.installActivateMollie();
	await utils.cleanReconnectMollie();
} );

test( `C3348 | Validate that only the correct payment methods (that supports a first mandate) are displayed for recurring products `, async ( {
	utils,
	classicCheckout,
} ) => {
	const expectedGateways = [
		gateways.ideal,
		gateways.creditcard,
		gateways.bancontact,
		gateways.eps,
		gateways.kbc,
		gateways.belfius,
		gateways.trustly,
		gateways.paybybank,
	];
	await utils.fillVisitorsCart( [ products.mollieSubscription100 ] );
	await classicCheckout.visit();
	await classicCheckout.fillCheckoutForm( guests.germany );
	await classicCheckout.selectShippingMethod( flatRate.settings.title );
	await expect( classicCheckout.paymentOptionListitems() ).toHaveCount(
		expectedGateways.length
	);
	for ( const gateway of expectedGateways ) {
		await expect(
			classicCheckout.paymentOption( gateway.name )
		).toBeVisible();
	}
} );
