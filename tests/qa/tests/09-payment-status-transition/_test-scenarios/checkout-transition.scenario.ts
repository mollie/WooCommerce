/**
 * External dependencies
 */
import { countTotals, expect, WooCommerceApi } from '@inpsyde/playwright-utils/build';
import { Client as MollieClientApi } from 'mollie-api-typescript';
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
import { MollieTestData, MollieSettings, guests } from '../../../resources';

/**
 * Only banktransfer/directdebit are driven to "on-hold" at checkout
 * (PaymentProcessor::updatePaymentStatusForDelayedMethods() hardcodes it to
 * those two). Other `confirmationDelayed` gateways (e.g. iDEAL) stay
 * "pending" the whole time - never visibly on-hold.
 *
 * Timing: on-hold is set the instant WC creates the order, before the
 * customer touches Mollie's checkout page. Any action there lets WC's return
 * handler process the live status and move the order on - so `order` must be
 * fetched right after redirect, before any page interaction, or this check
 * reliably loses the race. Soft assertion as a safety net only; expected to
 * pass.
 *
 * Only for "expired"/"paid": Mollie's API can't force a second, independent
 * webhook for those after the fact (unlike "canceled" - see
 * assertOnHoldCancelledByMollie).
 *
 * @param order
 */
const assertStartsOnHold = async (
	order: Awaited< ReturnType< WooCommerceApi[ 'getOrder' ] > >
) => {
	await expect
		.soft(
			order.status,
			'Assert order genuinely starts on-hold before settling to its final status'
		)
		.toEqual( 'on-hold' );
};

/**
 * Deterministic version of the on-hold -> cancelled transition: instead of
 * picking "canceled" directly on the Mollie hosted checkout page (a single
 * webhook that may never leave the order observably on-hold), this hard-
 * asserts the order is on-hold right after checkout (the order's real
 * initial status - see assertStartsOnHold), then cancels the payment through
 * the real Mollie API - no need to interact with Mollie's checkout page at
 * all. No sandbox-timing race: on-hold and cancelled are each backed by
 * their own real, independently-verified state.
 *
 * @param param0
 * @param param0.wooCommerceApi
 * @param param0.mollieClientApi
 * @param orderId
 * @param molliePaymentId
 * @param mollieApiMethod
 */
const assertOnHoldCancelledByMollie = async (
	{
		wooCommerceApi,
		mollieClientApi,
	}: {
		wooCommerceApi: WooCommerceApi;
		mollieClientApi: MollieClientApi;
	},
	orderId: number,
	molliePaymentId: string,
	mollieApiMethod: MollieSettings.ApiMethod
) => {
	const order = await wooCommerceApi.getOrder( orderId );
	await expect(
		order.status,
		'Assert the order is genuinely on-hold before Mollie cancels the payment'
	).toEqual( 'on-hold' );

	await mollieClientApi.payments.cancel( { paymentId: molliePaymentId } );

	const expectedStatus = getOrderStatusFromMollieStatus(
		'canceled',
		mollieApiMethod
	);
	await expect( async () => {
		const orderAfterCancel = await wooCommerceApi.getOrder( orderId );
		await expect(
			orderAfterCancel.status,
			`Assert the order settles to "${ expectedStatus }" after Mollie cancels the still on-hold payment`
		).toEqual( expectedStatus );
	} ).toPass( { intervals: [ 5_000 ], timeout: 60_000 } );
};

/**
 * Drives an authorized-but-uncaptured (manual-capture) order to "cancelled"
 * and asserts the plugin's StateChangeCapture -> VoidPayment path actually
 * voided the payment at Mollie. VoidPayment adds no order note on success
 * (src/MerchantCapture/Capture/Action/VoidPayment.php only notes on failure),
 * so the Mollie payment's own status is the only reliable signal.
 *
 * UNVERIFIED: assumes the "on_status_change_enabled" merchant-capture setting
 * is enabled on the test shop by default - confirm via a real run, and if it
 * isn't, this needs a utils.configureStore()-style precondition first.
 *
 * @param param0
 * @param param0.wooCommerceApi
 * @param param0.mollieClientApi
 * @param orderId
 * @param molliePaymentId
 */
