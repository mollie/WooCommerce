/**
 * Internal dependencies
 */
import { expect, test } from '../../utils';
import {
	flatRate,
	gateways,
	guests,
	products,
	shopConfigClassic,
} from '../../resources';

test.beforeAll( async ( { utils, wooCommerceUtils } ) => {
	await utils.configureStore( {
		...shopConfigClassic,
		enableSubscriptionsPlugin: true,
	} );
	await wooCommerceUtils.createProduct( products.mollieSubscription100 );
	await utils.installAndActivateMollie();
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
		gateways.paybybank,
	];
	await utils.fillVisitorsCart( [ products.mollieSubscription100 ] );
	await classicCheckout.visit();
	await classicCheckout.fillCheckoutForm( guests.germany );
	await classicCheckout.selectShippingMethod( flatRate.settings.title );
	await expect(
		classicCheckout.paymentOptionListitems(),
		'Assert payment option list items count is correct'
	).toHaveCount( expectedGateways.length );
	for ( const gateway of expectedGateways ) {
		await classicCheckout.assertPaymentOptionLabel(
			gateway.slug,
			gateway.name,
			{ isSoftAssertion: true }
		);
	}
} );
