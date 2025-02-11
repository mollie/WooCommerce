/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import {
	gateways,
	guests,
	MollieGateway,
	products,
	shopSettings,
} from '../../resources';

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( { classicPages: true } );
	await utils.installActivateMollie();
	await utils.cleanReconnectMollie();
} );

test( 'C420154 | Validate correct gateways shown with Order API on Classic checkout', async ( {
	utils,
	classicCheckout,
	wooCommerceApi,
} ) => {
	test.setTimeout( 600000 );
	const excludedPaymentMethods = [ 'applepay', 'directdebit' ];
	let gateway: MollieGateway;
	let guest: WooCommerce.CreateCustomer;
	let countryCode: string;

	await utils.fillVisitorsCart( [ products.mollieSimleVoucherMeal100 ] );
	await classicCheckout.visit();

	for ( const key in gateways ) {
		gateway = gateways[ key ];
		guest = guests[ gateway.country ];
		countryCode = guest.billing.country;

		if ( excludedPaymentMethods.includes( gateway.slug ) ) continue;

		await wooCommerceApi.updateGeneralSettings( {
			woocommerce_currency: gateway.currency || 'EUR',
		} );
		await classicCheckout.page.reload();
		await classicCheckout.billingCountryCombobox().click();
		await classicCheckout.billingCountryOptionByCode( countryCode ).click();
		await classicCheckout.page.waitForTimeout( 1000 );

		await expect
			.soft( await classicCheckout.paymentOption( gateway.name ) )
			.toBeVisible();
	}
} );

test.afterAll( async ( { wooCommerceApi } ) => {
	await wooCommerceApi.updateGeneralSettings( shopSettings.germany.general );
} );
