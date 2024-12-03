/**
 * Internal dependencies
 */
import { MollieSettings } from '../../../../resources';

export const surchargeFixedAndPercentageOverLimit: {
	describe: string;
	title: string;
	expectedAmount: number;
	expectedFeeText: string;
	settings: MollieSettings.Gateway;
	tests: { testId: string; gateway: string }[];
} = {
	describe: 'Surcharge fee - Fixed and percentage over limit',
	title: 'Validate fixed and percentage fee surcharge for total over limit for',
	expectedAmount: 111.0,
	expectedFeeText: '+ € 10 + 10% fee might apply (excl. VAT)',
	settings: {
		payment_surcharge: 'fixed_fee_percentage',
		fixed_fee: '10',
		maximum_limit: '10',
		percentage: '10',
		surcharge_limit: '0',
	},
	tests: [
		// { testId: 'C420318', gateway: 'applepay' },
		{ testId: 'C89355', gateway: 'creditcard' },
		{ testId: 'C130905', gateway: 'giftcard' },
		{ testId: 'C129822', gateway: 'voucher' },
		{ testId: 'C129511', gateway: 'bancontact' },
		{ testId: 'C138020', gateway: 'belfius' },
		{ testId: 'C354673', gateway: 'billie' },
		{ testId: 'C133667', gateway: 'eps' },
		{ testId: 'C130865', gateway: 'ideal' },
		{ testId: 'C133677', gateway: 'kbc' },
		{ testId: 'C420328', gateway: 'mybank' },
		{ testId: 'C130895', gateway: 'paypal' },
		{ testId: 'C420140', gateway: 'paysafecard' },
		{ testId: 'C129812', gateway: 'przelewy24' },
		{ testId: 'C136538', gateway: 'banktransfer' },
		{ testId: 'C106917', gateway: 'in3' },
		// { testId: 'C130885', gateway: 'klarnapaylater' },
		// { testId: 'C136528', gateway: 'klarnapaynow' },
		// { testId: 'C106918', gateway: 'klarnasliceit' },
	],
};
