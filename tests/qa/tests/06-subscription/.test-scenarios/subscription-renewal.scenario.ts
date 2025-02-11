/**
 * Internal dependencies
 */
import { test, expect, annotateVisitor } from '../../../utils';

export const subscriptionRenewal = ( testId: string, order ) => {
	test(
		`${ testId } | Manual renewal for order paid by ${ order.payment.gateway.name } with status ${ order.orderStatus }`,
		annotateVisitor( order.customer ),
		async ( {
			utils,
			customerSubscriptions,
			classicCheckout,
			wooCommerceApi,
			orderReceived,
			wooCommerceOrderEdit,
			wooCommerceSubscriptionEdit,
		} ) => {
			// Make tested order:
			await utils.fillVisitorsCart( order.products );
			await classicCheckout.makeOrder( order );

			// Assert Order Details page
			await orderReceived.assertOrderDetails( order );
			const orderId = await orderReceived.getOrderNumber();
			const subscriptionId = await orderReceived.getSubscriptionNumber();
			await expect( orderReceived.subscriptionStatusCell() ).toHaveText(
				'Active'
			);
			await expect(
				orderReceived.viewSubscriptionButton()
			).toBeVisible();

			// Assert My Subscriptions page
			await customerSubscriptions.visit( subscriptionId );
			await expect( customerSubscriptions.paymentMethod() ).toHaveText(
				`Via ${ order.payment.gateway.name }`
			);

			// Assert PayPal API
			const orderJson = await wooCommerceApi.getOrder( orderId );
			await wooCommerceOrderEdit.assertOrderDetails( orderId, order );
			// Assert Subscription in the dashboard
			await wooCommerceSubscriptionEdit.assertSubscriptionDetails(
				subscriptionId,
				{
					...order,
					transactionId: orderJson.transaction_id,
				}
			);
		}
	);
};
