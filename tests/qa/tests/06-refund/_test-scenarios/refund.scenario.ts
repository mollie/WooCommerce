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
	const customer = guests[ gateway.country ];
	Object.assign( testData, { customer, currency } );

	const gatewayLabel = buildMollieGatewayLabel( gateway );

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
		async (
			{
				wooCommerceApi,
				utils,
				checkout,
				mollieHostedCheckout,
				mollieClientApi,
				orderReceived,
				payForOrder,
				wooCommerceOrderEdit,
				mollieApiMethod,
			},
			testInfo
		) => {
			// exclude tests for payment methods if not available for tested API
			test.skip(
				! gateway.availableForApiMethods.includes( mollieApiMethod ),
				`Test is not eligible for ${ mollieApiMethod } API method.`
			);

			// Sets the default orderStatus based on API method, if specific is not set
			if ( ! testData.orderStatus ) {
				testData.orderStatus = await getOrderStatusFromMollieStatus(
					payment.status,
					mollieApiMethod
				);
			}

			// Manual-capture gateways add a ship/capture precondition on top of the
			// existing checkout + refund-webhook waits, so give them more headroom.
			test.setTimeout(
				gateway.isCaptureRequired ? 40 * 60_000 : 30 * 60_000
			);

			// Manual-capture gateways (e.g. Klarna) sit authorized-but-uncaptured at
			// Mollie after checkout - refunding now would route through Mollie's
			// cancel-on-authorized logic instead of a genuine refund. Completing the
			// WC order triggers shipAndCaptureOrderAtMollie() (src/Payment/PaymentModule.php),
			// which ships the order lines at Mollie synchronously within that same
			// request - but that PHP method catches ApiException and only logs it
			// (no re-throw), and the WC status is already committed to "completed"
			// *before* the hook even runs (WC's save() persists the status, then
			// fires woocommerce_order_status_completed). So polling the WC order's
			// own status is a tautology - it can never detect a failed/skipped
			// capture. Poll for the specific success order note the PHP method adds
			// instead, which is the only real signal capture actually happened.
			const captureOrderAtMollie = async ( id: number ) => {
				await wooCommerceApi.updateOrder( id, { status: 'completed' } );
				await expect( async () => {
					const notes = await wooCommerceApi.getOrderNotes( id );
					const captured = notes.some( ( n ) =>
						n.note.includes(
							'successfully updated to shipped at Mollie'
						)
					);
					await expect(
						captured,
						'Assert order was shipped/captured at Mollie (order note present)'
					).toBe( true );
				} ).toPass( { intervals: [ 5_000 ], timeout: 60_000 } );
			};

			let transactionId: string;
			let molliePaymentId: string;
			let orderId: number;
			let refundAmount: string;
			let refundAvailable: number;
			let refundTransactionId: number;
			let orderTotal: string;
			let refunds = [];
			let refundMeta: { key: string; value: any };
			let statusAfterRefund: string;
			// Preconditions
			await test.step( 'Precondition: create WooCommerce order', async () => {
				await updateCurrencyIfNeeded( wooCommerceApi, currency );

				const orderTotals = await countTotals( testData );
				payment.amount = orderTotals.order;
				refundAvailable = orderTotals.order;
				refundAmount = getAmountPercentage(
					refundAvailable,
					testData.refundPercentage
				);

				await utils.fillVisitorsCart( testData.products );

				await checkout.makeOrder( testData );

				await mollieHostedCheckout.assertUrl();
				orderId = await mollieHostedCheckout.captureOrderNumber();
				await mollieHostedCheckout.payForOrder( payment );

				await processMolliePaymentStatus(
					{ mollieHostedCheckout, orderReceived, payForOrder },
					Number( orderId ),
					testData
				);

				const order = await wooCommerceApi.getOrder( orderId );
				// In case of Payment API transactionId = paymentId but in case of Order API transactionID = orderId which appears in order notes
				transactionId = order.transaction_id;
				await expect(
					transactionId,
					`Assert transaction ID ${ transactionId } is defined`
				).toBeDefined();
				// In case of Order API transactionId = orderId but to make a refund paymentId is required
				molliePaymentId = order.meta_data.find(
					( meta ) => meta.key === '_mollie_payment_id'
				)?.value;
				await expect(
					molliePaymentId,
					`Assert payment ID ${ molliePaymentId } is defined`
				).toBeDefined();
			} );

			if ( gateway.isCaptureRequired ) {
				await test.step( 'Precondition: capture authorized order at Mollie', () =>
					captureOrderAtMollie( orderId ) );
			}

			// Test

			// Make refund via Mollie client API
			if ( isMollieClientApiRefund ) {
				await test.step( 'Make refund via Mollie client API', async () => {
					const idempotencyKey = `${ molliePaymentId }-refund-${ orderId }`;
					await mollieClientApi.refunds.create( {
						paymentId: molliePaymentId,
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
				await test.step( 'Make refund via WooCommerce', async () => {
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

			// `_mollie_processed_refund_ids` is only ever written by
			// MollieOrderService::processRefunds(), which itself only runs
			// inside the incoming-webhook handler (doPaymentForOrder()). A
			// refund made via the Mollie client API happens entirely outside
			// WooCommerce, so WC genuinely has no other way to learn about it
			// - it has to wait for the next webhook to reconcile, which can
			// take up to ~10-30 minutes.
			// A refund made via the WooCommerce admin UI is the opposite:
			// RefundProcessor/MollieOrder::refund() creates it at Mollie
			// synchronously in the same request, so WC already knows about
			// it immediately. Mollie also doesn't re-ping the webhook just
			// because a refund was created via its API, so polling for the
			// meta here would just wait out the full timeout for a signal
			// that never arrives - check order.refunds instead.
			if ( isMollieClientApiRefund ) {
				await test.step( 'Wait for webhook and assert refund meta ~15 min', async () => {
					await expect( async () => {
						const order = await wooCommerceApi.getOrder( orderId );
						orderTotal = order.total;
						statusAfterRefund = order.status;
						refunds = order.refunds;
						refundMeta = order.meta_data.find(
							( meta ) =>
								meta.key === '_mollie_processed_refund_ids'
						);

						await expect(
							refundMeta,
							'Assert refund meta is defined'
						).toBeDefined();
						await wooCommerceOrderEdit.page.reload();
					} ).toPass( {
						intervals: [ 60_000 ],
						timeout: 30 * 60_000,
					} );

					await expect(
						refundMeta.value,
						'Assert refund meta value has length 1'
					).toHaveLength( 1 );
					refundTransactionId = refundMeta.value[ 0 ];
				} );
			} else {
				await test.step( 'Assert refund is reflected on the order', async () => {
					const order = await wooCommerceApi.getOrder( orderId );
					orderTotal = order.total;
					statusAfterRefund = order.status;
					refunds = order.refunds;

					await expect(
						refunds,
						'Assert refund is present on the order'
					).toHaveLength( 1 );
					refundTransactionId = refunds[ 0 ].id;
				} );
			}

			await test.step( 'Assert refund details', async ( step ) => {
				step.skip(
					isMolliePartialRefund,
					'Not availabe for partial refund via Mollie dashboard'
				);

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
			await test.step( 'Assert refund Order Notes', async () => {
				const formattedRefundAmount =
					parseFloat( refundAmount ).toString();
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

				// await assertOrderNotes(
				// 	wooCommerceApi,
				// 	orderId,
				// 	expectedNotes
				// );
			} );
		}
	);
};
