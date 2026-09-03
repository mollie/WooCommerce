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
 * Refunds a paid order via WooCommerce admin, then replays the original
 * "paid" webhook Mollie already holds for the payment, and asserts the order
 * stays refunded instead of being un-refunded by the late replay.
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
	await wooCommerceOrderEdit.refundButton().click();
	await wooCommerceOrderEdit.makeRefund( gatewayName );
	await wooCommerceOrderEdit.assertUrl( orderId );

	const refundedOrder = await wooCommerceApi.getOrder( orderId );
	await expect(
		refundedOrder.status,
		'Assert order is Refunded before replaying the webhook'
	).toEqual( 'refunded' );

	// --- Replay the webhook Mollie already holds for this payment ---
	const payment = await mollieClientApi.payments.get( {
		paymentId: molliePaymentId,
	} );
	const webhookUrl = payment.webhookUrl;
	await expect(
		webhookUrl,
		'Assert the payment has a webhookUrl registered to replay'
	).toBeTruthy();

	const replayResponse = await visitorRequest.post( webhookUrl as string, {
		form: { id: transactionId },
	} );
	await expect(
		replayResponse.status(),
		'Assert the replayed webhook is accepted (200) - a rejection would mean the secret/lookup itself is broken, not the fix under test'
	).toBe( 200 );

	// --- Fix-agnostic gate: status must not regress ---
	const orderAfterReplay = await wooCommerceApi.getOrder( orderId );
	await expect(
		orderAfterReplay.status,
		'Assert the order stays Refunded after the late webhook replay - must not flip back to Processing/Completed'
	).toEqual( 'refunded' );
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
