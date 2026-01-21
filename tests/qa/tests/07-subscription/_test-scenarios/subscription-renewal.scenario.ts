/**
 * Internal dependencies
 */
import { countTotals } from '@inpsyde/playwright-utils/build';
import { MollieTestData } from '../../../resources';
import { annotateVisitor, expect, processMolliePaymentStatus, test, updateCurrencyIfNeeded } from '../../../utils';

export const testSubscriptionRenewal = ( testData: MollieTestData.ShopOrder ) => {
	const { testId, payment, products, subscription, customer, currency } = testData;	
	const { gateway } = payment;
	const { parentOrderStatus, renewalOrderStatus } = subscription;

	test.describe( () => {
		// Restore customer and his storage state
		test.beforeAll( async ( { utils } ) => {
			await utils.restoreCustomer( customer );
		} );

		test(
			`${ testId } | Subscription - ${ gateway.name } - Manually renewed orders have status ${ renewalOrderStatus }`,
			annotateVisitor( customer ),
			async ( {
				utils,
				classicCheckout,
				mollieHostedCheckout,
				orderReceived,
				payForOrder,
				wooCommerceApi,
				mollieApi,
				wooCommerceOrderEdit,
				wooCommerceSubscriptionEdit,
				customerSubscriptions,
			} ) => {
				test.setTimeout( 2 * 60_000 );
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
				await expect(
					subscriptions.length,
					'Number of subscriptions created is not 1'
				).toBe( 1 );
				const subscription = subscriptions[ 0 ];

				const relatedParentOrder = {
					id: orderId,
					relationship: 'Parent Order',
					status:
						wooCommerceOrderEdit.getOrderStatusLabel( parentOrderStatus ),
					total: orderTotals.order,
				};

				const relatedSubscription = {
					id: subscription.id,
					relationship: 'Subscription',
					status: 'Active',
					total: orderTotals.order,
				};

				await wooCommerceOrderEdit.visit( orderId );
				await wooCommerceOrderEdit.assertRelatedOrders(
					[ relatedSubscription ],
					currency
				);

				await wooCommerceSubscriptionEdit.visit( subscription.id );
				await wooCommerceSubscriptionEdit.assertSubscriptionDetails(
					testData
				);
				await wooCommerceSubscriptionEdit.assertRelatedOrders(
					[ relatedParentOrder ],
					currency
				);

				// Subscription renewal
				await wooCommerceSubscriptionEdit.triggerSubscriptionRenewal(
					subscription.id
				);

				const renewalOrderIds =
					await mollieApi.getSubscriptionRenewalOrderIds(
						subscription.id
					);
				await expect( renewalOrderIds ).toHaveLength( 1 );

				const relatedRenewalOrders = [];

				for ( const renewalOrderId of renewalOrderIds ) {
					relatedRenewalOrders.push( {
						id: renewalOrderId,
						relationship: 'Renewal Order',
						status:
							wooCommerceOrderEdit.getOrderStatusLabel( renewalOrderStatus ),
						total: orderTotals.order,
					} );
				}

				await wooCommerceOrderEdit.visit( orderId );
				await wooCommerceOrderEdit.assertRelatedOrders(
					[ relatedSubscription, ...relatedRenewalOrders ],
					currency
				);

				await wooCommerceSubscriptionEdit.visit( subscription.id );
				await wooCommerceSubscriptionEdit.assertRelatedOrders(
					[ relatedParentOrder, ...relatedRenewalOrders ],
					currency
				);

				await customerSubscriptions.visit( subscription.id );
				await customerSubscriptions.assertRelatedOrders(
					[ relatedParentOrder, ...relatedRenewalOrders ],
					currency
				);
			}
		);
	} );
};
