/**
 * Internal dependencies
 */
import { MollieTestData, products } from '../../../../resources';

export const surchargeFixedAndPercentageUnderLimit: MollieTestData.SurchargeTestsGroup = {
	describeTitle: 'Surcharge fee > Fixed and percentage under limit',
	testTitle: 'Validate fixed and percentage fee surcharge for total under limit for',
	expectedAmount: 134.21,
	expectedFeeText: '+ € 10 + 10% fee might apply (excl. VAT)',
	settings: {
		payment_surcharge: 'fixed_fee_percentage',
		fixed_fee: '10',
		maximum_limit: '150',
		percentage: '10',
		surcharge_limit: '0',
	},
	tests: [
		// { testId: 'C420315', gateway: 'applepay' },
		// { testId: 'NotInTestRail', gateway: 'creditcard' },
		{ testId: 'C130902', gateway: 'giftcard' },
		{ testId: 'C129819', gateway: 'voucher', product: products.mollieSimleVoucherMeal100 },
		{ testId: 'C129508', gateway: 'bancontact' },
		{ testId: 'C138017', gateway: 'belfius' },
		{ testId: 'C354670', gateway: 'billie' },
		{ testId: 'C133664', gateway: 'eps' },
		{ testId: 'C130862', gateway: 'ideal' },
		{ testId: 'C133674', gateway: 'kbc' },
		{ testId: 'C420325', gateway: 'mybank' },
		{ testId: 'C130892', gateway: 'paypal' },
		{ testId: 'C420137', gateway: 'paysafecard' },
		{ testId: 'C129809', gateway: 'przelewy24' },
		{ testId: 'C136535', gateway: 'banktransfer' },
		{ testId: 'C106914', gateway: 'in3' },
		{ testId: 'NotInTestRail', gateway: 'paybybank' },
		{ testId: 'NotInTestRail', gateway: 'mbway' },
		{ testId: 'NotInTestRail', gateway: 'multibanco' },
		{ testId: 'NotInTestRail', gateway: 'swish', expectedFeeText: '+ kr 10 + 10% fee might apply (excl. VAT)' },
	],
};
