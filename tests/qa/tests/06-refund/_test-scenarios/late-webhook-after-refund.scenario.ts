/**
 * External dependencies
 */
import { countTotals, expect, WooCommerceApi } from '@inpsyde/playwright-utils/build';
import { Client as MollieClientApi } from 'mollie-api-typescript';
import { APIRequestContext } from '@playwright/test';
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
import { WooCommerceOrderEdit } from '../../../utils/admin';
import { MollieTestData, guests } from '../../../resources';

/**
 * Refunds a paid order via WooCommerce admin, delivers the webhook Mollie
 * holds for the payment once to let it reconcile the refund (the one
 * legitimate notification a real Mollie webhook would also send - a
 * WooCommerce-admin refund is created at Mollie synchronously, so nothing
 * has reconciled it yet at this point), then replays that same webhook a
 * second time as the genuine late/duplicate delivery under test, and asserts
 * nothing changes between the two deliveries.
 *
 * @param param0
 * @param param0.wooCommerceApi
 * @param param0.wooCommerceOrderEdit
 * @param param0.mollieClientApi
 * @param param0.visitorRequest
 * @param orderId
 * @param molliePaymentId
 * @param transactionId
 * @param gatewayName
 */
const assertLateWebhookDoesNotUnrefund = async (
	{
		wooCommerceApi,
		wooCommerceOrderEdit,
		mollieClientApi,
		visitorRequest,
	}: {
		wooCommerceApi: WooCommerceApi;
		wooCommerceOrderEdit: WooCommerceOrderEdit;
		mollieClientApi: MollieClientApi;
		visitorRequest: APIRequestContext;
	},
	orderId: number,
	molliePaymentId: string,
	transactionId: string,
	gatewayName: string
) => {
	// --- Refund the order fully via WooCommerce admin ---
	await wooCommerceOrderEdit.visit( orderId );
	await wooCommerceOrderEdit.refundButton().click( { force: true } );
	await wooCommerceOrderEdit.makeRefund( gatewayName );
	await wooCommerceOrderEdit.assertUrl( orderId );

	const refundedOrder = await wooCommerceApi.getOrder( orderId );
	await expect(
		refundedOrder.status,
		'Assert order is Refunded before delivering the webhook'
	).toEqual( 'refunded' );

	const payment = await mollieClientApi.payments.get( {
		paymentId: molliePaymentId,
	} );
	const webhookUrl = payment.webhookUrl;
	await expect(
		webhookUrl,
		'Assert the payment has a webhookUrl registered to replay'
	).toBeTruthy();

	const postWebhook = () =>
		visitorRequest.post( webhookUrl as string, { form: { id: transactionId } } );

	// --- First delivery: the legitimate, one-time reconciliation. This
	// establishes the baseline a genuine late *duplicate* (the second
	// delivery below) must not disturb.
	const firstDeliveryResponse = await postWebhook();
	await expect(
		firstDeliveryResponse.status(),
		'Assert the first post-refund webhook delivery is accepted (200)'
	).toBe( 200 );

	const orderAfterFirstDelivery = await wooCommerceApi.getOrder( orderId );
	await expect(
		orderAfterFirstDelivery.status,
		'Assert the order is still Refunded after the first (legitimate) webhook delivery'
	).toEqual( 'refunded' );

	const paymentAfterFirstDelivery = await mollieClientApi.payments.get( {
		paymentId: molliePaymentId,
	} );
	const amountRefundedBaseline = paymentAfterFirstDelivery.amountRefunded?.value;

	const refundProcessedIdsBaseline = orderAfterFirstDelivery.meta_data.find(
		( meta ) => meta.key === '_mollie_processed_refund_ids'
	)?.value;

	const notesAfterFirstDelivery = await wooCommerceApi.getOrderNotes( orderId );
	const refundNoteCountBaseline = notesAfterFirstDelivery.filter( ( note ) =>
		note.note.startsWith( 'Refunded ' )
	).length;

	// --- Second delivery: the genuine late/duplicate webhook under test ---
	const replayResponse = await postWebhook();
	await expect(
		replayResponse.status(),
		'Assert the replayed webhook is accepted (200) - a rejection would mean the secret/lookup itself is broken, not the fix under test'
	).toBe( 200 );

	// --- Necessary but not sufficient: status must not regress ---
	const orderAfterReplay = await wooCommerceApi.getOrder( orderId );
	await expect(
		orderAfterReplay.status,
		'Assert the order stays Refunded after the late webhook replay - must not flip back to Processing/Completed'
	).toEqual( 'refunded' );

	// --- The fix-agnostic gate: the refund itself must stay exactly as it
	// was after the first (legitimate) delivery - one refund, unchanged ---
	const paymentAfterReplay = await mollieClientApi.payments.get( {
		paymentId: molliePaymentId,
	} );
	await expect(
		paymentAfterReplay.amountRefunded?.value,
		'Assert Mollie-side amountRefunded is unchanged - the late replay must not trigger a second refund'
	).toEqual( amountRefundedBaseline );

	await expect(
		orderAfterReplay.refunds,
		'Assert the WooCommerce refund record(s) are unchanged - same refunds, not a new one'
	).toEqual( orderAfterFirstDelivery.refunds );

	const refundProcessedIdsAfter = orderAfterReplay.meta_data.find(
		( meta ) => meta.key === '_mollie_processed_refund_ids'
	)?.value;
	await expect(
		refundProcessedIdsAfter,
		'Assert _mollie_processed_refund_ids gained no extra entry from the late replay'
	).toEqual( refundProcessedIdsBaseline );

	const notesAfterReplay = await wooCommerceApi.getOrderNotes( orderId );
	const refundNoteCountAfter = notesAfterReplay.filter( ( note ) =>
		note.note.startsWith( 'Refunded ' )
	).length;
	await expect(
		refundNoteCountAfter,
		'Assert no second "Refunded" order note was added by the late replay'
	).toEqual( refundNoteCountBaseline );
};

