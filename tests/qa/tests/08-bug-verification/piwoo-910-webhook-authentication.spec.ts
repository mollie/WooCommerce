/**
 * Verifies the webhook authentication fix (mollie_webhook_secret) described in the
 * "webhook endpoint is unauthenticated" report. Per the PR, the fix is scoped to
 * RestApi (/wp-json/mollie/v1/webhook only): RestApi::checkWebhookSecret() rejects
 * requests missing or carrying a wrong mollie_webhook_secret before RestApi::callback()
 * runs, and UrlMiddleware::getWebhookUrl() embeds the token (from
 * RestApi::getOrCreateWebhookSecret()) in the URL registered with Mollie. The legacy
 * ?wc-api=mollie_return endpoint is out of scope for this PR and is not covered here.
 *
 * NOTE: the REST fix is merged and verified; the tests below pass against the
 * current codebase. The fix is REST-only (/wp-json/mollie/v1/webhook). The legacy
 * ?wc-api=mollie_return endpoint answering unauthenticated testByMollie probes with
 * 200 is intentionally out of scope (won't-fix, per the dev): that 200 comes from
 * WooCommerce core's gateway-callback dispatch, which runs before any Mollie code,
 * is not plugin-specific, can't be changed without breaking live payment callbacks,
 * and leaks only "a gateway is installed" - no order data or secrets.
 */

/**
 * External dependencies
 */
import { countTotals } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import {
	gateways,
	orders,
	products,
	shopConfigDefault,
	MollieTestData,
} from '../../resources';

const testOrder: MollieTestData.ShopOrder = {
	...orders.default,
	products: [ products.mollieSimple100 ],
	payment: {
		gateway: gateways.ideal,
		status: 'paid',
		bankIssuer: 'ING',
	},
};

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( shopConfigDefault );
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();
} );

test.describe( 'Webhook authentication (mollie_webhook_secret)', () => {
	test( 'REST webhook rejects requests with no secret, a wrong secret, or an unknown transaction', async ( {
		visitorRequest,
		requestUtils,
	} ) => {
		const restRoot = await requestUtils.getAPIRootURL();
		const webhookEndpoint = new URL(
			'mollie/v1/webhook',
			restRoot
		).toString();

		// Bare probe, exactly as an anonymous scanner would send it: no cookies, no
		// nonce, no params at all.
		const bareResponse = await visitorRequest.post( webhookEndpoint );
		await expect(
			bareResponse.status(),
			'Assert unauthenticated bare POST is refused'
		).toBe( 401 );

		// A plausible but made-up transaction ID. Unauthenticated, this must be
		// refused before any order lookup or outbound call to Mollie is attempted -
		// that outbound call is the enumeration/DoS vector from the report.
		const start = Date.now();
		const fakeIdResponse = await visitorRequest.post( webhookEndpoint, {
			form: { id: `tr_verification_${ Date.now() }` },
		} );
		const elapsedMs = Date.now() - start;

		await expect(
			fakeIdResponse.status(),
			'Assert unauthenticated request with an unrecognized transaction ID is refused'
		).toBe( 401 );
		await expect
			.soft(
				elapsedMs,
				'Assert rejection is near-instant, i.e. no outbound Mollie API call was attempted for the unknown ID'
			)
			.toBeLessThan( 3000 );

		// checkWebhookSecret() must hash_equals()-compare, not just check presence.
		const wrongSecretUrl = new URL( webhookEndpoint );
		wrongSecretUrl.searchParams.set(
			'mollie_webhook_secret',
			'not-the-real-secret'
		);
		const wrongSecretResponse = await visitorRequest.post(
			wrongSecretUrl.toString(),
			{ form: { id: `tr_verification_${ Date.now() }` } }
		);
		await expect(
			wrongSecretResponse.status(),
			'Assert a request carrying an incorrect mollie_webhook_secret is refused'
		).toBe( 401 );
	} );

	test( 'A real Mollie payment still completes the order automatically, and the webhook endpoint accepts the secret it embeds', async ( {
		utils,
		checkout,
		mollieHostedCheckout,
		orderReceived,
		wooCommerceApi,
		mollieClientApi,
		visitorRequest,
		mollieApiMethod,
	} ) => {
		test.skip(
			mollieApiMethod !== 'payment',
			'Secret retrieval reads the Payments API resource; Order API regression is already covered by the 05-transaction and 07-subscription suites.'
		);

		// --- Nothing broke: a genuine Mollie payment still completes the order ---
		const orderTotals = await countTotals( testOrder );
		testOrder.payment.amount = orderTotals.order;

		await utils.fillVisitorsCart( testOrder.products );
		await checkout.makeOrder( testOrder );
		await mollieHostedCheckout.assertUrl();
		const orderId = await mollieHostedCheckout.captureOrderNumber();
		await mollieHostedCheckout.payForOrder( testOrder.payment );
		await orderReceived.assertOrderDetails( testOrder );

		const paidOrder = await wooCommerceApi.getOrderByIdAndStatus(
			orderId,
			'processing'
		);
		await expect(
			paidOrder.status,
			'Assert order status updated to processing without manual intervention'
		).toBe( 'processing' );

		const transactionId = paidOrder.transaction_id;
		await expect(
			transactionId,
			'Assert a Mollie transaction ID was recorded on the order'
		).toBeTruthy();

		// --- The webhook URL Mollie holds for this payment now carries a secret ---
		const payment = await mollieClientApi.payments.get( {
			paymentId: transactionId,
		} );
		const secret = payment.webhookUrl
			? new URL( payment.webhookUrl ).searchParams.get(
					'mollie_webhook_secret'
			  )
			: null;
		await expect(
			secret,
			'Assert the webhook URL registered with Mollie carries a mollie_webhook_secret'
		).toBeTruthy();

		// Replay the exact call Mollie itself would make: POST to the stored
		// webhookUrl (secret included in the query string) with the transaction ID
		// in the body.
		const authenticatedResponse = await visitorRequest.post(
			payment.webhookUrl,
			{ form: { id: transactionId } }
		);
		await expect(
			authenticatedResponse.status(),
			'Assert a webhook call carrying the correct secret is accepted'
		).toBe( 200 );
	} );
} );
