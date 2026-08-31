/**
 * Internal dependencies
 */
import { MollieTestData, gateways } from '../../../resources';
import { baseOrder } from './transaction-base-order.data';

// Billink only works with an NL merchant profile (see nl-checkout.spec.ts's
// dedicated beforeAll and project), so it's kept separate from the other EUR gateways here.
export const checkoutNl: MollieTestData.ShopOrder[] = [
	{
		...baseOrder,
		testId: 'C4567609',
		testLabel: '@Critical',
		payment: {
			gateway: gateways.billink,
			status: 'authorized',
		},
	},
	{
		...baseOrder,
		testId: 'C4567610',
		payment: {
			gateway: gateways.billink,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C4567611',
		payment: {
			gateway: gateways.billink,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C4567612',
		payment: {
			gateway: gateways.billink,
			status: 'expired',
		},
	},
];
