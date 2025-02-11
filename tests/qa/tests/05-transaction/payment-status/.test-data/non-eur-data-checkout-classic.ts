/**
 * Internal dependencies
 */
import { MollieTestData } from '../../../../resources';

export const classicCheckoutNonEur: MollieTestData.PaymentStatus[] = [
	{
		testId: 'C300724',
		gatewaySlug: 'twint',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3007248',
		gatewaySlug: 'twint',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007249',
		gatewaySlug: 'twint',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007250',
		gatewaySlug: 'twint',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007251',
		gatewaySlug: 'blik',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3007252',
		gatewaySlug: 'blik',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007253',
		gatewaySlug: 'blik',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007254',
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
