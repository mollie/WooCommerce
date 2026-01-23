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
	processMolliePaymentStatus,
	test,
	updateCurrencyIfNeeded,
	assertOrderNotes,
	assertSubscriptionNotes,
} from '../../../utils';

export const testSubscriptionRenewal = (
	testData: MollieTestData.ShopOrder
) => {
	const { testId, payment, products, customer, currency } = testData;
	const { gateway } = payment;
	const { parentOrderStatus, renewalOrderStatus } = testData.subscription;

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
					'Assert number of subscriptions created is 1'
				).toBe( 1 );
				const [ subscription ] = subscriptions;

				// Build subscription order and related data
				const relatedParentOrder = {
					id: orderId,
					relationship: 'Parent Order',
					status: wooCommerceOrderEdit.getOrderStatusLabel(
						parentOrderStatus
					),
					total: orderTotals.order,
				};

				const relatedSubscription = {
					id: subscription.id,
					relationship: 'Subscription',
					status: 'Active',
					total: orderTotals.order,
				};

				// Assert initial order details
				await wooCommerceOrderEdit.visit( orderId );
				await wooCommerceOrderEdit.assertRelatedOrders(
					[ relatedSubscription ],
					currency
				);

				// Assert initial order notes via WC API
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

				// Assert subscription details
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

				await wooCommerceSubscriptionEdit.assertRelatedOrders(
					[ relatedParentOrder ],
					currency
				);

				const relatedRenewalOrders = [];
				for ( let i = 0; i < 2; i++ ) {
					const renewalCount = i + 1;
					const assertionPrefix = `Renewal #${ renewalCount }) `;

					// Trigger subscription renewal
					await wooCommerceSubscriptionEdit.triggerSubscriptionRenewal(
						subscription.id
					);
					// Verify renewal order was created
					const renewalOrderIds =
						await mollieApi.getSubscriptionRenewalOrderIds(
							subscription.id
						);
					await expect(
						renewalOrderIds,
						`${ assertionPrefix }Assert renewal order IDs array has length ${ renewalCount }`
					).toHaveLength( renewalCount );
					renewalOrderIds.sort( ( a, b ) => a - b ); // sort array ascending

					// Assert renewal order details (latest renewal is at index i)
					const renewalOrderId = renewalOrderIds[ i ];
					const { transaction_id: renewalTransactionId } =
						await wooCommerceApi.getOrder( renewalOrderId );
					await expect(
						renewalTransactionId,
						`${ assertionPrefix }Assert Transaction ID ${ renewalTransactionId } is defined`
					).toBeDefined();

					// Assert current renewal order
					await wooCommerceOrderEdit.visit( renewalOrderId );
					await wooCommerceOrderEdit.assertRelatedOrders(
						[
							relatedSubscription,
							relatedParentOrder,
							...relatedRenewalOrders,
						],
						currency
					);

					// Assert renewal order notes via WC API
					const expectedRenewalNotes = [
						`MOLLIE TEST MODE: URL to change payment state for renewal payment:`,
						`payment started (${ renewalTransactionId } - test mode).`, //`${gateway.slug} payment started (${renewalTransactionId} - test mode).`
						`Payment via ${ gateway.name } (${ renewalTransactionId }).`,
						`Order completed using Mollie - ${ gateway.name } payment (${ renewalTransactionId } - test mode).`,
					];
					await assertOrderNotes(
						wooCommerceApi,
						renewalOrderId,
						expectedRenewalNotes,
						{ assertionPrefix },
					);

					// Add current renewal order to the list
					relatedRenewalOrders.push( {
						id: renewalOrderId,
						relationship: 'Renewal Order',
						status: wooCommerceOrderEdit.getOrderStatusLabel(
							renewalOrderStatus
						),
						total: orderTotals.order,
					} );

					// Assert parent order has related renewal orders and subscription
					await wooCommerceOrderEdit.visit( orderId );
					await wooCommerceOrderEdit.assertRelatedOrders(
						[ relatedSubscription, ...relatedRenewalOrders ],
						currency
					);

					// Assert subscription has related parent and renewal orders
					await wooCommerceSubscriptionEdit.visit( subscription.id );
					await wooCommerceSubscriptionEdit.assertRelatedOrders(
						[ relatedParentOrder, ...relatedRenewalOrders ],
						currency
					);

					// Assert renewed subscription order notes via WC API
					// Count is based on renewalCount, plus initial subscription notes
					const expectedRenewedSubscriptionNotes = [
						{
							note: `Process renewal order action requested by admin.`,
							count: i + 1,
						},
						{
							note: `Subscription renewal payment due: Status changed from Active to On hold.`,
							count: i + 1,
						},
						`Order #${ renewalOrderId } created to record renewal.`,
						{
							note: `Payment status marked complete.`,
							count: i + 2, // +1 for initial subscription
						},
						{
							note: `Status changed from On hold to Active.`,
							count: i + 1,
						},
					];
					await assertSubscriptionNotes(
						wooCommerceApi,
						subscription.id,
						expectedRenewedSubscriptionNotes,
						{ assertionPrefix },
					);
				}

				// Assert customer subscription has related parent and renewal orders
				await customerSubscriptions.visit( subscription.id );
				await customerSubscriptions.assertRelatedOrders(
					[ relatedParentOrder, ...relatedRenewalOrders ],
					currency
				);
			}
		);
	} );
};
