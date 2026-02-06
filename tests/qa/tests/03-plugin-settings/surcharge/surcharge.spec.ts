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
		enableClassicPages: true,
		settings: {
			general: shopSettings.germany.general,
		},
	} );
	await utils.installAndActivateMollie();
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
					classicCheckout.paymentOptionLabel( gateway.slug ),
					`Assert ${ gateway.name } payment option is visible`
				).toBeVisible();
				await classicCheckout
					.paymentOptionLabel( gateway.slug )
					.click();
				await classicCheckout.page.waitForTimeout( 2000 ); // timeout for progress spinner (can't catch the element)
				const feeNotice = classicCheckout.paymentOptionFee(
					gateway.name
				);
				if ( expectedFeeText ) {
					await expect(
						feeNotice,
						`Assert fee notice is visible for ${ gateway.name }`
					).toContainText( expectedFeeText );
				} else {
					await expect(
						feeNotice,
						`Assert fee notice is not visible for ${ gateway.name }`
					).not.toBeVisible();
				}
				const totalAmount = await classicCheckout.captureTotalAmount();
				await expect(
					totalAmount,
					`Assert total amount is correct for ${ gateway.name }`
				).toEqual( surcharge.expectedAmount );
			} );
		}
	} );
}
