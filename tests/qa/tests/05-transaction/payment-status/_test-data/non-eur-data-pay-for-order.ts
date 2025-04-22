/**
 * Internal dependencies
 */
import { MollieTestData } from '../../../../resources';

export const payForOrderNonEur: MollieTestData.PaymentStatus[] = [
	{
		testId: 'C3007279',
		gatewaySlug: 'twint',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3007280',
		gatewaySlug: 'twint',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3007281',
		gatewaySlug: 'twint',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3007282',
		gatewaySlug: 'twint',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3007287',
		gatewaySlug: 'blik',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3007288',
		gatewaySlug: 'blik',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3007289',
		gatewaySlug: 'blik',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3007290',
		gatewaySlug: 'blik',
		paymentStatus: 'expired',
	},
	// recently added payment methods
	{
		testId: 'NotInTestRail',
		gatewaySlug: 'paybybank',
		paymentStatus: 'paid',
	},
	{
		testId: 'NotInTestRail',
		gatewaySlug: 'paybybank',
		paymentStatus: 'failed',
	},
	{
		testId: 'NotInTestRail',
		gatewaySlug: 'paybybank',
		paymentStatus: 'canceled',
	},
	{
		testId: 'NotInTestRail',
		gatewaySlug: 'paybybank',
		paymentStatus: 'expired',
	},
	// Swish
	{
		testId: 'NotInTestRail',
		gatewaySlug: 'swish',
		paymentStatus: 'paid',
	},
	{
		testId: 'NotInTestRail',
		gatewaySlug: 'swish',
		paymentStatus: 'failed',
	},
	{
		testId: 'NotInTestRail',
		gatewaySlug: 'swish',
		paymentStatus: 'canceled',
	},
	{
		testId: 'NotInTestRail',
		gatewaySlug: 'swish',
		paymentStatus: 'expired',
	},
];
