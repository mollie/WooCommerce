/**
 * Internal dependencies
 */
import { orders, MollieTestData, products, customers } from '../../../resources';

export const baseOrder: MollieTestData.ShopRefund = {
	...orders.default,
	products: [ products.mollieSimple100 ],
	customer: customers.germany,
	currency: 'EUR',
};