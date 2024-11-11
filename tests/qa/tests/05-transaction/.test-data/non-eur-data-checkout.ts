/**
 * Internal dependencies
 */
import { MollieTestData } from '../../../resources';

export const paymentStatusCheckoutNonEur: MollieTestData.PaymentStatus[] = [
	{
		testId: 'C3007271',
		gatewaySlug: 'twint',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3007272',
		gatewaySlug: 'twint',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007273',
		gatewaySlug: 'twint',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007274',
		gatewaySlug: 'twint',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007275',
		gatewaySlug: 'blik',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3007276',
		gatewaySlug: 'blik',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007277',
		gatewaySlug: 'blik',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007278',
		gatewaySlug: 'blik',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
];
