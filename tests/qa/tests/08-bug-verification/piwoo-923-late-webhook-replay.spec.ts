/**
 * Verifies PIWOO-923 ("late paid webhook after refund silently un-refunds a
 * pay-later order"). Per the report, MollieOrderService::orderNeedsPayment()
 * (MollieOrderService.php:334-363) unconditionally returns true for any
 * order carrying `_mollie_authorized = '1'`, regardless of the order's
 * current status or refund state. That meta is set once by
 * onWebhookAuthorized() and never cleared, so a late/duplicate webhook
 * delivered by Mollie after a pay-later (authorize-then-capture) order has
 * already been refunded still reaches doPaymentForOrder()'s "order doesn't
 * need payment" dispatch (MollieOrderService.php:275-288) and is routed to
 * the matching onWebhook*() handler by live Mollie status - never checking
 * amountRefunded or the order's own WooCommerce status.
 *
 * For Klarna specifically (Orders API), a captured order's live Mollie
 * status is "completed", so the handler actually invoked on replay is
 * onWebhookCompleted() (WebhookHandler.php:185-243), not onWebhookPaid() -
 * same missing-guard defect, different method name, since Mollie's Payments
 * API "paid" and Orders API "completed" are the two names for "this
 * transaction settled" that the ticket's onWebhookPaid example generalizes
 * over. Both unconditionally call $order->payment_complete(), which can
 * flip an already-Refunded order back to Processing/Completed.
 *
 * This can't be observed by waiting for a real duplicate webhook - Mollie's
 * redelivery timing isn't controllable from a test and there's no guarantee
 * one arrives during a test run. Instead, this delivers the webhook
 * directly: RestApi::callback() (RestApi.php) authenticates a POST to
 * /wp-json/mollie/v1/webhook via the mollie_webhook_secret embedded in the
 * webhookUrl Mollie already holds for the order (same mechanism verified
 * in piwoo-910-webhook-authentication.spec.ts). Mollie's Payment/Order
 * resources have no "refunded" status value - status stays "completed"
 * forever once captured (refund info is carried separately via
 * amountRefunded/_links.refunds).
 *
 * The refund is created synchronously, so no webhook has reconciled it yet
 * here - a single POST would be the first delivery, not a late duplicate.
 * So this posts twice: the first establishes the baseline, the second is
 * the late duplicate under test. Assertions compare against that baseline,
 * not a pre-webhook snapshot.
 *
 * Out of scope here: the equivalent race for late "canceled"/"failed"
 * webhooks against an already-settled order (same ticket, and PIWOO-927 for
 * the canceled case specifically) - reproducing those needs a second,
 * superseded payment attempt on the same order, which this spec doesn't set
 * up. Also out of scope: whether stock is genuinely double-reduced - WC
 * core guards reduce_stock via a `_reduced_stock` order-meta flag
 * independent of this bug, so the stock assertion below is a soft
 * diagnostic, not the fix-agnostic gate.
 */

/**
 * External dependencies
 */
import { countTotals } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { test, expect, processMolliePaymentStatus } from '../../utils';
import {
	gateways,
	orders,
	products,
	shopConfigDefault,
	MollieTestData,
} from '../../resources';

const STOCK_BASELINE = 100;

const testOrder: MollieTestData.ShopOrder = {
	...orders.default,
	products: [ products.mollieSimple100 ],
	payment: { gateway: gateways.klarna, status: 'authorized' },
};

let testProductId: number;

test.beforeAll( async ( { utils, wooCommerceApi } ) => {
	await utils.configureStore( shopConfigDefault );
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();

	const product = await wooCommerceApi.getProductBySlug(
		products.mollieSimple100.slug
	);
	testProductId = product.id;
	await wooCommerceApi.updateProduct( testProductId, {
		manage_stock: true,
		stock_quantity: STOCK_BASELINE,
	} );
} );

test.afterAll( async ( { wooCommerceApi } ) => {
	// mollieSimple100 is shared across the whole suite - put it back to its
	// default (unmanaged) stock state so other specs aren't affected by this
	// spec's stock tracking.
	await wooCommerceApi.updateProduct( testProductId, {
		manage_stock: false,
	} );
} );

