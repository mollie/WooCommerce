/**
 * Incident inc-2026-10-09: an orphaned paid payment replayed on the legacy
 * webhook pays another order. Fails on 8.1.10, fixed in 8.1.11.
 * Guard permutations: tests/Integration/spec/webhooks/ForeignPaymentWebhookTest.php
 */

/**
 * External dependencies
 */
import { countTotals } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import {
	test,
	expect,
	assertOrderNotes,
	assertPaymentBelongsToOrder,
	captureMolliePaymentAttempt,
	getMollieResource,
} from '../../utils';
import {
	guests,
	products,
	shopConfigClassic,
	MollieTestData,
} from '../../resources';
import {
	baseOrder,
	cardPayment,
	payPalPayment,
} from '../05-transaction/_test-data';

const customer = guests[ cardPayment.gateway.country ];

test.beforeAll( async ( { utils, mollieApi } ) => {
	await utils.configureStore( shopConfigClassic );
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();
	await mollieApi.updateMollieGateway( 'creditcard', {
		mollie_components_enabled: 'no',
	} );
} );

test.afterAll( async ( { mollieApi } ) => {
	await mollieApi.updateMollieGateway( 'creditcard', {
		mollie_components_enabled: 'yes',
	} );
} );

test( 'C4567658 | Bug verification - Legacy webhook does not pay an order with an orphaned payment of another order @Critical', async ( {
	utils,
	classicCheckout,
	mollieHostedCheckout,
	wooCommerceApi,
	mollieClientApi,
	visitorRequest,
} ) => {
	test.setTimeout( 4 * 60_000 );
	// The cheap order gives the orphaned card payment, the expensive order is the target of the forged call
	const cheapOrderCard: MollieTestData.ShopOrder = {
		...baseOrder,
		customer,
		payment: { ...cardPayment },
	};
	const cheapOrderPayPal: MollieTestData.ShopOrder = {
		...baseOrder,
		customer,
		payment: { ...payPalPayment },
	};
	const expensiveOrder: MollieTestData.ShopOrder = {
		...baseOrder,
		customer,
		products: [ products.mollieSimple100, products.mollieVirtual100 ],
		payment: { ...cardPayment },
	};
	const { order: cheapOrderAmount } = await countTotals( cheapOrderCard );
	cheapOrderCard.payment.amount = cheapOrderAmount;
	cheapOrderPayPal.payment.amount = cheapOrderAmount;
	expensiveOrder.payment.amount = (
		await countTotals( expensiveOrder )
	).order;

	// Cheap order: card attempt left unpaid, then paid with PayPal
	await utils.fillVisitorsCart( cheapOrderCard.products );
	await classicCheckout.makeOrder( cheapOrderCard );
	const cheapOrderCardAttempt = await captureMolliePaymentAttempt(
		mollieHostedCheckout,
		wooCommerceApi
	);
	await classicCheckout.makeOrder( cheapOrderPayPal );
	const cheapOrderPayPalAttempt = await captureMolliePaymentAttempt(
		mollieHostedCheckout,
		wooCommerceApi
	);
	await expect(
		cheapOrderPayPalAttempt.orderId,
		'Assert both attempts belong to the cheap order'
	).toEqual( cheapOrderCardAttempt.orderId );
	await mollieHostedCheckout.payForOrder( cheapOrderPayPal.payment );
	await wooCommerceApi.getOrderByIdAndStatus(
		cheapOrderPayPalAttempt.orderId,
		'processing'
	);

	// Card attempt of the cheap order paid late: now linked to no order
	await mollieHostedCheckout.page.goto( cheapOrderCardAttempt.checkoutUrl );
	await mollieHostedCheckout.payForOrder( cheapOrderCard.payment );
	await expect
		.poll(
			async () =>
				(
					await getMollieResource(
						mollieClientApi,
						cheapOrderCardAttempt.transactionId
					)
				).status,
			{
				message: `Assert Mollie reports ${ cheapOrderCardAttempt.transactionId } as paid`,
				timeout: 30_000,
			}
		)
		.toBe( 'paid' );

	// Precondition: the late card payment is linked to no order, the cheap order keeps PayPal
	const cheapOrderBefore = await wooCommerceApi.getOrder(
		cheapOrderPayPalAttempt.orderId
	);
	const cheapOrderLinks = [
		cheapOrderBefore.transaction_id,
		...cheapOrderBefore.meta_data
			.filter( ( meta ) =>
				[ '_mollie_payment_id', '_mollie_order_id' ].includes(
					meta.key
				)
			)
			.map( ( meta ) => meta.value ),
	];
	await expect(
		cheapOrderLinks,
		'Assert the late card payment is not linked to the cheap order'
	).not.toContain( cheapOrderCardAttempt.transactionId );
	await expect(
		cheapOrderBefore.transaction_id,
		'Assert the cheap order is linked to the PayPal attempt'
	).toBe( cheapOrderPayPalAttempt.transactionId );

	// Expensive order: card attempt left unpaid
	await utils.fillVisitorsCart( expensiveOrder.products );
	await classicCheckout.makeOrder( expensiveOrder );
	const expensiveOrderAttempt = await captureMolliePaymentAttempt(
		mollieHostedCheckout,
		wooCommerceApi
	);
	await expect(
		expensiveOrderAttempt.orderId,
		'Assert the expensive order is a new order'
	).not.toEqual( cheapOrderCardAttempt.orderId );
	const { order_key: expensiveOrderKey } = await wooCommerceApi.getOrder(
		expensiveOrderAttempt.orderId
	);

	// Forged call: the expensive order's id and key, the cheap order's card payment in the body.
	// Sent in the path form from the incident report and in the query form (same WC handler).
	const gatewayId = cardPayment.gateway.settings.id;
	const orderParams = `order_id=${ expensiveOrderAttempt.orderId }&key=${ expensiveOrderKey }`;
	for ( const webhookUrl of [
		`/wc-api/${ gatewayId }/?${ orderParams }`,
		`/?wc-api=${ gatewayId }&${ orderParams }`,
	] ) {
		const response = await visitorRequest.post( webhookUrl, {
			form: { id: cheapOrderCardAttempt.transactionId },
		} );
		await expect
			.soft(
				response.status(),
				`Assert the legacy webhook ${ webhookUrl } ends without a server error`
			)
			.toBe( 200 );
	}

	// The expensive order is untouched
	const expensiveOrderAfter = await wooCommerceApi.getOrder(
		expensiveOrderAttempt.orderId
	);
	await expect(
		expensiveOrderAfter.status,
		'Assert the expensive order is still pending payment'
	).toBe( 'pending' );
	await expect(
		expensiveOrderAfter.transaction_id,
		'Assert the expensive order is still linked to its own attempt'
	).toBe( expensiveOrderAttempt.transactionId );
	await assertOrderNotes(
		wooCommerceApi,
		expensiveOrderAttempt.orderId,
		[ { note: /Order completed using Mollie/, count: 0 } ],
		{ isSoftAssertion: false }
	);
	await assertPaymentBelongsToOrder(
		{ mollieClientApi, wooCommerceApi },
		expensiveOrderAttempt.orderId
	);

	// The cheap order is untouched
	const cheapOrderAfter = await wooCommerceApi.getOrder(
		cheapOrderPayPalAttempt.orderId
	);
	await expect(
		cheapOrderAfter.status,
		'Assert the cheap order is still processing'
	).toBe( 'processing' );
	await expect(
		cheapOrderAfter.transaction_id,
		'Assert the cheap order is still linked to the PayPal attempt'
	).toBe( cheapOrderPayPalAttempt.transactionId );
} );
