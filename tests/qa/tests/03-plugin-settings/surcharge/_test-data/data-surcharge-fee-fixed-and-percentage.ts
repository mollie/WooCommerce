/**
 * Internal dependencies
 */
import { MollieTestData, products } from '../../../../resources';

export const surchargeFixedAndPercentage: MollieTestData.SurchargeTestsGroup = {
	describeTitle: 'Surcharge fee > Fixed and percentage',
	testTitle: 'Validate fixed and percentage fee surcharge for',
	expectedAmount: 134.21,
	expectedFeeText: '+ € 10 + 10% fee might apply (excl. VAT)',
	settings: {
		payment_surcharge: 'fixed_fee_percentage',
		fixed_fee: '10',
		maximum_limit: '0',
		percentage: '10',
		surcharge_limit: '0',
	},
	tests: [
		// { testId: 'C420312', gateway: 'applepay' },
		{ testId: 'C89352', gateway: 'creditcard' },
		{ testId: 'C130899', gateway: 'giftcard' },
		{
			testId: 'C129816',
			gateway: 'voucher',
			product: products.mollieSimleVoucherMeal100,
		},
		{ testId: 'C129505', gateway: 'bancontact' },
		{ testId: 'C138014', gateway: 'belfius' },
		{ testId: 'C354667', gateway: 'billie' },
		{ testId: 'C133661', gateway: 'eps' },
		{ testId: 'C130859', gateway: 'ideal' },
		{ testId: 'C133671', gateway: 'kbc' },
		{ testId: 'C420322', gateway: 'mybank' },
		{ testId: 'C130889', gateway: 'paypal' },
		{ testId: 'C420134', gateway: 'paysafecard' },
		{
			testId: 'C129806',
			gateway: 'przelewy24',
			expectedFeeText: '+ zł 10 + 10% fee might apply (excl. VAT)',
		},
		{ testId: 'C136532', gateway: 'banktransfer' },
		{ testId: 'C106911', gateway: 'in3' },
		{ testId: 'C4237535', gateway: 'paybybank' },
		{ testId: 'C4237529', gateway: 'mbway' },
		{ testId: 'C4237532', gateway: 'multibanco' },
		{
			testId: 'C4237545',
			gateway: 'swish',
			expectedFeeText: '+ kr 10 + 10% fee might apply (excl. VAT)',
		},
		{ testId: 'C4257952', gateway: 'bizum' },
	],
};