test( 'C4567637 | A late "completed" webhook replay must not un-refund an already-refunded Klarna order', async ( {
	utils,
	checkout,
	mollieHostedCheckout,
	payForOrder,
	orderReceived,
	wooCommerceApi,
	wooCommerceOrderEdit,
	visitorRequest,
} ) => {
	test.setTimeout( 3 * 60_000 );

	// --- Precondition: place and authorize a Klarna (pay-later) order ---
	const orderTotals = await countTotals( testOrder );
	testOrder.payment.amount = orderTotals.order;

	await utils.fillVisitorsCart( testOrder.products );
	await checkout.makeOrder( testOrder );
	await mollieHostedCheckout.assertUrl();
	const orderId = await mollieHostedCheckout.captureOrderNumber();
	await mollieHostedCheckout.payForOrder( testOrder.payment );
	await processMolliePaymentStatus(
		{ mollieHostedCheckout, orderReceived, payForOrder },
		Number( orderId ),
		testOrder
	);

	const authorizedOrder = await wooCommerceApi.getOrder( orderId );
	const transactionId = authorizedOrder.transaction_id;
	const molliePaymentId = authorizedOrder.meta_data.find(
		( meta ) => meta.key === '_mollie_payment_id'
	)?.value;
	await expect(
		molliePaymentId,
		'Assert a Mollie payment ID was recorded on the order'
	).toBeDefined();

	// --- Precondition: capture (ship) the order at Mollie ---
	// Polling the WC order's own status here would be a tautology - WC
	// commits "completed" before the capture hook that calls Mollie even
	// runs. Poll for the specific success order note instead. Klarna's
	// authorize->capture can land on either the Orders API (shipAll) or the
	// Payments API (manual capture) depending on the store's api_switch
	// setting, and each writes a different note - accept either.
	await wooCommerceApi.updateOrder( orderId, { status: 'completed' } );
	await expect( async () => {
		const notes = await wooCommerceApi.getOrderNotes( orderId );
		const captured = notes.some(
			( note ) =>
				note.note.includes( 'successfully updated to shipped at Mollie' ) ||
				note.note.includes( 'payment capture of' )
		);
		await expect(
			captured,
			'Assert order was shipped/captured at Mollie (order note present)'
		).toBe( true );
	} ).toPass( { intervals: [ 5_000 ], timeout: 60_000 } );

	const capturedProduct = await wooCommerceApi.getProduct( testProductId );
	const stockAfterCapture = capturedProduct.stock_quantity;
	await expect
		.soft(
			stockAfterCapture,
			'Diagnostic: stock reduced once for the authorized order'
		)
		.toBeLessThan( STOCK_BASELINE );

	// --- Refund the order fully via WooCommerce admin ---
	await wooCommerceOrderEdit.visit( orderId );
	await wooCommerceOrderEdit.refundButton().click( { force: true } );
	await wooCommerceOrderEdit.makeRefund( testOrder.payment.gateway.name );
	await wooCommerceOrderEdit.assertUrl( orderId );

	const refundedOrder = await wooCommerceApi.getOrder( orderId );
	await expect(
		refundedOrder.status,
		'Assert order is Refunded before delivering the webhook'
	).toEqual( 'refunded' );

	const stockAfterRefund = (
		await wooCommerceApi.getProduct( testProductId )
	).stock_quantity;

	// Klarna never fires onWebhookPaid(), so webhookUrl/amountRefunded never land
	// on the payment sub-resource - fetch the Mollie Order directly instead.
	const getMollieOrder = async () => {
		const response = await visitorRequest.get(
			`https://api.mollie.com/v2/orders/${ transactionId }`,
			{
				headers: {
					Authorization: `Bearer ${ process.env.MOLLIE_TEST_API_KEY }`,
				},
			}
		);
		return response.json();
	};

	const mollieOrder = await getMollieOrder();
	const webhookUrl = mollieOrder.webhookUrl;
	await expect(
		webhookUrl,
		'Assert the order has a webhookUrl registered to replay'
	).toBeTruthy();

	const postWebhook = () =>
		visitorRequest.post( webhookUrl as string, { form: { id: transactionId } } );

	// --- First delivery: legitimate reconciliation, establishes the baseline ---
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

	const mollieOrderAfterFirstDelivery = await getMollieOrder();
	const amountRefundedBaseline = mollieOrderAfterFirstDelivery.amountRefunded?.value;

	const refundProcessedIdsBaseline = orderAfterFirstDelivery.meta_data.find(
		( meta ) => meta.key === '_mollie_processed_refund_ids'
	)?.value;

	const notesAfterFirstDelivery = await wooCommerceApi.getOrderNotes( orderId );
	const refundNoteCountBaseline = notesAfterFirstDelivery.filter( ( note ) =>
		note.note.startsWith( 'Refunded ' )
	).length;
	const completedNoteCountBaseline = notesAfterFirstDelivery.filter( ( note ) =>
		note.note.includes( 'Order completed at Mollie for' )
	).length;

	// --- Second delivery: the late duplicate under test ---
	const replayResponse = await postWebhook();
	await expect(
		replayResponse.status(),
		'Assert the replayed webhook is accepted (200) - a rejection would mean the secret/lookup itself is broken, not the fix under test'
	).toBe( 200 );

	// --- Status must not regress (not sufficient on its own; see refund gate below) ---
	const orderAfterReplay = await wooCommerceApi.getOrder( orderId );
	await expect(
		orderAfterReplay.status,
		'Assert the order stays Refunded after the late webhook replay - must not flip back to Processing/Completed'
	).toEqual( 'refunded' );

	// --- Refund must stay exactly as it was after the first delivery ---
	const mollieOrderAfterReplay = await getMollieOrder();
	await expect(
		mollieOrderAfterReplay.amountRefunded?.value,
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

	// --- Diagnostics: informative, not a hard gate (see file header) ---
	const stockAfterReplay = (
		await wooCommerceApi.getProduct( testProductId )
	).stock_quantity;
	await expect
		.soft(
			stockAfterReplay,
			'Diagnostic: stock must not be reduced a second time by a re-run payment_complete()'
		)
		.toEqual( stockAfterRefund );

	const completedNoteCountAfter = notesAfterReplay.filter( ( note ) =>
		note.note.includes( 'Order completed at Mollie for' )
	).length;
	await expect
		.soft(
			completedNoteCountAfter,
			'Diagnostic: an EXTRA onWebhookCompleted() note from the late replay (beyond the one the first, legitimate delivery already added) is direct evidence the handler re-ran again against an already-refunded order'
		)
		.toEqual( completedNoteCountBaseline );
} );
