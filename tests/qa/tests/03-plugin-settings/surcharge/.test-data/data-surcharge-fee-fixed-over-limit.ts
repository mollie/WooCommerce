/**
 * Internal dependencies
 */
import { MollieSettings } from '../../../../resources';

export const surchargeFixedOverLimit: {
	describe: string;
	title: string;
	expectedAmount: number;
	expectedFeeText: string;
	settings: MollieSettings.Gateway;
	tests: { testId: string; gateway: string }[];
} = {
	describe: 'Surcharge fee - Fixed over limit',
	title: 'Validate fixed fee surcharge fo total over limit for',
	expectedAmount: 111.0,
	expectedFeeText: '+ € 10 fee might apply (excl. VAT)',
	settings: {
		payment_surcharge: 'fixed_fee',
		fixed_fee: '10',
		maximum_limit: '10',
		percentage: '0',
		surcharge_limit: '0',
	},
	tests: [
		// { testId: 'C354671', gateway: 'applepay' },
		{ testId: 'C89353', gateway: 'creditcard' },
		{ testId: 'C130903', gateway: 'giftcard' },
		{ testId: 'C129820', gateway: 'voucher' },
		{ testId: 'C129509', gateway: 'bancontact' },
		{ testId: 'C138018', gateway: 'belfius' },
		{ testId: 'C354671', gateway: 'billie' },
		{ testId: 'C133665', gateway: 'eps' },
		{ testId: 'C130863', gateway: 'ideal' },
		{ testId: 'C133675', gateway: 'kbc' },
		{ testId: 'C420326', gateway: 'mybank' },
		{ testId: 'C130893', gateway: 'paypal' },
		{ testId: 'C420138', gateway: 'paysafecard' },
		{ testId: 'C129810', gateway: 'przelewy24' },
		{ testId: 'C136536', gateway: 'banktransfer' },
		{ testId: 'C106915', gateway: 'in3' },
		// { testId: 'C130883', gateway: 'klarnapaylater' },
		// { testId: 'C136526', gateway: 'klarnapaynow' },
		// { testId: 'C128597', gateway: 'klarnasliceit' },
	],
};
