/**
 * Internal dependencies
 */
import { orders, MollieTestData, products } from '../../../../resources';

export const baseOrder: MollieTestData.ShopOrder = {
	...orders.default,
	products: [ products.mollieSimple100 ],
};