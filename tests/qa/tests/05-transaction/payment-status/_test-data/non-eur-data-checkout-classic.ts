/**
 * Internal dependencies
 */
import { MollieTestData } from '../../../../resources';

export const classicCheckoutNonEur: MollieTestData.Transaction[] = [
	{
		testId: 'C3007247',
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
		testId: 'C0000',
		gatewaySlug: 'paybybank',
		paymentStatus: 'open',
	},
	{
		testId: 'C4237583',
		gatewaySlug: 'paybybank',
		paymentStatus: 'paid',
	},
	{
		testId: 'C4237584',
		gatewaySlug: 'paybybank',
		paymentStatus: 'failed',
	},
	{
		testId: 'C4237585',
		gatewaySlug: 'paybybank',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C4237586',
		gatewaySlug: 'paybybank',
		paymentStatus: 'expired',
	},
	// Swish
	{
		testId: 'C4237587',
		gatewaySlug: 'swish',
		paymentStatus: 'paid',
	},
	{
		testId: 'C4237588',
		gatewaySlug: 'swish',
		paymentStatus: 'failed',
	},
	{
		testId: 'C4237589',
		gatewaySlug: 'swish',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C4237590',
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
		testId: 'C00001',
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
		testId: 'C00002',
		gatewaySlug: 'mobilepay',
		paymentStatus: 'failed',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'mobilepay',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'mobilepay',
		paymentStatus: 'expired',
	},
];
