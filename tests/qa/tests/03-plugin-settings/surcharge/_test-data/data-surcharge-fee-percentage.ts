/**
 * Internal dependencies
 */
import { MollieTestData, products } from '../../../../resources';

export const surchargePercentage: MollieTestData.SurchargeTestsGroup = {
	describeTitle: 'Surcharge fee > Percentage',
	testTitle: 'Validate percentage fee surcharge for',
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
		{
			testId: 'C129815',
			gateway: 'voucher',
			product: products.mollieSimleVoucherMeal100,
		},
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
		{ testId: 'C4237538', gateway: 'paybybank' },
		{ testId: 'C4237526', gateway: 'mbway' },
		{ testId: 'C4237517', gateway: 'multibanco' },
		{ testId: 'C4237548', gateway: 'swish' },
	],
};
