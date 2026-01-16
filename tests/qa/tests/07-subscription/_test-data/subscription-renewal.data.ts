/**
 * Internal dependencies
 */
import { MollieTestData, gateways, cards } from '../../../resources';
import { baseOrder } from './subscription-base-order.data';

export const subscriptionRenewal: MollieTestData.ShopOrder[] = [
	{
		...baseOrder,
		testId: 'C4132915',
		payment: {
			gateway: gateways.ideal,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C4211824',
		payment: {
			gateway: gateways.bancontact,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C4211825',
		payment: {
			gateway: gateways.eps,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C4211826',
		payment: {
			gateway: gateways.kbc,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C4211827',
		payment: {
			gateway: gateways.belfius,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C4132916',
		payment: {
			gateway: gateways.creditcard,
			card: cards.visa,
			status: 'paid',
		},
		subscription: {
			parentOrderStatus: 'processing',
			renewalOrderStatus: 'processing',
		},
	},
];
