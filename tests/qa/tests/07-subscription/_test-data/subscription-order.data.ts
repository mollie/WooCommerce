/**
 * Internal dependencies
 */
import { MollieTestData, gateways } from '../../../resources';
import { baseOrder } from './subscription-base-order.data';

export const subscriptionOrderOnClassicCheckout: MollieTestData.ShopOrder[] = [
	{
		...baseOrder,
		testId: 'C4211828',
		payment: {
			gateway: gateways.trustly,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C4237623',
		payment: {
			gateway: gateways.paybybank,
			status: 'paid',
		},
	},
];
