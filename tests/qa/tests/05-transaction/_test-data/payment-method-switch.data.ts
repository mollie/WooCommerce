/**
 * Internal dependencies
 */
import {
	MollieTestData,
	MolliePayment,
	gateways,
	cards,
} from '../../../resources';
import { creditCardDisabledComponents } from './eur-credit-card-disabled-mollie-components.data';

export const cardPayment: MolliePayment = {
	gateway: creditCardDisabledComponents,
	status: 'paid',
	card: cards.visa,
};

export const payPalPayment: MolliePayment = {
	gateway: gateways.paypal,
	status: 'paid',
};

export const paymentMethodSwitch: MollieTestData.PaymentMethodSwitch[] = [
	{
		testId: 'C4567659',
		testLabel: '@Critical',
		checkoutType: 'classic',
		firstPayment: cardPayment,
		secondPayment: payPalPayment,
	},
	{
		testId: 'C4567660',
		checkoutType: 'classic',
		firstPayment: payPalPayment,
		secondPayment: cardPayment,
	},
	{
		testId: 'C4567661',
		checkoutType: 'block',
		firstPayment: cardPayment,
		secondPayment: payPalPayment,
	},
];
