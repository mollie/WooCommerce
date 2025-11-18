/**
 * Internal dependencies
 */
import { MollieTestData, products } from '../../../../resources';

export const surchargeFixedUnderLimit: MollieTestData.SurchargeTestsGroup = {
	describeTitle: 'Surcharge fee > Fixed under Limit',
	testTitle: 'Validate fixed fee surcharge for total under limit for',
	expectedAmount: 122.0,
	expectedFeeText: '+ € 10 fee might apply (excl. VAT)',
	settings: {
		payment_surcharge: 'fixed_fee',
		fixed_fee: '10',
		maximum_limit: '150',
		percentage: '0',
		surcharge_limit: '0',
	},
	tests: [
		// { testId: 'C420313', gateway: 'applepay' },
		{ testId: 'C89354', gateway: 'creditcard' },
		{ testId: 'C130900', gateway: 'giftcard' },
		{
			testId: 'C129817',
			gateway: 'voucher',
			product: products.mollieSimleVoucherMeal100,
		},
		{ testId: 'C129506', gateway: 'bancontact' },
		{ testId: 'C138015', gateway: 'belfius' },
		{ testId: 'C354668', gateway: 'billie' },
		{ testId: 'C133662', gateway: 'eps' },
		{ testId: 'C130860', gateway: 'ideal' },
		{ testId: 'C133672', gateway: 'kbc' },
		{ testId: 'C420323', gateway: 'mybank' },
		{ testId: 'C130890', gateway: 'paypal' },
		{ testId: 'C420135', gateway: 'paysafecard' },
		{
			testId: 'C129807',
			gateway: 'przelewy24',
			expectedFeeText: '+ zł 10 fee might apply (excl. VAT)',
		},
		{ testId: 'C136533', gateway: 'banktransfer' },
		{ testId: 'C106912', gateway: 'in3' },
		{ testId: 'C4237540', gateway: 'paybybank' },
		{ testId: 'C4237524', gateway: 'mbway' },
		{ testId: 'C4237515', gateway: 'multibanco' },
		{
			testId: 'C4237550',
			gateway: 'swish',
			expectedFeeText: '+ kr 10 fee might apply (excl. VAT)',
		},
		{ testId: 'C4257947', gateway: 'bizum' },
	],
};
