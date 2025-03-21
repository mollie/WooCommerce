/**
 * Internal dependencies
 */
import { cards, MollieTestData } from '../../../../resources';

export const creditCardDisabledMollieComponentsClassicCheckout: MollieTestData.PaymentStatus[] =
	[
		{
			testId: 'C3376',
			gatewaySlug: 'creditcard',
			paymentStatus: 'paid',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
			orderStatus: 'processing',
		},
		{
			testId: 'C3377',
			gatewaySlug: 'creditcard',
			paymentStatus: 'open',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
			orderStatus: 'pending',
		},
		{
			testId: 'C3378',
			gatewaySlug: 'creditcard',
			paymentStatus: 'failed',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
			orderStatus: 'pending',
		},
		{
			testId: 'C3379',
			gatewaySlug: 'creditcard',
			paymentStatus: 'expired',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
			orderStatus: 'pending',
		},
	];

export const creditCardDisabledMollieComponentsCheckout: MollieTestData.PaymentStatus[] =
	[
		{
			testId: 'C420273',
			gatewaySlug: 'creditcard',
			paymentStatus: 'paid',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
			orderStatus: 'processing',
		},
		{
			testId: 'C420274',
			gatewaySlug: 'creditcard',
			paymentStatus: 'open',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
			orderStatus: 'pending',
		},
		{
			testId: 'C420275',
			gatewaySlug: 'creditcard',
			paymentStatus: 'failed',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
			orderStatus: 'pending',
		},
		{
			testId: 'C420276',
			gatewaySlug: 'creditcard',
			paymentStatus: 'expired',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
			orderStatus: 'pending',
		},
	];

export const creditCardDisabledMollieComponentsPayForOrder: MollieTestData.PaymentStatus[] =
	[
		{
			testId: 'C420388',
			gatewaySlug: 'creditcard',
			paymentStatus: 'paid',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
			orderStatus: 'processing',
		},
		{
			testId: 'C420389',
			gatewaySlug: 'creditcard',
			paymentStatus: 'open',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
			orderStatus: 'pending',
		},
		{
			testId: 'C420390',
			gatewaySlug: 'creditcard',
			paymentStatus: 'failed',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
			orderStatus: 'pending',
		},
		{
			testId: 'C420391',
			gatewaySlug: 'creditcard',
			paymentStatus: 'expired',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
			orderStatus: 'pending',
		},
	];
