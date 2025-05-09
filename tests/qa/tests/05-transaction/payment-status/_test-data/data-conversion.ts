/**
 * Internal dependencies
 */
import {
	MolliePaymentStatus,
	MollieSettings,
	MollieTestData,
	gateways,
	guests,
	orders,
	products,
} from '../../../../resources';

const getExpectedOrderStatus = (
	paymentStatus: MolliePaymentStatus
): WooCommerce.OrderStatus => {
	const apiMethod = process.env.MOLLIE_API_METHOD as MollieSettings.ApiMethod;
	const statusConversion: Record<
		MolliePaymentStatus,
		WooCommerce.OrderStatus
	> = {
		paid: 'processing',
		authorized: 'processing',
		canceled: 'pending',
		expired: apiMethod === 'order' ? 'pending' : 'cancelled',
		failed: apiMethod === 'order' ? 'pending' : 'failed',
		open: 'pending',
		pending: 'pending',
	};
	return statusConversion[ paymentStatus ];
};

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
					} ), // for card tests with mollie components = 'no'
				},
			},
			billingCompany: testData.billingCompany, // for billie tests
			card: testData.card, // for card tests
			bankIssuer: testData.bankIssuer, // for kbc tests
			status: testData.paymentStatus,
		},
		orderStatus: testData.orderStatus || getExpectedOrderStatus( testData.paymentStatus ),
		customer: guests[ gateway.country ],
		currency: gateway.currency,
	};
	return order;
};
