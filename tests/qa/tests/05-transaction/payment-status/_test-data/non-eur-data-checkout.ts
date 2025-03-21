/**
 * Internal dependencies
 */
import { MollieTestData } from '../../../../resources';

export const checkoutNonEur: MollieTestData.PaymentStatus[] = [
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
