/**
 * Internal dependencies
 */
/**
 * External dependencies
 */
import { countTotals } from '@inpsyde/playwright-utils/build';
import { MollieTestData } from '../../../resources';
import {
	annotateVisitor,
	expect,
	test,
	processMolliePaymentStatus,
	updateCurrencyIfNeeded,
	assertOrderNotes,
	assertSubscriptionNotes,
} from '../../../utils';

export const testSubscriptionOrderOnClassicCheckout = (
	testData: MollieTestData.ShopOrder
) => {
	const { testId, payment, products, customer } = testData;
	const { gateway } = payment;
	const { parentOrderStatus } = testData.subscription;

	test.describe( () => {
		// Restore customer and his storage state
		test.beforeAll( async ( { utils } ) => {
			await utils.restoreCustomer( customer );
		} );

		test(
			`${ testId } | Subscription - Classic checkout - ${ gateway.name } - Payment with status ${ payment.status } creates first order with status ${ parentOrderStatus }`,
			annotateVisitor( customer ),
			async ( {
				utils,
				classicCheckout,
				orderReceived,
				customerSubscriptions,
				wooCommerceApi,
				mollieHostedCheckout,
				payForOrder,
				wooCommerceOrderEdit,
				wooCommerceSubscriptionEdit,
			} ) => {
				test.setTimeout( 2 * 60_000 );
				// Precondition: Create the initial subscription order
				await updateCurrencyIfNeeded(
					wooCommerceApi,
					gateway.currency
				);

				const orderTotals = await countTotals( testData );
				payment.amount = orderTotals.order;

				await utils.fillVisitorsCart( products );
				await classicCheckout.makeOrder( testData );

				await mollieHostedCheckout.assertUrl();
				const orderId = await mollieHostedCheckout.captureOrderNumber();
				await mollieHostedCheckout.payForOrder( payment );

				await processMolliePaymentStatus(
					{ mollieHostedCheckout, orderReceived, payForOrder },
					Number( orderId ),
					testData
				);
				// End Precondition
				// Verify initial subscription order and subscription
				const { transaction_id: transactionId } =
					await wooCommerceApi.getOrder( orderId );
				await expect(
					transactionId,
					`Assert Transaction ID ${ transactionId } is defined`
				).toBeDefined();

				const subscriptions =
					await wooCommerceApi.getSubscriptionByParentId( orderId );
				await expect(
					subscriptions.length,
					'Assert  number of subscriptions created is 1'
				).toBe( 1 );
				const subscription = subscriptions[ 0 ];

				// Assert subscription on customer subscription details page
				await customerSubscriptions.visit( subscription.id );
				await customerSubscriptions.assertUrl( subscription.id );
				await expect(
					customerSubscriptions.paymentMethod(),
					'Assert payment method on customer subscription details page'
				).toHaveText( new RegExp( payment.gateway.name ) );

				// Assert initial order details
				await wooCommerceOrderEdit.visit( orderId );
				await wooCommerceOrderEdit.assertOrderDetails(
					testData,
					transactionId
				);

				// Assert order notes via WC API
				const expectedNotes = [
					`${ gateway.slug } payment started (${ transactionId } - test mode).`,
					`Payment via ${ gateway.name } (${ transactionId }).`,
					`Order completed using Mollie - ${ gateway.name } payment (${ transactionId } - test mode).`,
				];
				await assertOrderNotes(
					wooCommerceApi,
					orderId,
					expectedNotes
				);

				// Assert subscription edit page
				await wooCommerceSubscriptionEdit.visit( subscription.id );
				await wooCommerceSubscriptionEdit.assertSubscriptionDetails(
					testData
				);

				// Assert subscription order notes via WC API
				const expectedSubscriptionNotes = [
					`Payment status marked complete.`,
					`Status changed from Pending to Active.`,
				];
				await assertSubscriptionNotes(
					wooCommerceApi,
					subscription.id,
					expectedSubscriptionNotes
				);
			}
		);
	} );
};
