/**
 * Internal dependencies
 */
import {
	MollieTestData,
	gateways,
	guests,
	orders,
	products,
} from '../../../../resources';

export const createShopOrder = (
	testData: MollieTestData.PaymentStatus
): WooCommerce.ShopOrder => {
	const gateway = gateways[ testData.gatewaySlug ];
	const order: WooCommerce.ShopOrder = {
		...orders.default,
		products: [ products.mollieSimple100 ],
		payment: {
			gateway: {
				...gateway,
				settings: {
					...gateway.settings,
					...( testData.mollieComponentsEnabled && {
						mollie_components_enabled:
							testData.mollieComponentsEnabled,
					} ), // for card tests with mollie components
				},
			},
			billingCompany: testData.billingCompany, // for billie tests
			card: testData.card, // for card tests
			bankIssuer: testData.bankIssuer, // for kbc tests
			status: testData.paymentStatus,
		},
		orderStatus: testData.orderStatus,
		customer: guests[ gateway.country ],
		currency: gateway.currency,
	};
	return order;
};
