/**
 * Internal dependencies
 */
import { MollieTestData } from '../../../../resources';

export const classicCheckoutNonEur: MollieTestData.PaymentStatus[] = [
	{
		testId: 'C300724',
		gatewaySlug: 'twint',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3007248',
		gatewaySlug: 'twint',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3007249',
		gatewaySlug: 'twint',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3007250',
		gatewaySlug: 'twint',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3007251',
		gatewaySlug: 'blik',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3007252',
		gatewaySlug: 'blik',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3007253',
		gatewaySlug: 'blik',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3007254',
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
