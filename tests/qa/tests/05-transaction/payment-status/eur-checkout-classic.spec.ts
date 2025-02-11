/**
 * Internal dependencies
 */
import { expect, test } from '../../../utils';
import { testPaymentStatusOnClassicCheckout } from './.test-scenarios';
import { createShopOrder, classicCheckoutEur } from './.test-data';
import { shopSettings } from '../../../resources';

test.beforeAll( async ( { utils, mollieApi }, testInfo ) => {
	if ( testInfo.project.name !== 'all' ) {
		return;
	}

	await utils.configureStore( {
		settings: {
			general: shopSettings.germany.general,
		},
		classicPages: true,
	} );
	await utils.installActivateMollie();
	await utils.cleanReconnectMollie();
	await mollieApi.updateMollieGateway( 'creditcard', {
		mollie_components_enabled: 'no',
	} );
} );

for ( const testData of classicCheckoutEur ) {
	const order = createShopOrder( testData );
	testPaymentStatusOnClassicCheckout( testData.testId, order );
}

// import { products } from '../../../resources';
// test( `kbc-test`, async ( { utils, classicCheckout, checkout } ) => {
// 	await utils.fillVisitorsCart( [ products.mollieSimple100 ] );

// 	// await checkout.visit();
// 	// await checkout.paymentOption( 'KBC/CBC Payment Button' ).click();
// 	// await checkout.kbcIssuerSelect().selectOption( 'KBC' );

// 	await classicCheckout.visit();
// 	await classicCheckout.paymentOption( 'KBC/CBC Payment Button' ).click();
// 	await classicCheckout.kbcIssuerSelect().selectOption( 'KBC' );
// 	await classicCheckout.page.pause();
// } );
