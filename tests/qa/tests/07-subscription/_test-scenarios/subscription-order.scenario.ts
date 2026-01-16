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
		// Restore customer and his storage state to remove vaulted payment methods.
		// Placed in beforeAll for each test to be able to use storate state in a test.
		test.beforeAll( async ( { utils, wooCommerceApi } ) => {
			await wooCommerceApi.deleteAllSubscriptions();
			await wooCommerceApi.deleteAllOrders();
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

				const subscriptions =
					await wooCommerceApi.getSubscriptionByParentId( orderId );
				await expect( subscriptions.length ).toBe( 1 );
				const subscription = subscriptions[ 0 ];

				await customerSubscriptions.visit( subscription.id );
				await customerSubscriptions.assertUrl( subscription.id );
				await expect(
					customerSubscriptions.paymentMethod()
				).toHaveText( new RegExp( payment.gateway.name ) );

				await wooCommerceOrderEdit.assertOrderDetails(
					Number( orderId ),
					testData
				);

				await wooCommerceSubscriptionEdit.visit( subscription.id );
				await wooCommerceSubscriptionEdit.assertSubscriptionDetails(
					testData
				);
			}
		);
	} );
};
