/**
 * External dependencies
 */
import { countTotals, expect } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import {
	test,
	processMolliePaymentStatus,
	updateCurrencyIfNeeded,
	getOrderStatusFromMollieStatus,
	assertOrderNotes,
	TestBaseExtend,
} from '../../../utils';
import { MollieTestData, guests } from '../../../resources';

/**
 * Shared setup: resolves order status/currency/totals and skips the test if
 * the current Mollie API method doesn't support the gateway - identical
 * groundwork the regular gateway transaction scenarios do before checkout.
 */
const preparePayPalExpressOrder = async (
	testData: MollieTestData.ShopOrder,
	wooCommerceApi: TestBaseExtend[ 'wooCommerceApi' ],
	mollieApiMethod: TestBaseExtend[ 'mollieApiMethod' ]
) => {
	const { payment } = testData;
	const { gateway } = payment;

	test.skip(
		! gateway.availableForApiMethods.includes( mollieApiMethod ),
		`Test is not eligible for ${ mollieApiMethod } API method.`
	);

	const customer = guests[ gateway.country ];
	Object.assign( testData, { customer, currency: gateway.currency } );

	if ( ! testData.orderStatus ) {
		testData.orderStatus = await getOrderStatusFromMollieStatus(
			payment.status,
			mollieApiMethod
		);
	}

	await updateCurrencyIfNeeded( wooCommerceApi, gateway.currency );

	const orderTotals = await countTotals( testData );
	payment.amount = orderTotals.order;
};

/**
 * Shared tail: pays at Mollie for the order the Express button just created,
 * then asserts order status/transaction id/notes - identical to the regular
 * gateway transaction scenarios, since the Express button hands off to the
 * exact same order-processing code path once redirected to Mollie.
 */
const completePayPalExpressOrder = async (
	{
		wooCommerceApi,
		mollieHostedCheckout,
		orderReceived,
		payForOrder,
		wooCommerceOrderEdit,
	}: Pick<
		TestBaseExtend,
		| 'wooCommerceApi'
		| 'mollieHostedCheckout'
		| 'orderReceived'
		| 'payForOrder'
		| 'wooCommerceOrderEdit'
	>,
	testData: MollieTestData.ShopOrder
) => {
	const { payment } = testData;
	const { gateway } = payment;

	await mollieHostedCheckout.assertUrl();
	const orderId = await mollieHostedCheckout.captureOrderNumber();
	await mollieHostedCheckout.payForOrder( payment );

	await processMolliePaymentStatus(
		{ mollieHostedCheckout, orderReceived, payForOrder },
		Number( orderId ),
		testData
	);

	const { transaction_id: transactionId } =
		await wooCommerceApi.getOrder( orderId );
	await expect(
		transactionId,
		`Assert transaction ID ${ transactionId } is defined`
	).toBeDefined();

	await wooCommerceOrderEdit.visit( orderId );
	await wooCommerceOrderEdit.assertOrderDetails( testData, transactionId );

	if ( payment.status === 'paid' ) {
		const expectedNotes = [
			`${ gateway.slug } payment started (${ transactionId } - test mode).`,
			`Payment via ${ gateway.name } (${ transactionId }).`,
			`Order completed using Mollie - ${ gateway.name } payment (${ transactionId } - test mode).`,
		];
		await assertOrderNotes( wooCommerceApi, orderId, expectedNotes );
	}
};

export const testPayPalExpressProduct = (
	testData: MollieTestData.ShopOrder
) => {
	const { testId, testLabel, payment } = testData;
	const label = testLabel ? ` ${ testLabel }` : '';

	test( `${ testId } | Transaction - Product - PayPal Express - Payment status ${ payment.status } creates order with expected status${ label }`, async ( {
		wooCommerceApi,
		product,
		mollieHostedCheckout,
		orderReceived,
		payForOrder,
		wooCommerceOrderEdit,
		mollieApiMethod,
	} ) => {
		await preparePayPalExpressOrder(
			testData,
			wooCommerceApi,
			mollieApiMethod
		);

		await product.visit( testData.products[ 0 ].slug );
		await expect(
			product.payPalExpressButton(),
			'Assert PayPal Express button is visible on Product page'
		).toBeVisible();
		await product.payPalExpressButton().click();

		await completePayPalExpressOrder(
			{
				wooCommerceApi,
				mollieHostedCheckout,
				orderReceived,
				payForOrder,
				wooCommerceOrderEdit,
			},
			testData
		);
	} );
};

export const testPayPalExpressCart = ( testData: MollieTestData.ShopOrder ) => {
	const { testId, testLabel, payment } = testData;
	const label = testLabel ? ` ${ testLabel }` : '';

	test( `${ testId } | Transaction - Cart - PayPal Express - Payment status ${ payment.status } creates order with expected status${ label }`, async ( {
		wooCommerceApi,
		utils,
		cart,
		mollieHostedCheckout,
		orderReceived,
		payForOrder,
		wooCommerceOrderEdit,
		mollieApiMethod,
	} ) => {
		await preparePayPalExpressOrder(
			testData,
			wooCommerceApi,
			mollieApiMethod
		);

		await utils.fillVisitorsCart( testData.products );
		await cart.visit();
		await expect(
			cart.payPalExpressButton(),
			'Assert PayPal Express button is visible on Cart page'
		).toBeVisible();
		await cart.payPalExpressButton().click();

		await completePayPalExpressOrder(
			{
				wooCommerceApi,
				mollieHostedCheckout,
				orderReceived,
				payForOrder,
				wooCommerceOrderEdit,
			},
			testData
		);
	} );
};

export const testPayPalExpressCheckout = (
	testData: MollieTestData.ShopOrder
) => {
	const { testId, testLabel, payment } = testData;
	const label = testLabel ? ` ${ testLabel }` : '';

	test( `${ testId } | Transaction - Checkout - PayPal Express - Payment status ${ payment.status } creates order with expected status${ label }`, async ( {
		wooCommerceApi,
		utils,
		checkout,
		mollieHostedCheckout,
		orderReceived,
		payForOrder,
		wooCommerceOrderEdit,
		mollieApiMethod,
	} ) => {
		await preparePayPalExpressOrder(
			testData,
			wooCommerceApi,
			mollieApiMethod
		);

		await utils.fillVisitorsCart( testData.products );
		await checkout.visit();
		await expect(
			checkout.payPalExpressButton(),
			'Assert PayPal Express button is visible on Checkout page'
		).toBeVisible();
		await checkout.payPalExpressButton().click();

		await completePayPalExpressOrder(
			{
				wooCommerceApi,
				mollieHostedCheckout,
				orderReceived,
				payForOrder,
				wooCommerceOrderEdit,
			},
			testData
		);
	} );
};
