/**
 * Re-checks PIWOO-842 on the 8.2.0 package: an order with a line below zero or
 * at exactly zero can be paid, and the Mollie amount equals the order total.
 * The order is created over REST and paid on the order-pay page.
 *
 * Mollie 8.2.0 Phase 2 test cases: P1-01, P1-02.
 */

/**
 * Internal dependencies
 */
import {
	test,
	expect,
	updateCurrencyIfNeeded,
	assertPaymentBelongsToOrder,
} from '../../utils';
import { gateways, orders, products, MollieTestData } from '../../resources';

const freeProduct = {
	...products.mollieSimple100,
	name: 'QA PIWOO-842 Free Product',
	slug: 'qa-piwoo-842-free',
	regular_price: '0.00',
};

const baseOrder = {
	...orders.default,
	payment: { gateway: gateways.ideal, status: 'paid', bankIssuer: 'ING' },
};

const testCases: [ string, MollieTestData.ShopOrder ][] = [
	[
		'C4567667 | Order with a line below zero is paid with the order total',
		{
			...baseOrder,
			products: [ products.mollieSimple100 ],
			fees: [
				{
					name: 'QA negative line',
					total: '-10.00',
					tax_status: 'none',
				},
			],
		} as unknown as MollieTestData.ShopOrder,
	],
	[
		'C4567668 | Order with a line at zero is paid with the order total',
		{
			...baseOrder,
			products: [ products.mollieSimple100, freeProduct ],
		} as unknown as MollieTestData.ShopOrder,
	],
];

test.beforeAll( async ( { wooCommerceApi } ) => {
	await updateCurrencyIfNeeded( wooCommerceApi, 'EUR' );
} );

for ( const [ title, testData ] of testCases ) {
	test(
		title,
		async ( {
			wooCommerceUtils,
			wooCommerceApi,
			mollieClientApi,
			payForOrder,
			mollieHostedCheckout,
		} ) => {
			const order = await wooCommerceUtils.createApiOrder( testData );
			testData.payment.amount = Number( order.total );

			await payForOrder.makeOrder( order.id, order.order_key, testData );
			await mollieHostedCheckout.payForOrder( testData.payment );

			await expect
				.poll(
					async () =>
						( await wooCommerceApi.getOrder( order.id ) ).status,
					{
						timeout: 60_000,
					}
				)
				.toBe( 'processing' );
			await assertPaymentBelongsToOrder(
				{ mollieClientApi, wooCommerceApi },
				order.id
			);
		}
	);
}
