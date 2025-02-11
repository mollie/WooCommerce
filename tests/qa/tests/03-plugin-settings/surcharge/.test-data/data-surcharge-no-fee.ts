/**
 * Internal dependencies
 */
import {
	MollieSettings,
	MollieTestData,
	products,
} from '../../../../resources';

export const surchargeNoFee: MollieTestData.SurchargeTestsGroup = {
	describeTitle: 'Surcharge fee > No fee',
	testTitle: 'Validate no fee surcharge for',
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
		// { testId: 'NotInTestRail', gateway: 'creditcard' },
		{ testId: 'C130896', gateway: 'giftcard' },
		{
			testId: 'C129813',
			gateway: 'voucher',
			product: products.mollieSimleVoucherMeal100,
		},
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
		{ testId: 'NotInTestRail', gateway: 'paybybank' },
		{ testId: 'NotInTestRail', gateway: 'mbway' },
		{ testId: 'NotInTestRail', gateway: 'multibanco' },
		{ testId: 'NotInTestRail', gateway: 'swish' },
	],
};
