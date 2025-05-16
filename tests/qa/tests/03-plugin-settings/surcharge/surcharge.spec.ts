/**
 * Internal dependencies
 */
import { test, expect } from '../../../utils';
import {
	surchargeNoFee,
	surchargeFixed,
	surchargeFixedUnderLimit,
	surchargeFixedOverLimit,
	surchargePercentage,
	surchargePercentageUnderLimit,
	surchargePercentageOverLimit,
	surchargeFixedAndPercentage,
	surchargeFixedAndPercentageUnderLimit,
	surchargeFixedAndPercentageOverLimit,
} from './_test-data';
import {
	gateways,
	products,
	guests,
	flatRate,
	shopSettings,
	MollieSettings,
} from '../../../resources';

const testedApiMethod =
	( process.env.MOLLIE_API_METHOD as MollieSettings.ApiMethod ) || 'payment';

const allTests = [
	surchargeNoFee,
	surchargeFixed,
	surchargeFixedUnderLimit,
	surchargeFixedOverLimit,
	surchargePercentage,
	surchargePercentageUnderLimit,
	surchargePercentageOverLimit,
	surchargeFixedAndPercentage,
	surchargeFixedAndPercentageUnderLimit,
	surchargeFixedAndPercentageOverLimit,
];

test.beforeAll( async ( { utils }, testInfo ) => {
	if ( testInfo.project.name !== 'all' ) {
		return;
	}
	await utils.configureStore( {
		classicPages: true,
		settings: {
			general: shopSettings.germany.general,
		},
	} );
	await utils.installActivateMollie();
	await utils.cleanReconnectMollie();
} );

for ( const surcharge of allTests ) {
	test.describe( surcharge.describeTitle, () => {
		for ( const tested of surcharge.tests ) {
			const gateway = gateways[ tested.gateway ];

			// exclude tests for payment methods if not available for tested API
			if (
				! gateway.availableForApiMethods.includes( testedApiMethod )
			) {
				continue;
			}

			const country = gateway.country;
			const product = tested.product || products.mollieSimple100;
			const expectedFeeText =
				tested.expectedFeeText || surcharge.expectedFeeText;

			test( `${ tested.testId } | ${ surcharge.testTitle } ${ gateway.name }`, async ( {
				wooCommerceApi,
				mollieApi,
				utils,
				classicCheckout,
			} ) => {
				await wooCommerceApi.updateGeneralSettings(
					shopSettings[ country ].general
				);

				await mollieApi.updateMollieGateway(
					gateway.slug,
					surcharge.settings
				);

				await utils.fillVisitorsCart( [ product ] );

				await classicCheckout.visit();
				await classicCheckout.fillCheckoutForm( guests[ country ] );
				await classicCheckout.selectShippingMethod(
					flatRate.settings.title
				);
				await expect(
					classicCheckout.paymentOption( gateway.name )
				).toBeVisible();
				await classicCheckout.paymentOption( gateway.name ).click();
				await classicCheckout.page.waitForTimeout( 2000 ); // timeout for progress spinner (can't catch the element)
				const feeNotice = classicCheckout.paymentOptionFee(
					gateway.name
				);
				if ( expectedFeeText ) {
					await expect( feeNotice ).toContainText( expectedFeeText );
				} else {
					await expect( feeNotice ).not.toBeVisible();
				}
				const totalAmount = await classicCheckout.captureTotalAmount();
				await expect( totalAmount ).toEqual( surcharge.expectedAmount );
			} );
		}
	} );
}
