/**
 * Internal dependencies
 */
import { MollieSettings } from '../../../../resources';

export const surchargePercentage: {
	describe: string;
	title: string;
	expectedAmount: number;
	expectedFeeText: string;
	settings: MollieSettings.Gateway;
	tests: { testId: string; gateway: string }[];
} = {
	describe: 'Surcharge fee - Percentage',
	title: 'Validate percentage fee surcharge for',
	expectedAmount: 123.21,
	expectedFeeText: '+ 10% fee might apply (excl. VAT)',
	settings: {
		payment_surcharge: 'percentage',
		fixed_fee: '0',
		maximum_limit: '0',
		percentage: '10',
		surcharge_limit: '0',
	},
	tests: [
		// { testId: 'C420311', gateway: 'applepay' },
		{ testId: 'C89351', gateway: 'creditcard' },
		{ testId: 'C130898', gateway: 'giftcard' },
		{ testId: 'C129815', gateway: 'voucher' },
		{ testId: 'C129504', gateway: 'bancontact' },
		{ testId: 'C138013', gateway: 'belfius' },
		{ testId: 'C354666', gateway: 'billie' },
		{ testId: 'C133660', gateway: 'eps' },
		{ testId: 'C130858', gateway: 'ideal' },
		{ testId: 'C133670', gateway: 'kbc' },
		{ testId: 'C420321', gateway: 'mybank' },
		{ testId: 'C130888', gateway: 'paypal' },
		{ testId: 'C420133', gateway: 'paysafecard' },
		{ testId: 'C129805', gateway: 'przelewy24' },
		{ testId: 'C136531', gateway: 'banktransfer' },
		{ testId: 'C106910', gateway: 'in3' },
		// { testId: 'C130875', gateway: 'klarnapaylater' },
		// { testId: 'C136521', gateway: 'klarnapaynow' },
		// { testId: 'C127818', gateway: 'klarnasliceit' },
	],
};
