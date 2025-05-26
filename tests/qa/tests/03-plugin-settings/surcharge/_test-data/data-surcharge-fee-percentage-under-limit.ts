/**
 * Internal dependencies
 */
import { MollieTestData, products } from '../../../../resources';

export const surchargePercentageUnderLimit: MollieTestData.SurchargeTestsGroup =
	{
		describeTitle: 'Surcharge fee > Percentage under limit',
		testTitle:
			'Validate percentage fee surcharge for total under limit for',
		expectedAmount: 123.21,
		expectedFeeText: '+ 10% fee might apply (excl. VAT)',
		settings: {
			payment_surcharge: 'percentage',
			fixed_fee: '0',
			maximum_limit: '150',
			percentage: '10',
			surcharge_limit: '0',
		},
		tests: [
			// { testId: 'C420314', gateway: 'applepay' },
			{ testId: 'C100198', gateway: 'creditcard' },
			{ testId: 'C130901', gateway: 'giftcard' },
			{
				testId: 'C129818',
				gateway: 'voucher',
				product: products.mollieSimleVoucherMeal100,
			},
			{ testId: 'C129798', gateway: 'bancontact' },
			{ testId: 'C138016', gateway: 'belfius' },
			{ testId: 'C354669', gateway: 'billie' },
			{ testId: 'C133663', gateway: 'eps' },
			{ testId: 'C130861', gateway: 'ideal' },
			{ testId: 'C133673', gateway: 'kbc' },
			{ testId: 'C420324', gateway: 'mybank' },
			{ testId: 'C130891', gateway: 'paypal' },
			{ testId: 'C420136', gateway: 'paysafecard' },
			{ testId: 'C129808', gateway: 'przelewy24' },
			{ testId: 'C136534', gateway: 'banktransfer' },
			{ testId: 'C106913', gateway: 'in3' },
			{ testId: 'C4237537', gateway: 'paybybank' },
			{ testId: 'C4237527', gateway: 'mbway' },
			{ testId: 'C4237518', gateway: 'multibanco' },
			{ testId: 'C4237547', gateway: 'swish' },
		],
	};
