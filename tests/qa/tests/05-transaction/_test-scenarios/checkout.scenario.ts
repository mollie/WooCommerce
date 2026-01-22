/**
 * External dependencies
 */
import { countTotals, expect } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import {
	test,
	buildMollieGatewayLabel,
	processMolliePaymentStatus,
	updateCurrencyIfNeeded,
	getOrderStatusFromMollieStatus,
	assertOrderNotes,
} from '../../../utils';
import { MollieTestData, guests } from '../../../resources';

const isMultistepCheckout = process.env.IS_MULTISTEP_CHECKOUT === 'true';

export const testPaymentStatusOnCheckout = (
	testData: MollieTestData.ShopOrder
) => {
	const { testId, payment } = testData;
	const { gateway } = payment;

	const orderStatus = getOrderStatusFromMollieStatus( payment.status );
	const customer = guests[ gateway.country ];
	const currency = gateway.currency;
	const gatewayLabel = buildMollieGatewayLabel( gateway );
	const multistepLabel = isMultistepCheckout ? ' - Multistep' : '';

	Object.assign( testData, { orderStatus, customer, currency } );

	test( `${ testId } | Transaction${ multistepLabel } - Checkout - ${ gatewayLabel } - Payment status ${ payment.status } creates order with status ${ orderStatus }`, async ( {
		wooCommerceApi,
		utils,
		checkout,
		mollieHostedCheckout,
		orderReceived,
		payForOrder,
		wooCommerceOrderEdit,
	} ) => {
		await updateCurrencyIfNeeded( wooCommerceApi, gateway.currency );

		const orderTotals = await countTotals( testData );
		payment.amount = orderTotals.order;

		await utils.fillVisitorsCart( testData.products );

		await ( isMultistepCheckout
			? checkout.makeMultistepOrder( testData )
			: checkout.makeOrder( testData ) );

		await mollieHostedCheckout.assertUrl();
		const orderId = await mollieHostedCheckout.captureOrderNumber();
		await mollieHostedCheckout.payForOrder( payment );

		await processMolliePaymentStatus(
			{ mollieHostedCheckout, orderReceived, payForOrder },
			Number( orderId ),
			testData
		);

		const { transaction_id: transactionId } = await wooCommerceApi.getOrder(
			orderId
		);
		await expect(
			transactionId,
			`Assert transaction ID ${ transactionId } is defined`
		).toBeDefined();

		await wooCommerceOrderEdit.visit( orderId );
		await wooCommerceOrderEdit.assertOrderDetails(
			testData,
			transactionId
		);

		// Assert order notes via WC API
		const expectedNotes = [
			`${ gateway.slug } payment started (${ transactionId } - test mode).`,
			`Payment via ${ gateway.name } (${ transactionId }).`,
			`Order completed using Mollie - ${ gateway.name } payment (${ transactionId } - test mode).`,
		];
		await assertOrderNotes( wooCommerceApi, orderId, expectedNotes );
	} );
};
