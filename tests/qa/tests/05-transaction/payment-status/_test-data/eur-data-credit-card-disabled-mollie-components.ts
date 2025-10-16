/**
 * Internal dependencies
 */
import { cards, MollieTestData } from '../../../../resources';

export const creditCardDisabledMollieComponentsClassicCheckout: MollieTestData.PaymentStatus[] =
	[
		{
			testId: 'C3371',
			gatewaySlug: 'creditcard',
			paymentStatus: 'paid',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
			orderStatus: 'processing',
		},
		{
			testId: 'C3372',
			gatewaySlug: 'creditcard',
			paymentStatus: 'open',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
		},
		{
			testId: 'C3373',
			gatewaySlug: 'creditcard',
			paymentStatus: 'failed',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
		},
		{
			testId: 'C3374',
			gatewaySlug: 'creditcard',
			paymentStatus: 'expired',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
		},
	];

export const creditCardDisabledMollieComponentsCheckout: MollieTestData.PaymentStatus[] =
	[
		{
			testId: 'C420271',
			gatewaySlug: 'creditcard',
			paymentStatus: 'expired',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
		},
		{
			testId: 'C420268',
			gatewaySlug: 'creditcard',
			paymentStatus: 'paid',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
			orderStatus: 'processing',
		},
		{
			testId: 'C420270',
			gatewaySlug: 'creditcard',
			paymentStatus: 'failed',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
		},
		{
			testId: 'C420269',
			gatewaySlug: 'creditcard',
			paymentStatus: 'open',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
		},
	];

export const creditCardDisabledMollieComponentsPayForOrder: MollieTestData.PaymentStatus[] =
	[
		{
			testId: 'C420386',
			gatewaySlug: 'creditcard',
			paymentStatus: 'expired',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
		},
		{
			testId: 'C420383',
			gatewaySlug: 'creditcard',
			paymentStatus: 'paid',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
			orderStatus: 'processing',
		},
		{
			testId: 'C420385',
			gatewaySlug: 'creditcard',
			paymentStatus: 'failed',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
		},
		{
			testId: 'C420384',
			gatewaySlug: 'creditcard',
			paymentStatus: 'open',
			card: cards.visa,
			mollieComponentsEnabled: 'no',
		},
	];
