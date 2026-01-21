/**
 * External dependencies
 */
import { capitalizeFirst, countTotals, expect, formatMoney, getAmountPercentage } from '@inpsyde/playwright-utils/build';
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

export const testRefund = ( testData: MollieTestData.ShopRefund ) => {
	const { testId, payment, currency, refundPercentage, isMollieClientApiRefund } = testData;
	const { gateway } = payment;

	const orderStatus = getOrderStatusFromMollieStatus(payment.status);
	const customer = guests[gateway.country];
	const gatewayLabel = buildMollieGatewayLabel(gateway);

	Object.assign(testData, { orderStatus, customer, currency });

	const refundPart = refundPercentage === 100
		? 'Full'
		: 'Partial';

	const refundVia = isMollieClientApiRefund
		? 'Mollie Dashboard'
		: 'WooCommerce';

	const testTitle = `${ testId } | Refund - ${ refundPart } - ${ gatewayLabel } - Via ${ refundVia }`;

	test( testTitle, async ( {
		wooCommerceApi,
		utils,
		classicCheckout,
		mollieHostedCheckout,
		mollieClientApi,
		orderReceived,
		payForOrder,
		wooCommerceOrderEdit,
	} ) => {
		test.setTimeout( 1.5 * 60_000 );
		await updateCurrencyIfNeeded( wooCommerceApi, currency );

		const orderTotals = await countTotals( testData );
		payment.amount = orderTotals.order;
		const refundAvailable = orderTotals.order;
		const refundAmount = getAmountPercentage(
			refundAvailable,
			testData.refundPercentage
		);

		await utils.fillVisitorsCart( testData.products );

		await classicCheckout.makeOrder( testData );

		const orderId = await mollieHostedCheckout.pay( payment );

		await processMolliePaymentStatus(
			{ mollieHostedCheckout, orderReceived, payForOrder },
			Number( orderId ),
			testData
		);

		const { transaction_id: transactionId } =
			await wooCommerceApi.getOrder( orderId );

		// Test

		// Make refund via Mollie client API
		if( isMollieClientApiRefund ) {
			const idempotencyKey = `${ transactionId }-refund-${ orderId }`;
			await mollieClientApi.refunds.create( {
				paymentId: transactionId,
				idempotencyKey: idempotencyKey,
				refundRequest: {
					amount: {
						value: refundAmount,
						currency
					},
					description: testTitle,
					metadata: {},
				},
			} );
			await wooCommerceOrderEdit.visit( orderId );
		}
		// Make refund via WooCommerce admin
		else {
			await wooCommerceOrderEdit.visit( orderId );
			await wooCommerceOrderEdit.refundButton().click();

			// Assertions before refund
			await expect(
				wooCommerceOrderEdit.restockRefundedItemsCheckbox()
			).toBeVisible();
			await expect(
				wooCommerceOrderEdit.totalAmountAlreadyRefunded()
			).toHaveText( `-${ formatMoney( 0, testData.currency ) }` );
			await expect(
				wooCommerceOrderEdit.totalAvailableToRefund()
			).toHaveText(
				formatMoney( Number( refundAvailable ), testData.currency )
			);
			await wooCommerceOrderEdit.makeRefund( gateway.name, refundAmount );
		}

		// Assert URL after page is reloaded
		await wooCommerceOrderEdit.assertUrl( orderId );

		// Assert via API WooCommerce Order refund status and presence of refunds
		const { meta_data, status, refunds, total } =
			await wooCommerceApi.getOrder( orderId );

		await expect( transactionId, `Transaction ID ${ transactionId }` ).toBeDefined();
		await expect( status ).toEqual( testData.refundOrderStatus );
		await expect( refunds ).toHaveLength( 1 );
		const { total: refundTotal, id: refundId  } = refunds[ 0 ];
		await expect( refundTotal ).toEqual(
			`-${ Number( refundAmount ).toFixed( 2 ) }`
		);
		
		// Delayed webhooks cause following values to arrive in 9-11 minutes after refund creation
		// const refundMeta = meta_data.find(
		// 	meta => meta.key === '_mollie_processed_refund_ids'
		// );
		// await expect( refundMeta ).toBeDefined();
		// await expect( refundMeta.value ).toHaveLength( 1 );
		// const refundTransactionId = refundMeta.value[ 0 ];

		// Assert on OrderEdit page that WooCommerce and PayPal refund fields are displayed and have expected values
		await wooCommerceOrderEdit.assertRefundData( {
			currency: testData.currency,
			orderStatus: capitalizeFirst( testData.refundOrderStatus ),
			refundId,
			refundAmount: Number( refundAmount ),
			refundTotal: Number( refundAmount ),
			netPayment:
				parseFloat( total ) - parseFloat( refundAmount ),
		} );

		// Assert order notes via WC API
		const orderNotes = await wooCommerceApi.getOrderNotes( orderId );
		const notes = orderNotes.map( orderNote => orderNote.note );
		const formattedRefundAmount = parseFloat( refundAmount ).toString();

		const expectedNotes = [
			`Refunded ${ currency }${ formattedRefundAmount } - Payment: ${ transactionId }, Refund`,//: ${ refundTransactionId }`,
			`${ gateway.slug } payment started (${ transactionId } - test mode).`,
			`Payment via ${ gateway.name } (${ transactionId }).`,
			`Order completed using Mollie - ${ gateway.name } payment (${ transactionId } - test mode).`,
		];

		if ( refundPercentage === 100 ) {
			expectedNotes.unshift(`Order status changed from Processing to Refunded.`);
		}

		for ( const expected of expectedNotes ) {
			const matches = notes.filter( note => note.includes( expected ) );
			await expect.soft( matches, `Note "${ expected }" should appear exactly once` ).toHaveLength( 1 );
			await expect.soft( matches[0] ).toContain( expected ); // This gives the diff on mismatch
		}
	} );
};
