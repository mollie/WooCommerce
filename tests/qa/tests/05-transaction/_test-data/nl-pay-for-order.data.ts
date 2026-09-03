/**
 * Internal dependencies
 */
import { MollieTestData, gateways } from '../../../resources';
import { baseOrder } from './transaction-base-order.data';

// Billink only works with an NL merchant profile (see nl-pay-for-order.spec.ts's
// dedicated beforeAll and project), so it's kept separate from the other EUR gateways here.
export const payForOrderNl: MollieTestData.ShopOrder[] = [
	{
		...baseOrder,
		testId: 'C4567625',
		testLabel: '@Critical',
		payment: {
			gateway: gateways.billink,
			status: 'authorized',
		},
	},
	{
		...baseOrder,
		testId: 'C4567626',
		payment: {
			gateway: gateways.billink,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C4567627',
		payment: {
			gateway: gateways.billink,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C4567628',
		payment: {
			gateway: gateways.billink,
			status: 'expired',
		},
	},
];
