/**
 * Webhook updates only the order a Mollie payment was created for.
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
import { guests, products, MollieTestData } from '../../resources';
import {
	baseOrder,
	cardPayment,
	payPalPayment,
} from '../05-transaction/_test-data';

const customer = guests[ cardPayment.gateway.country ];

// Card components are disabled by the `transaction:payment-ownership` shard
// setup (tests/qa/project-shards.ts); this spec only needs classic checkout.
test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( { enableClassicPages: true } );
} );

test( 'C4567658 | Webhook - Payment created for another order does not change the order @Critical', async ( {
	utils,
	classicCheckout,
	mollieHostedCheckout,
	wooCommerceApi,
	mollieClientApi,
	visitorRequest,
} ) => {
	test.setTimeout( 4 * 60_000 );
	// Order A (cheap): gives a paid card payment linked to no order
	// Order B (more expensive): the order the webhook call names
	const orderACard: MollieTestData.ShopOrder = {
		...baseOrder,
		customer,
		payment: { ...cardPayment },
	};
	const orderAPayPal: MollieTestData.ShopOrder = {
		...baseOrder,
		customer,
		payment: { ...payPalPayment },
	};
	const orderB: MollieTestData.ShopOrder = {
		...baseOrder,
		customer,
		products: [ products.mollieSimple100, products.mollieVirtual100 ],
		payment: { ...cardPayment },
	};
	const { order: orderAAmount } = await countTotals( orderACard );
	orderACard.payment.amount = orderAAmount;
	orderAPayPal.payment.amount = orderAAmount;
	orderB.payment.amount = ( await countTotals( orderB ) ).order;

	// Order A: card attempt left unpaid, then paid with PayPal
	await utils.fillVisitorsCart( orderACard.products );
	await classicCheckout.makeOrder( orderACard );
	const orderACardAttempt = await captureMolliePaymentAttempt(
		mollieHostedCheckout,
		wooCommerceApi
	);
	await classicCheckout.makeOrder( orderAPayPal );
	const orderAPayPalAttempt = await captureMolliePaymentAttempt(
		mollieHostedCheckout,
		wooCommerceApi
	);
	await expect(
		orderAPayPalAttempt.orderId,
		'Assert both attempts belong to order A'
	).toEqual( orderACardAttempt.orderId );
	await mollieHostedCheckout.payForOrder( orderAPayPal.payment );
	await wooCommerceApi.getOrderByIdAndStatus(
		orderAPayPalAttempt.orderId,
		'processing'
	);

	// Card attempt of order A paid late: now linked to no order
	await mollieHostedCheckout.page.goto( orderACardAttempt.checkoutUrl );
	await mollieHostedCheckout.payForOrder( orderACard.payment );
	await expect
		.poll(
			async () =>
				(
					await getMollieResource(
						mollieClientApi,
						orderACardAttempt.transactionId
					)
				).status,
			{
				message: `Assert Mollie reports ${ orderACardAttempt.transactionId } as paid`,
				timeout: 30_000,
			}
		)
		.toBe( 'paid' );

	// Precondition: the late card payment is linked to no order, order A keeps PayPal
	const orderABefore = await wooCommerceApi.getOrder(
		orderAPayPalAttempt.orderId
	);
	const orderALinks = [
		orderABefore.transaction_id,
		...orderABefore.meta_data
			.filter( ( meta ) =>
				[ '_mollie_payment_id', '_mollie_order_id' ].includes(
					meta.key
				)
			)
			.map( ( meta ) => meta.value ),
	];
	await expect(
		orderALinks,
		'Assert the late card payment is not linked to order A'
	).not.toContain( orderACardAttempt.transactionId );
	await expect(
		orderABefore.transaction_id,
		'Assert order A is linked to the PayPal attempt'
	).toBe( orderAPayPalAttempt.transactionId );

	// Order B: card attempt left unpaid
	await utils.fillVisitorsCart( orderB.products );
	await classicCheckout.makeOrder( orderB );
	const orderBCardAttempt = await captureMolliePaymentAttempt(
		mollieHostedCheckout,
		wooCommerceApi
	);
	await expect(
		orderBCardAttempt.orderId,
		'Assert order B is a new order'
	).not.toEqual( orderACardAttempt.orderId );
	const { order_key: orderBKey } = await wooCommerceApi.getOrder(
		orderBCardAttempt.orderId
	);

	// Webhook call with order B's id and key and order A's card payment.
	// Sent in the path form and in the query form (same WC handler).
	const gatewayId = cardPayment.gateway.settings.id;
	const orderParams = `order_id=${ orderBCardAttempt.orderId }&key=${ orderBKey }`;
	for ( const webhookUrl of [
		`/wc-api/${ gatewayId }/?${ orderParams }`,
		`/?wc-api=${ gatewayId }&${ orderParams }`,
	] ) {
		const response = await visitorRequest.post( webhookUrl, {
			form: { id: orderACardAttempt.transactionId },
		} );
		await expect
			.soft(
				response.status(),
				`Assert the legacy webhook ${ webhookUrl } ends without a server error`
			)
			.toBe( 200 );
	}

	// Order B is untouched
	const orderBAfter = await wooCommerceApi.getOrder(
		orderBCardAttempt.orderId
	);
	await expect(
		orderBAfter.status,
		'Assert order B is still pending payment'
	).toBe( 'pending' );
	await expect(
		orderBAfter.transaction_id,
		'Assert order B is still linked to its own attempt'
	).toBe( orderBCardAttempt.transactionId );
	await assertOrderNotes(
		wooCommerceApi,
		orderBCardAttempt.orderId,
		[ { note: /Order completed using Mollie/, count: 0 } ],
		{ isSoftAssertion: false }
	);
	await assertPaymentBelongsToOrder(
		{ mollieClientApi, wooCommerceApi },
		orderBCardAttempt.orderId
	);

	// Order A is untouched
	const orderAAfter = await wooCommerceApi.getOrder(
		orderAPayPalAttempt.orderId
	);
	await expect(
		orderAAfter.status,
		'Assert order A is still processing'
	).toBe( 'processing' );
	await expect(
		orderAAfter.transaction_id,
		'Assert order A is still linked to the PayPal attempt'
	).toBe( orderAPayPalAttempt.transactionId );
} );
