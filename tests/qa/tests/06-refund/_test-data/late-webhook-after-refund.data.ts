/**
 * Internal dependencies
 */
import { MollieTestData, gateways, cards } from '../../../resources';
import { baseOrder } from './refund-base-order.data';

export const lateWebhookAfterRefundEur: MollieTestData.ShopOrder[] = [
	{
		...baseOrder,
		testId: 'C4567642',
		payment: {
			gateway: gateways.creditcard,
			status: 'paid',
			card: cards.visa,
		},
	},
];
