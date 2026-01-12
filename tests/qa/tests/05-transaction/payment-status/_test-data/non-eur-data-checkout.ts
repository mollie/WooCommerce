/**
 * Internal dependencies
 */
import { MollieTestData } from '../../../../resources';

export const checkoutNonEur: MollieTestData.Transaction[] = [
	{
		testId: 'C3007271',
		gatewaySlug: 'twint',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3007272',
		gatewaySlug: 'twint',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3007273',
		gatewaySlug: 'twint',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3007274',
		gatewaySlug: 'twint',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3007275',
		gatewaySlug: 'blik',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3007276',
		gatewaySlug: 'blik',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3007277',
		gatewaySlug: 'blik',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3007278',
		gatewaySlug: 'blik',
		paymentStatus: 'expired',
	},
	// recently added payment methods
	{
		testId: 'C4237599',
		gatewaySlug: 'paybybank',
		paymentStatus: 'paid',
	},
	{
		testId: 'C4237500',
		gatewaySlug: 'paybybank',
		paymentStatus: 'failed',
	},
	{
		testId: 'C4237501',
		gatewaySlug: 'paybybank',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C4237502',
		gatewaySlug: 'paybybank',
		paymentStatus: 'expired',
	},
	// Swish
	{
		testId: 'C4237603',
		gatewaySlug: 'swish',
		paymentStatus: 'paid',
	},
	{
		testId: 'C4237604',
		gatewaySlug: 'swish',
		paymentStatus: 'failed',
	},
	{
		testId: 'C4237605',
		gatewaySlug: 'swish',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C4237606',
		gatewaySlug: 'swish',
		paymentStatus: 'expired',
	},
	// Vipps
	{
		testId: 'C0000',
		gatewaySlug: 'vipps',
		paymentStatus: 'paid',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'vipps',
		paymentStatus: 'failed',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'vipps',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'vipps',
		paymentStatus: 'expired',
	},
	// MobilePay
	{
		testId: 'C0000',
		gatewaySlug: 'mobilepay',
		paymentStatus: 'paid',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'mobilepay',
		paymentStatus: 'failed',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'mobilepay',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C00003',
		gatewaySlug: 'mobilepay',
		paymentStatus: 'expired',
	},
];