export const testLateWebhookAfterRefundOnCheckout = (
	testData: MollieTestData.ShopOrder
) => {
	const { testId, testLabel, payment } = testData;
	const { gateway } = payment;

	const customer = guests[ gateway.country ];
	const currency = gateway.currency;
	Object.assign( testData, { customer, currency } );
	const gatewayLabel = buildMollieGatewayLabel( gateway );
	const label = testLabel ? ` ${ testLabel }` : '';

	test( `${ testId } | Refund - Late webhook replay - Checkout - ${ gatewayLabel }${ label }`, async ( {
		wooCommerceApi,
		utils,
		checkout,
		mollieHostedCheckout,
		orderReceived,
		payForOrder,
		wooCommerceOrderEdit,
		mollieClientApi,
		visitorRequest,
		isMultistepCheckout,
		mollieApiMethod,
	} ) => {
		test.setTimeout( 3 * 60_000 );

		test.skip(
			! gateway.availableForApiMethods.includes( mollieApiMethod ),
			`Test is not eligible for ${ mollieApiMethod } API method.`
		);

		if ( ! testData.orderStatus ) {
			testData.orderStatus = await getOrderStatusFromMollieStatus(
				payment.status,
				mollieApiMethod
			);
		}

		await updateCurrencyIfNeeded( wooCommerceApi, currency );

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

		const order = await wooCommerceApi.getOrder( orderId );
		const transactionId = order.transaction_id;
		const molliePaymentId = order.meta_data.find(
			( meta ) => meta.key === '_mollie_payment_id'
		)?.value;
		await expect(
			molliePaymentId,
			'Assert a Mollie payment ID was recorded on the order'
		).toBeDefined();

		await assertLateWebhookDoesNotUnrefund(
			{ wooCommerceApi, wooCommerceOrderEdit, mollieClientApi, visitorRequest },
			orderId,
			molliePaymentId,
			transactionId,
			gateway.name
		);
	} );
};
