/**
 * Internal dependencies
 */
import { MollieSettings } from '../../../../resources';

export const surchargeNoFee: {
	describe: string;
	title: string;
	expectedAmount: number;
	expectedFeeText: string;
	settings: MollieSettings.Gateway;
	tests: { testId: string; gateway: string }[];
} = {
	describe: 'Surcharge fee - No fee',
	title: 'Validate no fee surcharge for',
	expectedAmount: 111.0,
	expectedFeeText: '',
	settings: {
		payment_surcharge: 'no_fee',
		fixed_fee: '0',
		maximum_limit: '0',
		percentage: '0',
		surcharge_limit: '0',
	},
	tests: [
		// { testId: 'C420309', gateway: 'applepay' },
		// { testId: 'C000', gateway: 'creditcard' },
		{ testId: 'C130896', gateway: 'giftcard' },
		{ testId: 'C129813', gateway: 'voucher' },
		{ testId: 'C129502', gateway: 'bancontact' },
		{ testId: 'C138011', gateway: 'belfius' },
		{ testId: 'C354664', gateway: 'billie' },
		{ testId: 'C133658', gateway: 'eps' },
		{ testId: 'C130856', gateway: 'ideal' },
		{ testId: 'C133668', gateway: 'kbc' },
		{ testId: 'C420319', gateway: 'mybank' },
		{ testId: 'C130886', gateway: 'paypal' },
		{ testId: 'C420131', gateway: 'paysafecard' },
		{ testId: 'C129803', gateway: 'przelewy24' },
		{ testId: 'C136529', gateway: 'banktransfer' },
		{ testId: 'C106908', gateway: 'in3' },
		// { testId: 'C130871', gateway: 'klarnapaylater' },
		// { testId: 'C136519', gateway: 'klarnapaynow' },
		// { testId: 'C127227', gateway: 'klarnasliceit' },
	],
};
