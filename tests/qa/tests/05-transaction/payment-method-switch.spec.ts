/**
 * Shopper switches the payment method on the same order (two Mollie attempts).
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
	getOrderStatusFromMollieStatus,
} from '../../utils';
import { guests, shopConfigDefault, MollieTestData } from '../../resources';
import {
	baseOrder,
	cardPayment,
	payPalPayment,
	paymentMethodSwitch,
} from './_test-data';

const customer = guests[ cardPayment.gateway.country ];

test.beforeAll( async ( { utils, mollieApi } ) => {
	await utils.configureStore( shopConfigDefault );
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

for ( const {
	testId,
	testLabel,
	checkoutType,
	firstPayment,
	secondPayment,
} of paymentMethodSwitch ) {
	const label = testLabel ? ` ${ testLabel }` : '';
	const checkoutLabel =
		checkoutType === 'classic' ? 'Classic checkout' : 'Checkout';

	test.describe( () => {
		test.beforeAll( async ( { utils } ) => {
			await utils.configureStore( {
				enableClassicPages: checkoutType === 'classic',
			} );
		} );

		test( `${ testId } | Transaction - ${ checkoutLabel } - Payment method switch - ${ firstPayment.gateway.name } to ${ secondPayment.gateway.name } - Order is paid with the second attempt${ label }`, async ( {
			utils,
			checkout,
			classicCheckout,
			mollieHostedCheckout,
			wooCommerceApi,
			mollieClientApi,
			isMultistepCheckout,
		} ) => {
			test.setTimeout( 3 * 60_000 );
			const firstOrder: MollieTestData.ShopOrder = {
				...baseOrder,
				customer,
				payment: { ...firstPayment },
			};
			const secondOrder: MollieTestData.ShopOrder = {
				...baseOrder,
				customer,
				payment: { ...secondPayment },
			};
			const { order: amount } = await countTotals( firstOrder );
			firstOrder.payment.amount = amount;
			secondOrder.payment.amount = amount;

			const checkoutPage =
				checkoutType === 'classic' ? classicCheckout : checkout;
			const makeOrder = isMultistepCheckout
				? checkoutPage.makeMultistepOrder
				: checkoutPage.makeOrder;

			await utils.fillVisitorsCart( firstOrder.products );
			await makeOrder( firstOrder );
			const firstAttempt = await captureMolliePaymentAttempt(
				mollieHostedCheckout,
				wooCommerceApi
			);

			// Shopper goes back from Mollie and places the same order with another method
			await makeOrder( secondOrder );
			const secondAttempt = await captureMolliePaymentAttempt(
				mollieHostedCheckout,
				wooCommerceApi
			);
			await expect(
				secondAttempt.orderId,
				'Assert the second attempt belongs to the same order'
			).toEqual( firstAttempt.orderId );
			await expect(
				secondAttempt.transactionId,
				'Assert the second attempt is a new Mollie payment'
			).not.toEqual( firstAttempt.transactionId );

			await mollieHostedCheckout.payForOrder( secondOrder.payment );

			const order = await wooCommerceApi.getOrderByIdAndStatus(
				secondAttempt.orderId,
				'processing'
			);
			await expect(
				order.transaction_id,
				'Assert order is linked to the second attempt'
			).toBe( secondAttempt.transactionId );
			await assertPaymentBelongsToOrder(
				{ mollieClientApi, wooCommerceApi },
				secondAttempt.orderId
			);
			await assertOrderNotes( wooCommerceApi, secondAttempt.orderId, [
				{
					note: new RegExp(
						`Order completed using Mollie .*${ secondAttempt.transactionId }`
					),
					count: 1,
				},
				{
					note: new RegExp(
						`Order completed using Mollie .*${ firstAttempt.transactionId }`
					),
					count: 0,
				},
			] );

			// Mollie may not allow cancelling the first attempt
			const firstAttemptAtMollie = await getMollieResource(
				mollieClientApi,
				firstAttempt.transactionId
			);
			await expect
				.soft(
					[ 'open', 'canceled', 'created' ],
					`Assert first attempt ${ firstAttempt.transactionId } is not paid (status: ${ firstAttemptAtMollie.status })`
				)
				.toContain( firstAttemptAtMollie.status );
		} );
	} );
}

test.describe( () => {
	test.beforeAll( async ( { utils } ) => {
		await utils.configureStore( { enableClassicPages: true } );
	} );

	test( 'C4567662 | Transaction - Classic checkout - Payment method switch - First attempt paid late while order is unpaid - Order is paid with the first attempt', async ( {
		utils,
		classicCheckout,
		mollieHostedCheckout,
		wooCommerceApi,
		mollieClientApi,
		isMultistepCheckout,
	} ) => {
		test.setTimeout( 3 * 60_000 );
		const cardOrder: MollieTestData.ShopOrder = {
			...baseOrder,
			customer,
			payment: { ...cardPayment },
		};
		const payPalOrder: MollieTestData.ShopOrder = {
			...baseOrder,
			customer,
			payment: { ...payPalPayment },
		};
		const { order: amount } = await countTotals( cardOrder );
		cardOrder.payment.amount = amount;
		payPalOrder.payment.amount = amount;

		const makeOrder = isMultistepCheckout
			? classicCheckout.makeMultistepOrder
			: classicCheckout.makeOrder;

		// Both attempts are left unpaid
		await utils.fillVisitorsCart( cardOrder.products );
		await makeOrder( cardOrder );
		const cardAttempt = await captureMolliePaymentAttempt(
			mollieHostedCheckout,
			wooCommerceApi
		);
		await makeOrder( payPalOrder );
		await captureMolliePaymentAttempt(
			mollieHostedCheckout,
			wooCommerceApi
		);

		// Shopper goes back to the first attempt and pays it
		await mollieHostedCheckout.page.goto( cardAttempt.checkoutUrl );
		await mollieHostedCheckout.payForOrder( cardOrder.payment );

		const order = await wooCommerceApi.getOrderByIdAndStatus(
			cardAttempt.orderId,
			'processing'
		);
		await expect(
			order.transaction_id,
			'Assert order is linked to the paid first attempt'
		).toBe( cardAttempt.transactionId );
		await assertPaymentBelongsToOrder(
			{ mollieClientApi, wooCommerceApi },
			cardAttempt.orderId
		);
		// TODO: assert the full notes once fixed (dev ticket pending): the method title is empty
		// ("Order completed using  payment"), "Payment via PayPal" is shown for the card payment,
		// and a later "payment still pending" note names the unused PayPal attempt
		await assertOrderNotes( wooCommerceApi, cardAttempt.orderId, [
			{
				note: new RegExp(
					`Order completed using .*${ cardAttempt.transactionId }`
				),
				count: 1,
			},
		] );
	} );

	test( 'C4567663 | Transaction - Classic checkout - Payment method switch - First attempt paid late after order is paid - Order stays paid with the second attempt', async ( {
		utils,
		classicCheckout,
		mollieHostedCheckout,
		wooCommerceApi,
		mollieClientApi,
		isMultistepCheckout,
	} ) => {
		test.setTimeout( 3 * 60_000 );
		const cardOrder: MollieTestData.ShopOrder = {
			...baseOrder,
			customer,
			payment: { ...cardPayment },
		};
		const payPalOrder: MollieTestData.ShopOrder = {
			...baseOrder,
			customer,
			payment: { ...payPalPayment },
		};
		const { order: amount } = await countTotals( cardOrder );
		cardOrder.payment.amount = amount;
		payPalOrder.payment.amount = amount;

		const makeOrder = isMultistepCheckout
			? classicCheckout.makeMultistepOrder
			: classicCheckout.makeOrder;

		// Card attempt left unpaid, order paid with PayPal
		await utils.fillVisitorsCart( cardOrder.products );
		await makeOrder( cardOrder );
		const cardAttempt = await captureMolliePaymentAttempt(
			mollieHostedCheckout,
			wooCommerceApi
		);
		await makeOrder( payPalOrder );
		const payPalAttempt = await captureMolliePaymentAttempt(
			mollieHostedCheckout,
			wooCommerceApi
		);
		await mollieHostedCheckout.payForOrder( payPalOrder.payment );
		await wooCommerceApi.getOrderByIdAndStatus(
			payPalAttempt.orderId,
			'processing'
		);

		// Shopper also pays the card attempt: the same order is paid twice
		await mollieHostedCheckout.page.goto( cardAttempt.checkoutUrl );
		await mollieHostedCheckout.payForOrder( cardOrder.payment );
		await expect
			.poll(
				async () =>
					(
						await getMollieResource(
							mollieClientApi,
							cardAttempt.transactionId
						)
					).status,
				{
					message: `Assert Mollie reports ${ cardAttempt.transactionId } as paid`,
					timeout: 30_000,
				}
			)
			.toBe( 'paid' );

		const order = await wooCommerceApi.getOrder( payPalAttempt.orderId );
		await expect( order.status, 'Assert order is still processing' ).toBe(
			'processing'
		);
		await expect(
			order.transaction_id,
			'Assert order is still linked to the PayPal attempt'
		).toBe( payPalAttempt.transactionId );
		await assertPaymentBelongsToOrder(
			{ mollieClientApi, wooCommerceApi },
			payPalAttempt.orderId
		);
		await assertOrderNotes( wooCommerceApi, payPalAttempt.orderId, [
			{ note: /Order completed using /, count: 1 },
		] );
		// TODO: expected handling of the second payment is an open dev question
		// (order note, automatic refund or admin notice). Today only a debug log is written.
	} );
} );

// Retry with the same method
test.describe( () => {
	test.beforeAll( async ( { utils } ) => {
		await utils.configureStore( { enableClassicPages: true } );
	} );

	test( 'C4567664 | Transaction - Classic checkout - Payment retry with the same method - Card - Order is paid once', async ( {
		utils,
		classicCheckout,
		mollieHostedCheckout,
		wooCommerceApi,
		mollieClientApi,
		mollieApiMethod,
		isMultistepCheckout,
	} ) => {
		test.setTimeout( 3 * 60_000 );
		const cardOrder: MollieTestData.ShopOrder = {
			...baseOrder,
			customer,
			payment: { ...cardPayment },
		};
		cardOrder.payment.amount = ( await countTotals( cardOrder ) ).order;

		const makeOrder = isMultistepCheckout
			? classicCheckout.makeMultistepOrder
			: classicCheckout.makeOrder;

		await utils.fillVisitorsCart( cardOrder.products );
		await makeOrder( cardOrder );
		const firstAttempt = await captureMolliePaymentAttempt(
			mollieHostedCheckout,
			wooCommerceApi
		);

		// Shopper goes back from Mollie and places the same order with the same method
		await makeOrder( cardOrder );
		const retryAttempt = await captureMolliePaymentAttempt(
			mollieHostedCheckout,
			wooCommerceApi
		);
		await expect(
			retryAttempt.orderId,
			'Assert the retry belongs to the same order'
		).toEqual( firstAttempt.orderId );
		// Payments API: an open card payment cannot be cancelled, so the plugin reuses it
		if ( mollieApiMethod === 'payment' ) {
			await expect(
				retryAttempt.transactionId,
				'Assert the open card payment is reused'
			).toBe( firstAttempt.transactionId );
		}

		await mollieHostedCheckout.payForOrder( cardOrder.payment );

		const order = await wooCommerceApi.getOrderByIdAndStatus(
			retryAttempt.orderId,
			'processing'
		);
		await expect(
			order.transaction_id,
			'Assert order is linked to the paid attempt'
		).toBe( retryAttempt.transactionId );
		await assertPaymentBelongsToOrder(
			{ mollieClientApi, wooCommerceApi },
			retryAttempt.orderId
		);
		await assertOrderNotes( wooCommerceApi, retryAttempt.orderId, [
			{ note: /Order completed using /, count: 1 },
		] );
	} );
} );

// Switch from the pay-for-order page after a failed payment.
// Block pages: the pay-for-order page object opens the block checkout order-pay URL.
test.describe( () => {
	test.beforeAll( async ( { utils } ) => {
		await utils.configureStore( { enableClassicPages: false } );
	} );

	test( 'C4567665 | Transaction - Pay for order - Payment method switch after a failed payment - Card to PayPal - Order is paid with PayPal', async ( {
		utils,
		checkout,
		payForOrder,
		mollieHostedCheckout,
		wooCommerceApi,
		mollieClientApi,
		mollieApiMethod,
		isMultistepCheckout,
	} ) => {
		test.setTimeout( 3 * 60_000 );
		const cardOrder: MollieTestData.ShopOrder = {
			...baseOrder,
			customer,
			payment: { ...cardPayment, status: 'failed' },
		};
		const payPalOrder: MollieTestData.ShopOrder = {
			...baseOrder,
			customer,
			payment: { ...payPalPayment },
		};
		const { order: amount } = await countTotals( cardOrder );
		cardOrder.payment.amount = amount;
		payPalOrder.payment.amount = amount;

		const makeOrder = isMultistepCheckout
			? checkout.makeMultistepOrder
			: checkout.makeOrder;

		// Card payment fails: Mollie sends the shopper to the pay-for-order page
		await utils.fillVisitorsCart( cardOrder.products );
		await makeOrder( cardOrder );
		const cardAttempt = await captureMolliePaymentAttempt(
			mollieHostedCheckout,
			wooCommerceApi
		);
		await mollieHostedCheckout.payForOrder( cardOrder.payment );
		await expect(
			payForOrder.heading(),
			'Assert pay for order page is shown'
		).toBeVisible();
		await wooCommerceApi.getOrderByIdAndStatus(
			cardAttempt.orderId,
			getOrderStatusFromMollieStatus( 'failed', mollieApiMethod )
		);

		// Shopper pays the same order with PayPal from the pay-for-order page
		const { order_key: orderKey } = await wooCommerceApi.getOrder(
			cardAttempt.orderId
		);
		await payForOrder.makeOrder(
			cardAttempt.orderId,
			orderKey,
			payPalOrder
		);
		const payPalAttempt = await captureMolliePaymentAttempt(
			mollieHostedCheckout,
			wooCommerceApi
		);
		await expect(
			payPalAttempt.orderId,
			'Assert the PayPal attempt belongs to the same order'
		).toEqual( cardAttempt.orderId );
		await mollieHostedCheckout.payForOrder( payPalOrder.payment );

		const order = await wooCommerceApi.getOrderByIdAndStatus(
			payPalAttempt.orderId,
			'processing'
		);
		await expect(
			order.transaction_id,
			'Assert order is linked to the PayPal attempt'
		).toBe( payPalAttempt.transactionId );
		await assertPaymentBelongsToOrder(
			{ mollieClientApi, wooCommerceApi },
			payPalAttempt.orderId
		);
		await assertOrderNotes( wooCommerceApi, payPalAttempt.orderId, [
			{
				note: new RegExp(
					`Order completed using Mollie .*${ payPalAttempt.transactionId }`
				),
				count: 1,
			},
			{
				note: new RegExp(
					`Order completed using Mollie .*${ cardAttempt.transactionId }`
				),
				count: 0,
			},
		] );
	} );
} );
