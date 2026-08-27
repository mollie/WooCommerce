/**
 * Internal dependencies
 */
import { test, buildMollieGatewayLabel } from '../../../utils';
import { MollieTestData } from '../../../resources';

/**
 * Checks out with the given phone number and stops as soon as the order has
 * reached Mollie's hosted checkout page - the phone number's E.164 formatting
 * is verified at integration level, so this only needs to confirm checkout
 * isn't blocked before the payment itself. No payment is completed.
 *
 * @param testData
 */
export const testReachesMollieHostedCheckout = (
	testData: MollieTestData.ShopOrder
) => {
	const { testId, testLabel, payment, customer } = testData;
	const { gateway } = payment;
	const gatewayLabel = buildMollieGatewayLabel( gateway );
	const label = testLabel ? ` ${ testLabel }` : '';

	test( `${ testId } | Phone validation - ${ gatewayLabel } - checkout with phone "${ customer.billing.phone }" reaches Mollie hosted checkout${ label }`, async ( {
		utils,
		checkout,
		mollieHostedCheckout,
		isMultistepCheckout,
		mollieApiMethod,
	} ) => {
		// exclude tests for payment methods if not available for tested API
		test.skip(
			! gateway.availableForApiMethods.includes( mollieApiMethod ),
			`Test is not eligible for ${ mollieApiMethod } API method.`
		);

		await utils.fillVisitorsCart( testData.products );

		await ( isMultistepCheckout
			? checkout.makeMultistepOrder( testData )
			: checkout.makeOrder( testData ) );

		await mollieHostedCheckout.assertUrl();
	} );
};
