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

export const testPaymentStatusOnPayForOrder = ( testData: MollieTestData.ShopOrder ) => {
	const { testId, payment } = testData;
	const { gateway } = payment;

	const orderStatus = getOrderStatusFromMollieStatus(payment.status);
	const customer = guests[gateway.country];
	const currency = gateway.currency;
	const gatewayLabel = buildMollieGatewayLabel(gateway);

	Object.assign(testData, { orderStatus, customer, currency });

	test( `${ testId } | Transaction - Pay for order - ${ gatewayLabel } - Payment status ${ payment.status } creates order with status ${ orderStatus }`, async ( {
		wooCommerceApi,
		wooCommerceUtils,
		mollieHostedCheckout,
		orderReceived,
		payForOrder,
		wooCommerceOrderEdit,
	} ) => {
		await updateCurrencyIfNeeded( wooCommerceApi, gateway.currency );

		const orderTotals = await countTotals( testData );
		payment.amount = orderTotals.order;

		const apiOrder = await wooCommerceUtils.createApiOrder( testData );

		await payForOrder.makeOrder( apiOrder.id, apiOrder.order_key, testData );

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
