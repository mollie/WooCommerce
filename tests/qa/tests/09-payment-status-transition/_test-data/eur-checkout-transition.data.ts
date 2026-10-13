/**
 * Internal dependencies
 */
import { MollieTestData, gateways } from '../../../resources';
import { baseOrder } from './transaction-base-order-transition.data';

export const checkoutTransitionsEur: MollieTestData.ShopOrderTransition[] = [
	{
		...baseOrder,
		testId: 'C4567638',
		transition: 'onHoldToFinal',
		payment: {
			gateway: gateways.banktransfer,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C4567639',
		transition: 'onHoldToFinal',
		payment: {
			gateway: gateways.banktransfer,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C4567640',
		transition: 'onHoldToFinal',
		payment: {
			gateway: gateways.banktransfer,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C4567641',
		transition: 'authorizedToVoided',
		payment: {
			gateway: gateways.klarna,
			status: 'authorized',
		},
	},
];
