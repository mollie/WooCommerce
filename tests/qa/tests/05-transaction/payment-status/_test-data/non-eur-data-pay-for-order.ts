/**
 * Internal dependencies
 */
import { MollieTestData } from '../../../../resources';

export const payForOrderNonEur: MollieTestData.PaymentStatus[] = [
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
	// recently added payment methods
	{
		testId: 'NotInTestRail',
		gatewaySlug: 'paybybank',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'NotInTestRail',
		gatewaySlug: 'paybybank',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'NotInTestRail',
		gatewaySlug: 'paybybank',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'NotInTestRail',
		gatewaySlug: 'paybybank',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	// Swish
	{
		testId: 'NotInTestRail',
		gatewaySlug: 'swish',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'NotInTestRail',
		gatewaySlug: 'swish',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'NotInTestRail',
		gatewaySlug: 'swish',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'NotInTestRail',
		gatewaySlug: 'swish',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
];
