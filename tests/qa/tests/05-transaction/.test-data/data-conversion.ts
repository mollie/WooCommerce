/**
 * Internal dependencies
 */
import {
	MollieTestData,
	gateways,
	guests,
	orders,
	products,
} from '../../../resources';

export const createShopOrder = (
	testData: MollieTestData.PaymentStatus
): WooCommerce.ShopOrder => {
	const gateway = gateways[ testData.gatewaySlug ];
	const order: WooCommerce.ShopOrder = {
		...orders.default,
		products: [ products.mollieSimple100 ],
		payment: {
			gateway,
			billingCompany: testData.billingCompany,
			card: testData.card,
			bankIssuer: testData.bankIssuer,
			status: testData.paymentStatus,
		},
		orderStatus: testData.orderStatus,
		customer: guests[ gateway.country ],
		currency: gateway.currency,
	};
	return order;
};
