/**
 * Verifies PIWOO-890 ("order confirmation page double-counted by tracking tools
 * after paying with Mollie"). Per the report, UrlMiddleware.php:54 sends Mollie a
 * redirectUrl that already points at the order-received page (via
 * $gateway->get_return_url($order)) with ?filter_flag=onMollieReturn appended, not
 * at the legacy wc-api endpoint. Because of that, the browser's first landing on
 * that URL already satisfies is_order_received_page() before
 * PaymentModule::mollieReturnRedirect() (hooked on template_redirect,
 * PaymentModule.php:89-91) reacts to filter_flag and 302-redirects to the clean
 * URL. Any hook that runs before template_redirect (e.g. WordPress's 'wp' action -
 * what tracking plugins use) observes the thank-you page on both legs of that
 * redirect, so server-side tracking fires twice per order.
 *
 * As the report itself notes, this can only be observed against a real WP +
 * WooCommerce site driving the real Mollie test-payment redirect flow -
 * filter_input(INPUT_GET, ...) is always NULL under CLI SAPI, so
 * PaymentModule::orderByRequest() can never succeed under phpunit, and there is no
 * way to assert what a 'wp'-hooked tracking plugin observed without a live
 * request/response chain. Per the automation suggestion, this spec installs a
 * temporary QA-only probe plugin (piwoo-890-order-received-probe, source at
 * tests/qa/resources/files/piwoo-890-order-received-probe.zip) that sets an
 * X-QA-Order-Received-Hit response header on the 'wp' action whenever
 * is_order_received_page() is true, and asserts on the captured response chain.
 * Installed the same way enable-bizum.zip already is (plugins.installPluginFromFile
 * + requestUtils.activatePlugin), so it works against a real remote QA env, not
 * just wp-env/CI.
 *
 * Claim 3 from the report (WebhookHandler::onWebhookCompleted() calling
 * $order->payment_complete() a second time for Orders API/Klarna methods) is a
 * separate concern, out of scope for this spec per the ticket, and tracked
 * instead under its own follow-up ticket.
 *
 * Two things worth flagging about how this spec verifies the fix:
 *
 * 1. Whether the probe's header actually survives onto the flagged 302 response
 *    (set via header() on the 'wp' action, then PaymentModule::onMollieReturn()
 *    calls wp_safe_redirect() + die()) is the mechanical assumption the whole
 *    spec rests on. This was confirmed against the real Kinsta QA env (fixed
 *    build): is_order_received_page() is observed exactly once, on the clean
 *    URL (utm_nooverride=1, no filter_flag), via the wp-hook probe.
 * 2. The "exactly once" assertion (hitCount, below) is the only hard,
 *    fix-agnostic gate. The 302/filter_flag/200 shape assertions are kept as
 *    `expect.soft` diagnostics only - informative if a fix changes the redirect
 *    shape, but must never fail the test on their own, since a valid fix could
 *    take a different shape (e.g. dropping filter_flag, or not using an
 *    order-received-shaped intermediate URL at all).
 *
 * Manual verification (Kinsta staging, fixed build): Test 1 (double HTTP
 * request), Test 2 (single is_order_received_page), and Test 3 (returnUrl ->
 * order-received with filter_flag) all pass. No tracking plugin needed - the
 * probe measures the exact pre-template_redirect condition tracking tools key
 * on.
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
	piwoo890OrderReceivedProbePlugin,
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

type OrderReceivedResponse = {
	url: string;
	status: number;
	hit: string | null;
};

test.beforeAll( async ( { utils, requestUtils, plugins } ) => {
	await utils.configureStore( shopConfigDefault );
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();

	if (
		! ( await requestUtils.isPluginInstalled(
			piwoo890OrderReceivedProbePlugin.slug
		) )
	) {
		await plugins.installPluginFromFile(
			piwoo890OrderReceivedProbePlugin.zipFilePath
		);
	}
	await requestUtils.activatePlugin( piwoo890OrderReceivedProbePlugin.slug );
} );

test.afterAll( async ( { requestUtils } ) => {
	// Temporary QA-only probe - deactivate so it doesn't linger on the shared env.
	await requestUtils.deactivatePlugin(
		piwoo890OrderReceivedProbePlugin.slug
	);
} );

test.describe( 'Order-received page double-counted after Mollie return (PIWOO-890)', () => {
	/**
	 * Captures every main-frame response for the order-received URL. Filtered by
	 * frame identity (not resourceType) so it can't silently miss the redirect
	 * leg due to a resourceType quirk on a redirected navigation - checkout,
	 * mollieHostedCheckout and orderReceived are all constructed against this
	 * same visitorPage instance (see tests/qa/utils/test.ts), so there is no
	 * other page/context the Mollie return could land on unnoticed.
	 * @param visitorPage
	 */
	const captureOrderReceivedResponses = (
		visitorPage: import('@playwright/test').Page
	): OrderReceivedResponse[] => {
		const responses: OrderReceivedResponse[] = [];
		visitorPage.on( 'response', ( response ) => {
			const url = response.url();
			if (
				response.frame() === visitorPage.mainFrame() &&
				url.includes( '/order-received/' )
			) {
				responses.push( {
					url,
					status: response.status(),
					hit:
						response.headers()[ 'x-qa-order-received-hit' ] ?? null,
				} );
			}
		} );
		return responses;
	};

	test( 'C4567603 | Nothing broke: the order confirmation page still loads and displays correctly after paying with Mollie', async ( {
		utils,
		checkout,
		mollieHostedCheckout,
		orderReceived,
		visitorPage,
	} ) => {
		const responses = captureOrderReceivedResponses( visitorPage );

		const orderTotals = await countTotals( testOrder );
		testOrder.payment.amount = orderTotals.order;

		await utils.fillVisitorsCart( testOrder.products );
		await checkout.makeOrder( testOrder );
		await mollieHostedCheckout.assertUrl();
		await mollieHostedCheckout.payForOrder( testOrder.payment );

		await orderReceived.assertOrderDetails( testOrder );

		// Harness sanity check, independent of the PIWOO-890 fix status: proves
		// the response listener is wired to the right page and actually observes
		// the order-received URL, so a future empty capture in the test below can
		// only mean the harness broke, not be silently misread as "fixed".
		await expect(
			responses.length,
			'Assert the order-received URL was observed at least once during the real Mollie return'
		).toBeGreaterThan( 0 );

		// Assert the probe itself actually fired - confirmed against the real
		// Kinsta env, so this holds both pre- and post-fix and guards against a
		// silent hitCount === 0 (e.g. a broken probe) being misread as a pass.
		await expect(
			responses.some( ( response ) => response.hit === '1' ),
			'Assert the QA probe header was observed at least once'
		).toBe( true );

		// --- Diagnostic only: documents the currently-known redirect shape
		// (302 with filter_flag, then a clean 200) so a regression is visible in
		// the report, but a differently-shaped valid fix must not fail this test
		// on these checks alone. ---
		await expect
			.soft(
				responses,
				'Diagnostic: the order-received URL is hit twice - the filter_flag redirect, then the clean render'
			)
			.toHaveLength( 2 );
		if ( responses.length === 2 ) {
			const [ flaggedResponse, finalResponse ] = responses;
			await expect
				.soft(
					flaggedResponse.url,
					'Diagnostic: first leg carries filter_flag=onMollieReturn'
				)
				.toContain( 'filter_flag=onMollieReturn' );
			await expect
				.soft(
					flaggedResponse.status,
					'Diagnostic: first leg is a 302 redirect'
				)
				.toBe( 302 );
			await expect
				.soft(
					finalResponse.url,
					'Diagnostic: second leg is the clean URL'
				)
				.not.toContain( 'filter_flag' );
			await expect
				.soft(
					finalResponse.status,
					'Diagnostic: second leg is a 200 render'
				)
				.toBe( 200 );
		}
	} );

	test( 'C4567604 | The thank-you page is observed as such exactly once across the whole Mollie return redirect chain', async ( {
		utils,
		checkout,
		mollieHostedCheckout,
		orderReceived,
		visitorPage,
	} ) => {
		const responses = captureOrderReceivedResponses( visitorPage );

		const orderTotals = await countTotals( testOrder );
		testOrder.payment.amount = orderTotals.order;

		await utils.fillVisitorsCart( testOrder.products );
		await checkout.makeOrder( testOrder );
		await mollieHostedCheckout.assertUrl();
		await mollieHostedCheckout.payForOrder( testOrder.payment );
		await orderReceived.assertOrderDetails( testOrder );

		// --- The only hard, fix-agnostic gate: the thank-you page must be
		// observed by pre-template_redirect hooks exactly once per order, no
		// matter how the eventual fix reshapes the redirect itself. ---
		const hitCount = responses.filter(
			( response ) => response.hit === '1'
		).length;
		await expect(
			hitCount,
			'Assert the thank-you page is counted exactly once across the whole redirect chain, not twice'
		).toBe( 1 );
	} );
} );
