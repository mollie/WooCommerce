/**
 * Internal dependencies
 */
import { orders, MollieTestData, customers, products } from '../../../resources';

export const baseOrder: MollieTestData.ShopOrder = {
	...orders.default,
	products: [ products.mollieSubscription100 ],
	subscription: {
		parentOrderStatus: 'processing',
		renewalOrderStatus: 'on-hold',
	},
	customer: customers.germany,
};