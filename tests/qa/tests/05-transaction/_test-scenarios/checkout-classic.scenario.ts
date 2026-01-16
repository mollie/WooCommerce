/**
 * External dependencies
 */
import { countTotals } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import {
	test,
	buildMollieGatewayLabel,
	processMolliePaymentStatus,
	updateCurrencyIfNeeded,
	getOrderStatusFromMollieStatus,
} from '../../../utils';
import { MollieTestData, guests } from 'resources';

const isMultistepCheckout = process.env.IS_MULTISTEP_CHECKOUT === 'true';

export const testPaymentStatusOnClassicCheckout = ( testData: MollieTestData.ShopOrder ) => {
	const { testId, payment } = testData;
	const { gateway } = payment;

	const orderStatus = getOrderStatusFromMollieStatus(payment.status);
	const customer = guests[gateway.country];
	const currency = gateway.currency;
	const gatewayLabel = buildMollieGatewayLabel(gateway);
	const multistepLabel = isMultistepCheckout ? ' - Multistep' : '';

	Object.assign(testData, { orderStatus, customer, currency });

	test( `${ testId } | Transaction${ multistepLabel } - Classic checkout - ${ gatewayLabel } - Payment status ${ payment.status } creates order with status ${ orderStatus }`, async ( {
		wooCommerceApi,
		utils,
		classicCheckout,
		mollieHostedCheckout,
		orderReceived,
		payForOrder,
		wooCommerceOrderEdit,
	} ) => {
		await updateCurrencyIfNeeded( wooCommerceApi, currency );

		const orderTotals = await countTotals( testData );
		payment.amount = orderTotals.order;

		await utils.fillVisitorsCart( testData.products );

		await ( isMultistepCheckout
			? classicCheckout.makeMultistepOrder( testData )
			: classicCheckout.makeOrder( testData ) );

		const orderId = await mollieHostedCheckout.pay( payment );

		await processMolliePaymentStatus(
			{ mollieHostedCheckout, orderReceived, payForOrder },
			Number( orderId ),
			testData
		);

		await wooCommerceOrderEdit.assertOrderDetails(
			Number( orderId ),
			testData
		);
	} );
};
