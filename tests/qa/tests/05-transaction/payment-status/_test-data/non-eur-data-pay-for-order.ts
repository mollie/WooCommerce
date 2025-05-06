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
		testId: 'C4237615',
		gatewaySlug: 'paybybank',
		paymentStatus: 'paid',
	},
	{
		testId: 'C4237616',
		gatewaySlug: 'paybybank',
		paymentStatus: 'failed',
	},
	{
		testId: 'C4237617',
		gatewaySlug: 'paybybank',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C4237618',
		gatewaySlug: 'paybybank',
		paymentStatus: 'expired',
	},
	// Swish
	{
		testId: 'C4237619',
		gatewaySlug: 'swish',
		paymentStatus: 'paid',
	},
	{
		testId: 'C4237620',
		gatewaySlug: 'swish',
		paymentStatus: 'failed',
	},
	{
		testId: 'C4237621',
		gatewaySlug: 'swish',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C4237622',
		gatewaySlug: 'swish',
		paymentStatus: 'expired',
	},
];
