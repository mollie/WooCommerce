/**
 * Internal dependencies
 */
import {
	MolliePaymentStatus,
	MollieTestData,
	gateways,
	guests,
	orders,
	products,
} from '../../../../resources';

const testedApiMethod =
	( process.env.MOLLIE_API_METHOD as MollieSettings.ApiMethod ) || 'payment';

const getExpectedOrderStatus = (
	paymentStatus: MolliePaymentStatus
): WooCommerce.OrderStatus => {
	const isPaymentApiMethod = testedApiMethod === 'payment';

	const statusConversion = {
		paid: 'processing',
		authorized: 'processing',
		canceled: 'pending',
		expired: isPaymentApiMethod ? 'cancelled' : 'pending',
		failed: isPaymentApiMethod ? 'failed' : 'pending',
		open: 'pending',
		pending: 'pending',
	} as Record< MolliePaymentStatus, WooCommerce.OrderStatus >;

	return statusConversion[ paymentStatus ];
};

export const createShopOrder = ( {
	gatewaySlug,
	paymentStatus,
	orderStatus,
	card,
	mollieComponentsEnabled,
	bankIssuer,
	billingCompany,
}: MollieTestData.Transaction ): WooCommerce.ShopOrder => {
	const gateway = gateways[ gatewaySlug ];
	const { country, currency } = gateway;
	const order = {
		...orders.default,
		products: [ products.mollieSimple100 ],
		payment: {
			gateway: {
				...gateway,
				settings: {
					...gateway.settings,
					// for tests with mollie components ('yes'/'no')
					...( mollieComponentsEnabled && {
						mollie_components_enabled: mollieComponentsEnabled,
					} ),
				},
			},
			billingCompany, // for billie tests
			card, // for card tests
			bankIssuer, // for kbc tests
			status: paymentStatus,
		},
		orderStatus: orderStatus ?? getExpectedOrderStatus( paymentStatus ),
		customer: guests[ country ],
		currency,
	};

	return order as WooCommerce.ShopOrder;
};
