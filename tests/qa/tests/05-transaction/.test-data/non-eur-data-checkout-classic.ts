/**
 * Internal dependencies
 */
import { MollieTestData } from '../../../resources';

export const paymentStatusCheckoutClassicNonEur: MollieTestData.PaymentStatus[] =
	[
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
	];
