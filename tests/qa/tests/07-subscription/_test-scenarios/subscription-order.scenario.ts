/**
 * Internal dependencies
 */
import { countTotals } from '@inpsyde/playwright-utils/build';
import { MollieTestData } from '../../../resources';
import {
	annotateVisitor,
	expect,
	test,
	processMolliePaymentStatus,
	updateCurrencyIfNeeded,
} from '../../../utils';

export const testSubscriptionOrderOnClassicCheckout = ( testData: MollieTestData.ShopOrder ) => {
	const { testId, payment, products, subscription, customer } = testData;	
	const { gateway } = payment;
	const { parentOrderStatus } = subscription;

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
				await updateCurrencyIfNeeded( wooCommerceApi, gateway.currency );

				const orderTotals = await countTotals( testData );
				payment.amount = orderTotals.order;

				await utils.fillVisitorsCart( products );
				await classicCheckout.makeOrder( testData );

				const orderId = await mollieHostedCheckout.pay( payment );

				await processMolliePaymentStatus(
					{ mollieHostedCheckout, orderReceived, payForOrder },
					Number( orderId ),
					testData
				);

				const { transaction_id: transactionId } =
					await wooCommerceApi.getOrder( orderId );
				await expect( transactionId, `Transaction ID ${ transactionId }` ).toBeDefined();

				const subscriptions =
					await wooCommerceApi.getSubscriptionByParentId( orderId );
				await expect(
					subscriptions.length,
					'Number of subscriptions created is not 1'
				).toBe( 1 );
				const subscription = subscriptions[ 0 ];

				await customerSubscriptions.visit( subscription.id );
				await customerSubscriptions.assertUrl( subscription.id );
				await expect(
					customerSubscriptions.paymentMethod(),
					'Payment method on subscription details page is incorrect'
				).toHaveText( new RegExp( payment.gateway.name ) );

				await wooCommerceOrderEdit.visit( orderId );
				await wooCommerceOrderEdit.assertOrderDetails(
					testData,
					transactionId,
				);

				await wooCommerceSubscriptionEdit.visit( subscription.id );
				await wooCommerceSubscriptionEdit.assertSubscriptionDetails(
					testData
				);
			}
		);
	} );
};
