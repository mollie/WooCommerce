/**
 * Internal dependencies
 */
import { MollieSettings } from '../../../../resources';

export const surchargePercentageOverLimit: {
	describe: string;
	title: string;
	expectedAmount: number;
	expectedFeeText: string;
	settings: MollieSettings.Gateway;
	tests: { testId: string; gateway: string }[];
} = {
	describe: 'Surcharge fee - Percentage over limit',
	title: 'Validate percentage fee surcharge for total over limit for',
	expectedAmount: 111.0,
	expectedFeeText: '+ 10% fee might apply (excl. VAT)',
	settings: {
		payment_surcharge: 'percentage',
		fixed_fee: '0',
		maximum_limit: '10',
		percentage: '10',
		surcharge_limit: '0',
	},
	tests: [
		// { testId: 'C420317', gateway: 'applepay' },
		{ testId: 'C89356', gateway: 'creditcard' },
		{ testId: 'C130904', gateway: 'giftcard' },
		{ testId: 'C129821', gateway: 'voucher' },
		{ testId: 'C129510', gateway: 'bancontact' },
		{ testId: 'C138019', gateway: 'belfius' },
		{ testId: 'C354672', gateway: 'billie' },
		{ testId: 'C133666', gateway: 'eps' },
		{ testId: 'C130864', gateway: 'ideal' },
		{ testId: 'C133676', gateway: 'kbc' },
		{ testId: 'C420327', gateway: 'mybank' },
		{ testId: 'C130894', gateway: 'paypal' },
		{ testId: 'C420139', gateway: 'paysafecard' },
		{ testId: 'C129811', gateway: 'przelewy24' },
		{ testId: 'C136537', gateway: 'banktransfer' },
		{ testId: 'C106916', gateway: 'in3' },
		// { testId: 'C130884', gateway: 'klarnapaylater' },
		// { testId: 'C136527', gateway: 'klarnapaynow' },
		// { testId: 'C129200', gateway: 'klarnasliceit' },
	],
};