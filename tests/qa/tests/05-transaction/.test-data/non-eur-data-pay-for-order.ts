/**
 * Internal dependencies
 */
import { MollieTestData } from '../../../resources';

export const paymentStatusPayForOrderNonEur: MollieTestData.PaymentStatus[] = [
	{
		testId: 'C3007279',
		gatewaySlug: 'twint',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3007280',
		gatewaySlug: 'twint',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007281',
		gatewaySlug: 'twint',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007282',
		gatewaySlug: 'twint',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007287',
		gatewaySlug: 'blik',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3007288',
		gatewaySlug: 'blik',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007289',
		gatewaySlug: 'blik',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007290',
		gatewaySlug: 'blik',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
];
