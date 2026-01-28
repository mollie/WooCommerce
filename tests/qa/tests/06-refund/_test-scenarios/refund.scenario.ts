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

	const isWooCommerceFullRefund =
		! isMollieClientApiRefund && refundPercentage === 100;
	const isMollieFullRefund =
		isMollieClientApiRefund && refundPercentage === 100;
	const isWooCommercePartialRefund =
		! isMollieClientApiRefund && refundPercentage < 100;
	const isMolliePartialRefund =
		isMollieClientApiRefund && refundPercentage < 100;

	const testTitle = `${ testId } | Refund - ${ refundPart } - ${ gatewayLabel } - Via ${ refundVia }`;

	test(
		testTitle,
		async ( {
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
			let transactionId: string;
			let orderId: number;
			let refundAmount: string;
			let refundAvailable: number;
			let refundTransactionId: number;
			let orderTotal: string;
			let refunds = [];
			let refundMeta: { key: string; value: any };
			let statusAfterRefund: string;
			// Preconditions
			await test.step( 'Precondition: create WooCommerce order', async step => {
				await updateCurrencyIfNeeded( wooCommerceApi, currency );

				const orderTotals = await countTotals( testData );
				payment.amount = orderTotals.order;
				refundAvailable = orderTotals.order;
				refundAmount = getAmountPercentage(
					refundAvailable,
					testData.refundPercentage
				);

				await utils.fillVisitorsCart( testData.products );

				await classicCheckout.makeOrder( testData );

				await mollieHostedCheckout.assertUrl();
				orderId = await mollieHostedCheckout.captureOrderNumber();
				await mollieHostedCheckout.payForOrder( payment );

				await processMolliePaymentStatus(
					{ mollieHostedCheckout, orderReceived, payForOrder },
					Number( orderId ),
					testData
				);

				const order = await wooCommerceApi.getOrder( orderId );
				transactionId = order.transaction_id;
				await expect(
					transactionId,
					`Assert transaction ID ${ transactionId } is defined`
				).toBeDefined();
			} );

			// Test

			// Make refund via Mollie client API
			if ( isMollieClientApiRefund ) {
				await test.step( 'Make refund via Mollie client API', async step => {
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
				} );
			}
			// Make refund via WooCommerce admin
			else {
				await test.step( 'Make refund via WooCommerce', async step => {
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
					// Assert URL after page is reloaded
					await wooCommerceOrderEdit.assertUrl( orderId );
				} );
			}

			await test.step( 'Wait for webhook and assert refund meta ~10 min', async step => {
				// Assert via API WooCommerce Order refund status and presence of refunds
				// Delayed webhooks cause following values to arrive in ~10 minutes after refund creation
				await expect( async () => {
					const order = await wooCommerceApi.getOrder( orderId );
					orderTotal = order.total;
					statusAfterRefund = order.status;
					refunds = order.refunds;
					refundMeta = order.meta_data.find(
						( meta ) => meta.key === '_mollie_processed_refund_ids'
					);

					await expect(
						refundMeta,
						'Assert refund meta is defined'
					).toBeDefined();
					await wooCommerceOrderEdit.page.reload();
				} ).toPass( {
					intervals: [ 60_000 ],
					timeout: 14 * 60_000,
				} );

				await expect(
					refundMeta.value,
					'Assert refund meta value has length 1'
				).toHaveLength( 1 );
				refundTransactionId = refundMeta.value[ 0 ];
			} );
			
			await test.step( 'Assert refund details', async step => {
				step.skip( isMolliePartialRefund, 'Not availabe for partial refund via Mollie dashboard' );

				await expect(
					refunds,
					`Assert refunds array has length 1`
				).toHaveLength( 1 );
				const { total: refundTotal, id: refundId } = refunds[ 0 ];

				await expect(
					statusAfterRefund,
					`Assert order status is ${ expectedRefundOrderStatus }`
				).toEqual( expectedRefundOrderStatus );

				const expectedRefundTotal = `-${ Number( refundAmount ).toFixed(
					2
				) }`;
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
					netPayment:
						parseFloat( orderTotal ) - parseFloat( refundAmount ),
				} );
			} );

			// Assert order notes via WC API
			await test.step( 'Assert refund Order Notes', async step => {
				const formattedRefundAmount = parseFloat( refundAmount ).toString();
				let expectedNotes = [];
				if ( isWooCommerceFullRefund ) {
					expectedNotes = [
						`Refunded ${ currency }${ formattedRefundAmount } - Payment: ${ transactionId }, Refund: ${ refundTransactionId }`,
						`New refund ${ refundTransactionId } processed in Mollie Dashboard! Order note added, but order not updated.`,
						`Order status changed from Processing to ${ capitalizeFirst(
							expectedRefundOrderStatus
						) }.`,
					];
				}
				if ( isMollieFullRefund ) {
					expectedNotes = [
						`Mollie - ${
							gateway.name
						} payment _order_status_refunded via Mollie (${ transactionId } - test mode). You will need to manually review the payment (and adjust product stocks if you use it). Order status changed from Processing to ${ capitalizeFirst(
							expectedRefundOrderStatus
						) }.`,
						`Order status set to refunded. To return funds to the customer you will need to issue a refund through your payment gateway.`,
						`New refund ${ refundTransactionId } processed in Mollie Dashboard! Order note added, but order not updated.`,
						`Order status changed from Processing to ${ capitalizeFirst(
							expectedRefundOrderStatus
						) }.`,
					];
				}
				if ( isWooCommercePartialRefund ) {
					expectedNotes = [
						`New refund ${ refundTransactionId } processed in Mollie Dashboard! Order note added, but order not updated.`,
						`Refunded ${ currency }${ formattedRefundAmount } - Payment: ${ transactionId }, Refund: ${ refundTransactionId }`,
					];
				}
				if ( isMolliePartialRefund ) {
					expectedNotes = [
						`New refund ${ refundTransactionId } processed in Mollie Dashboard! Order note added, but order not updated.`,
					];
				}

				await assertOrderNotes( wooCommerceApi, orderId, expectedNotes );
			} );
		}
	);
};
