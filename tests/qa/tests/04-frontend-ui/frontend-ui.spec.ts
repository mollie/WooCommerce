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
	await utils.configureStore( { enableClassicPages: true } );
	await utils.installActivateMollie();
	await utils.cleanReconnectMollie();
} );

test( 'C420154 | Validate correct gateways shown with Order API on Classic checkout', async ( {
	utils,
	classicCheckout,
	wooCommerceApi,
	mollieApiMethod,
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

		// exclude tests for payment methods if not available for tested API
		if ( ! gateway.availableForApiMethods.includes( mollieApiMethod ) ) {
			continue;
		}

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

		await classicCheckout.assertPaymentOptionLabel(
			gateway.slug,
			gateway.name,
			{ isSoftAssertion: true }
		);
	}
} );

test.afterAll( async ( { wooCommerceApi } ) => {
	await wooCommerceApi.updateGeneralSettings( shopSettings.germany.general );
} );
