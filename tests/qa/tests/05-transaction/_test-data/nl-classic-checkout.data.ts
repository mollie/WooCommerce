/**
 * Internal dependencies
 */
import { MollieTestData, gateways } from '../../../resources';
import { baseOrder } from './transaction-base-order.data';

// Billink only works with an NL merchant profile (see nl-classic-checkout.spec.ts's
// dedicated beforeAll and project), so it's kept separate from the other EUR gateways here.
export const classicCheckoutNl: MollieTestData.ShopOrder[] = [
	{
		...baseOrder,
		testId: 'C4567617',
		testLabel: '@Critical',
		payment: {
			gateway: gateways.billink,
			status: 'authorized',
		},
	},
	{
		...baseOrder,
		testId: 'C4567618',
		payment: {
			gateway: gateways.billink,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C4567619',
		payment: {
			gateway: gateways.billink,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C4567620',
		payment: {
			gateway: gateways.billink,
			status: 'expired',
		},
	},
];
