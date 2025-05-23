/**
 * Internal dependencies
 */
import { MollieTestData, products } from '../../../../resources';

export const surchargeFixed: MollieTestData.SurchargeTestsGroup = {
	describeTitle: 'Surcharge fee > Fixed',
	testTitle: 'Validate fixed fee surcharge for',
	expectedAmount: 122.0 + 6.0,
	expectedFeeText: '+ € 10 fee might apply (excl. VAT)',
	settings: {
		payment_surcharge: 'fixed_fee',
		fixed_fee: '10',
		maximum_limit: '0',
		percentage: '0',
		surcharge_limit: '0',
	},
	tests: [
		// { testId: 'C420310', gateway: 'applepay' },
		{ testId: 'C94865', gateway: 'creditcard' },
		{ testId: 'C130897', gateway: 'giftcard' },
		{
			testId: 'C129814',
			gateway: 'voucher',
			product: products.mollieSimleVoucherMeal100,
		},
		{ testId: 'C129503', gateway: 'bancontact' },
		{ testId: 'C138012', gateway: 'belfius' },
		{ testId: 'C354665', gateway: 'billie' },
		{ testId: 'C133659', gateway: 'eps' },
		{ testId: 'C130857', gateway: 'ideal' },
		{ testId: 'C133669', gateway: 'kbc' },
		{ testId: 'C420320', gateway: 'mybank' },
		{ testId: 'C130887', gateway: 'paypal' },
		{ testId: 'C420132', gateway: 'paysafecard' },
		{
			testId: 'C129804',
			gateway: 'przelewy24',
			expectedFeeText: '+ zł 10 fee might apply (excl. VAT)',
		},
		{ testId: 'C136530', gateway: 'banktransfer' },
		{ testId: 'C106909', gateway: 'in3' },
		{ testId: 'C4237541', gateway: 'paybybank' },
		{ testId: 'C4237523', gateway: 'mbway' },
		{ testId: 'C4237514', gateway: 'multibanco' },
		{
			testId: 'C4237551',
			gateway: 'swish',
			expectedFeeText: '+ kr 10 fee might apply (excl. VAT)',
		},
	],
};