const voidAuthorizedOrder = async (
	{
		wooCommerceApi,
		mollieClientApi,
	}: {
		wooCommerceApi: WooCommerceApi;
		mollieClientApi: MollieClientApi;
	},
	orderId: number,
	molliePaymentId: string
) => {
	await wooCommerceApi.updateOrder( orderId, { status: 'cancelled' } );

	await expect( async () => {
		const payment = await mollieClientApi.payments.get( {
			paymentId: molliePaymentId,
		} );
		await expect(
			payment.status,
			'Assert the Mollie payment was voided after the merchant cancelled the still-authorized order'
		).toEqual( 'canceled' );
	} ).toPass( { intervals: [ 5_000 ], timeout: 60_000 } );

	const orderAfterVoid = await wooCommerceApi.getOrder( orderId );
	await expect(
		orderAfterVoid.status,
		'Assert the WooCommerce order reflects the cancellation'
	).toEqual( 'cancelled' );
};

export const testOrderStatusTransitionOnCheckout = (
	testData: MollieTestData.ShopOrderTransition
) => {
	const { testId, testLabel, payment, transition } = testData;
	const { gateway } = payment;

	const customer = guests[ gateway.country ];
	const currency = gateway.currency;
	Object.assign( testData, { customer, currency } );
	const gatewayLabel = buildMollieGatewayLabel( gateway );
	const label = testLabel ? ` ${ testLabel }` : '';

	test( `${ testId } | Transaction - Checkout - ${ gatewayLabel } - Payment status ${ payment.status } - Transition ${ transition }${ label }`, async ( {
		wooCommerceApi,
		utils,
		checkout,
		mollieHostedCheckout,
		orderReceived,
		payForOrder,
		mollieClientApi,
		isMultistepCheckout,
		mollieApiMethod,
	} ) => {
		test.setTimeout( 3 * 60_000 );

		// exclude tests for payment methods if not available for tested API
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

		if ( transition === 'onHoldToFinal' ) {
			// "on-hold" is the order's real initial status
			// (AbstractPaymentMethod::getInitialOrderStatus()), set the
			// instant WC creates the order - before the customer does
			// anything on Mollie's checkout page. Once they complete any
			// action there (even just picking "open"), WC's return handler
			// processes the live payment status and moves the order on (to
			// "pending"), so it must be checked here, before any page
			// interaction, not afterward.
			const orderBeforePayment = await wooCommerceApi.getOrder( orderId );
			const molliePaymentId = orderBeforePayment.meta_data.find(
				( meta ) => meta.key === '_mollie_payment_id'
			)?.value;
			await expect(
				molliePaymentId,
				'Assert a Mollie payment ID was recorded on the order'
			).toBeDefined();

			if ( payment.status === 'canceled' ) {
				// Deterministic: cancel the still-open payment directly via
				// the Mollie API - no need to interact with the checkout
				// page at all (see assertOnHoldCancelledByMollie).
				await assertOnHoldCancelledByMollie(
					{ wooCommerceApi, mollieClientApi },
					orderId,
					molliePaymentId,
					mollieApiMethod
				);
				return;
			}

			// expired / paid: Mollie's API offers no way to force a second
			// webhook after the fact, so fall back to a single sandbox
			// click. (A "pending" case was considered too, to verify
			// onWebhookPending() genuinely never moves the order - but
			// Mollie's test-mode simulator doesn't offer "pending" as a
			// selectable outcome for banktransfer, only Open/Paid/Expired,
			// so there's no way to trigger it through this mechanism.)
			await assertStartsOnHold( orderBeforePayment );
			await mollieHostedCheckout.payForOrder( payment );
			await processMolliePaymentStatus(
				{ mollieHostedCheckout, orderReceived, payForOrder },
				Number( orderId ),
				testData
			);

			// processMolliePaymentStatus only checks page-redirect behavior,
			// not the order's actual status - assert that explicitly too.
			await expect( async () => {
				const orderAfterPayment = await wooCommerceApi.getOrder( orderId );
				await expect(
					orderAfterPayment.status,
					`Assert the order settles to "${ testData.orderStatus }"`
				).toEqual( testData.orderStatus );
			} ).toPass( { intervals: [ 5_000 ], timeout: 60_000 } );
			return;
		}

		// authorizedToVoided needs the order to settle first (authorized),
		// so drive that via the sandbox.
		await mollieHostedCheckout.payForOrder( payment );
		await processMolliePaymentStatus(
			{ mollieHostedCheckout, orderReceived, payForOrder },
			Number( orderId ),
			testData
		);

		const order = await wooCommerceApi.getOrder( orderId );
		const molliePaymentId = order.meta_data.find(
			( meta ) => meta.key === '_mollie_payment_id'
		)?.value;
		await expect(
			molliePaymentId,
			'Assert a Mollie payment ID was recorded on the order'
		).toBeDefined();

		if ( transition === 'authorizedToVoided' ) {
			await voidAuthorizedOrder(
				{ wooCommerceApi, mollieClientApi },
				orderId,
				molliePaymentId
			);
		}
	} );
};
