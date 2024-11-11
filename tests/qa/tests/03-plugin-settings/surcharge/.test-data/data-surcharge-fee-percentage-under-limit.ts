/**
 * Internal dependencies
 */
import { MollieSettings } from '../../../../resources';

export const surchargePercentageUnderLimit: {
	describe: string;
	title: string;
	expectedAmount: number;
	expectedFeeText: string;
	settings: MollieSettings.Gateway;
	tests: { testId: string; gateway: string }[];
} = {
	describe: 'Surcharge fee - Percentage under limit',
	title: 'Validate percentage fee surcharge for total under limit for',
	expectedAmount: 123.21,
	expectedFeeText: '+ 10% fee might apply (excl. VAT)',
	settings: {
		payment_surcharge: 'percentage',
		fixed_fee: '0',
		maximum_limit: '150',
		percentage: '10',
		surcharge_limit: '0',
	},
	tests: [
		// { testId: 'C420314', gateway: 'applepay' },
		// { testId: 'C000', gateway: 'creditcard' },
		{ testId: 'C130901', gateway: 'giftcard' },
		{ testId: 'C129818', gateway: 'voucher' },
		{ testId: 'C129507', gateway: 'bancontact' },
		{ testId: 'C138016', gateway: 'belfius' },
		{ testId: 'C354669', gateway: 'billie' },
		{ testId: 'C133663', gateway: 'eps' },
		{ testId: 'C130861', gateway: 'ideal' },
		{ testId: 'C133673', gateway: 'kbc' },
		{ testId: 'C420324', gateway: 'mybank' },
		{ testId: 'C130891', gateway: 'paypal' },
		{ testId: 'C420136', gateway: 'paysafecard' },
		{ testId: 'C129808', gateway: 'przelewy24' },
		{ testId: 'C136534', gateway: 'banktransfer' },
		{ testId: 'C106913', gateway: 'in3' },
		// { testId: 'C130881', gateway: 'klarnapaylater' },
		// { testId: 'C136524', gateway: 'klarnapaynow' },
		// { testId: 'C1278121', gateway: 'klarnasliceit' },
	],
};
