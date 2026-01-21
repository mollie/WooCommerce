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
} from '../../../utils';
import { MollieTestData, guests } from 'resources';

export const testPaymentStatusOnPayForOrder = ( testData: MollieTestData.ShopOrder ) => {
	const { testId, payment } = testData;
	const { gateway } = payment;

	const orderStatus = getOrderStatusFromMollieStatus( payment.status );
	const customer = guests[ gateway.country ];
	const currency = gateway.currency;
	const gatewayLabel = buildMollieGatewayLabel( gateway );

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
		
		const { transaction_id: transactionId } =
			await wooCommerceApi.getOrder( orderId );
		await expect( transactionId, `Transaction ID ${ transactionId }` ).toBeDefined();

		await wooCommerceOrderEdit.visit( orderId );
		await wooCommerceOrderEdit.assertOrderDetails(
			testData,
			transactionId,
		);

		// Assert order notes via WC API
		const orderNotes = await wooCommerceApi.getOrderNotes(orderId);
		const notes = orderNotes.map(n => n.note);

		const expectedNotes = [
			`${gateway.slug} payment started (${transactionId} - test mode).`,
			`Payment via ${gateway.name} (${transactionId}).`,
			`Order completed using Mollie - ${gateway.name} payment (${transactionId} - test mode).`,
		];

		for (const expected of expectedNotes) {
			const matches = notes.filter(note => note.includes(expected));
			expect(matches, `Note "${expected}" should appear exactly once`).toHaveLength(1);
			expect(matches[0]).toContain(expected); // This gives the diff on mismatch
		}
	} );
};
