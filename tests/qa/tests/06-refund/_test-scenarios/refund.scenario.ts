/**
 * External dependencies
 */
import {
	capitalizeFirst,
	countTotals,
	expect,
	formatMoney,
	getAmountPercentage,
} from '@inpsyde/playwright-utils/build';
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

export const testRefund = ( testData: MollieTestData.ShopRefund ) => {
	const {
		testId,
		payment,
		currency,
		refundPercentage,
		isMollieClientApiRefund,
		refundOrderStatus: expectedRefundOrderStatus,
	} = testData;
	const { gateway } = payment;
	const orderStatus = getOrderStatusFromMollieStatus( payment.status );
	const customer = guests[ gateway.country ];
	const gatewayLabel = buildMollieGatewayLabel( gateway );
	Object.assign( testData, { orderStatus, customer, currency } );

	const refundPart = refundPercentage === 100 ? 'Full' : 'Partial';

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
			test.setTimeout( 16 * 60_000 );
			// Preconditions
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

			await mollieHostedCheckout.assertUrl();
			const orderId = await mollieHostedCheckout.captureOrderNumber();
			await mollieHostedCheckout.payForOrder( payment );

			await processMolliePaymentStatus(
				{ mollieHostedCheckout, orderReceived, payForOrder },
				Number( orderId ),
				testData
			);

			const { transaction_id: transactionId } =
				await wooCommerceApi.getOrder( orderId );

			// Test
			await expect(
				transactionId,
				`Assert transaction ID ${ transactionId } is defined`
			).toBeDefined();

			// Make refund via Mollie client API
			if ( isMollieClientApiRefund ) {
				const idempotencyKey = `${ transactionId }-refund-${ orderId }`;
				await mollieClientApi.refunds.create( {
					paymentId: transactionId,
					idempotencyKey,
					refundRequest: {
						amount: {
							value: refundAmount,
							currency,
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
					wooCommerceOrderEdit.restockRefundedItemsCheckbox(),
					'Assert restock refunded items checkbox is visible'
				).toBeVisible();
				await expect(
					wooCommerceOrderEdit.totalAmountAlreadyRefunded(),
					'Assert total amount already refunded is 0'
				).toHaveText( `-${ formatMoney( 0, currency ) }` );
				await expect(
					wooCommerceOrderEdit.totalAvailableToRefund(),
					'Assert total available to refund is correct'
				).toHaveText(
					formatMoney( Number( refundAvailable ), currency )
				);
				await wooCommerceOrderEdit.makeRefund(
					gateway.name,
					refundAmount
				);
			}

			// Assert URL after page is reloaded
			await wooCommerceOrderEdit.assertUrl( orderId );

			// Assert via API WooCommerce Order refund status and presence of refunds
			let orderTotal: string;
			let refunds = [];
			let refundMeta: { key: string; value: any };
			let statusAfterRefund: string;
			
			// Delayed webhooks cause following values to arrive in ~10 minutes after refund creation
			await expect( async () => {
				const order = await wooCommerceApi.getOrder( orderId );
				orderTotal = order.total;
				statusAfterRefund = order.status;
				refunds = order.refunds;
				refundMeta = order.meta_data.find(
					meta => meta.key === '_mollie_processed_refund_ids'
				);
				await expect(
					refunds,
					`Assert refunds array has length 1`
				).toHaveLength( 1 );
				await expect( refundMeta, 'Assert refund meta is defined' ).toBeDefined();
				await wooCommerceOrderEdit.page.reload();
			} ).toPass( {
				intervals: [ 60_000 ],
				timeout: 14 * 60_000,
			} );

			const { total: refundTotal, id: refundId } = refunds[ 0 ];

			await expect( refundMeta.value, 'Assert refund meta value has length 1' ).toHaveLength( 1 );
			const refundTransactionId = refundMeta.value[ 0 ];

			await expect(
				statusAfterRefund,
				`Assert order status is ${ expectedRefundOrderStatus }`
			).toEqual( expectedRefundOrderStatus );
			
			const expectedRefundTotal = `-${ Number( refundAmount ).toFixed( 2 ) }`;
			await expect(
				refundTotal,
				`Assert refund total is ${ expectedRefundTotal }`
			).toEqual( expectedRefundTotal );

			// Assert on OrderEdit page that WooCommerce and PayPal refund fields are displayed and have expected values
			await wooCommerceOrderEdit.assertRefundData( {
				currency,
				orderStatus: capitalizeFirst( expectedRefundOrderStatus ),
				refundId,
				refundAmount: Number( refundAmount ),
				refundTotal: Number( refundAmount ),
				netPayment: parseFloat( orderTotal ) - parseFloat( refundAmount ),
			} );

			// Assert order notes via WC API
			const formattedRefundAmount = parseFloat( refundAmount ).toString();
			let expectedNotes = [];
			// Expected notes for Full via WooCommerce:
			if(
				! isMollieClientApiRefund &&
				refundPercentage === 100
			) {
				expectedNotes = [
					`Refunded ${ currency }${ formattedRefundAmount } - Payment: ${ transactionId }, Refund: ${ refundTransactionId }`,
					`New refund ${ refundTransactionId } processed in Mollie Dashboard! Order note added, but order not updated.`,
				];
			}
			// Expected notes for Full via Mollie:
			else if(
				isMollieClientApiRefund &&
				refundPercentage === 100
			) {
				expectedNotes = [
					`Mollie - ${ gateway.name } payment _order_status_refunded via Mollie (${ transactionId } - test mode). You will need to manually review the payment (and adjust product stocks if you use it). Order status changed from Processing to ${ capitalizeFirst( expectedRefundOrderStatus ) }.`,
					`Order status set to refunded. To return funds to the customer you will need to issue a refund through your payment gateway.`,
					`New refund ${ refundTransactionId } processed in Mollie Dashboard! Order note added, but order not updated.`,
				];
			}
			// Expected notes for Partial via WooCommerce:
			else if(
				! isMollieClientApiRefund &&
				refundPercentage < 100
			) {
				expectedNotes = [
					`New refund ${ refundTransactionId } processed in Mollie Dashboard! Order note added, but order not updated.`,
					`Refunded ${ currency }${ formattedRefundAmount } - Payment: ${ transactionId }, Refund: ${ refundTransactionId }`,
				];
			}
			// Expected notes for Partial via Mollie:
			else if(
				isMollieClientApiRefund &&
				refundPercentage < 100
			) {
				expectedNotes = [
					`New refund ${ refundTransactionId } processed in Mollie Dashboard! Order note added, but order not updated.`,
				];
			}

			if ( refundPercentage === 100 ) {
				expectedNotes.unshift(
					`Order status changed from Processing to ${ capitalizeFirst( expectedRefundOrderStatus ) }.`
				);
			}

			await assertOrderNotes( wooCommerceApi, orderId, expectedNotes );
	} );
};
