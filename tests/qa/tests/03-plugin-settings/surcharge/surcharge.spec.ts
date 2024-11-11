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
} from './.test-data';
import { gateways, products, guests, flatRate } from '../../../resources';

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
	await utils.configureStore( { classicPages: true } );
	await utils.installActivateMollie();
	await utils.cleanReconnectMollie();
} );

for ( const surcharge of allTests ) {
	test.describe( surcharge.describe, () => {
		for ( const tested of surcharge.tests ) {
			const gateway = gateways[ tested.gateway ];
			const country = gateway.country;

			test( `${ tested.testId } | ${ surcharge.title } "${ gateway.name } "`, async ( {
				mollieApi,
				utils,
				classicCheckout,
			} ) => {
				await mollieApi.updateMollieGateway(
					gateway.slug,
					surcharge.settings
				);

				await utils.fillVisitorsCart( [ products.mollieSimple100 ] );
				await classicCheckout.visit();
				await classicCheckout.fillCheckoutForm( guests[ country ] );
				await classicCheckout.selectShippingMethod(
					flatRate.settings.title
				);
				await classicCheckout.paymentOption( gateway.name ).click();
				await classicCheckout.page.waitForTimeout( 2000 ); // timeout for progress spinner (can't catch the element)
				const feeNotice = classicCheckout.paymentOptionFee(
					gateway.name
				);
				if ( surcharge.expectedFeeText ) {
					await expect( feeNotice ).toContainText(
						surcharge.expectedFeeText
					);
				} else {
					await expect( feeNotice ).not.toBeVisible();
				}
				const totalAmount = await classicCheckout.captureTotalAmount();
				await expect( totalAmount ).toEqual( surcharge.expectedAmount );
			} );
		}
	} );
}
