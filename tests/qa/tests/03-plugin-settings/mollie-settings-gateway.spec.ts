/**
 * Internal dependencies
 */
import { annotateGateway, test, expect } from '../../utils';
import { gateways, guests, products } from '../../resources';

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( { classicPages: true } );
	await utils.installActivateMollie();
	await utils.cleanReconnectMollie();
} );

// for( let key in  gateways ) {
const key = 'eps';
const gateway = gateways[ key ];

test.describe( `Payment method settings ${ gateway.name }`, () => {
	test.beforeEach( async ( { mollieSettingsGateway } ) => {
		await mollieSettingsGateway.visit();
		await mollieSettingsGateway.setup( gateway.settings );
	} );

	test(
		`C3325 | Validate that the ecommerce admin can change the ${ gateway.name } payment name`,
		annotateGateway( gateway.slug ),
		async ( { utils, mollieSettingsGateway, classicCheckout } ) => {
			await mollieSettingsGateway.setup( {
				title: `${ gateway.name } edited`,
			} );
			await mollieSettingsGateway.saveChanges();

			await utils.fillVisitorsCart( [ products.mollieSimple100 ] );
			await classicCheckout.visit();
			await classicCheckout.fillCheckoutForm( guests[ gateway.country ] );
			await expect(
				classicCheckout.paymentOption( `${ gateway.name } edited` )
			).toBeVisible();
		}
	);

	test(
		`C3326 | Validate that the ecommerce admin can change the ${ gateway.name } payment logo`,
		annotateGateway( gateway.slug ),
		async ( { utils, mollieSettingsGateway, classicCheckout } ) => {
			await mollieSettingsGateway.setup( {
				enable_custom_logo: 'yes',
				custom_logo_path: './resources/files/mollie-test-logo.png',
			} );
			await mollieSettingsGateway.saveChanges();

			await utils.fillVisitorsCart( [ products.mollieSimple100 ] );
			await classicCheckout.visit();
			await classicCheckout.fillCheckoutForm( guests[ gateway.country ] );
			const paymentOptionLogoSrc = await classicCheckout
				.paymentOptionLogo( `${ gateway.name }` )
				.getAttribute( 'src' );
			await expect( paymentOptionLogoSrc ).toContain(
				`mollie-test-logo`
			);
		}
	);

	test(
		`C3327 | Validate that the ecommerce admin can change the ${ gateway.name } payment description`,
		annotateGateway( gateway.slug ),
		async ( { utils, mollieSettingsGateway, classicCheckout } ) => {
			await mollieSettingsGateway.setup( {
				description: `${ gateway.name } edited description`,
			} );
			await mollieSettingsGateway.saveChanges();

			await utils.fillVisitorsCart( [ products.mollieSimple100 ] );
			await classicCheckout.visit();
			await classicCheckout.fillCheckoutForm( guests[ gateway.country ] );
			await classicCheckout.paymentOption( gateway.name ).click();
			await expect(
				await classicCheckout.page.getByText(
					`${ gateway.name } edited description`
				)
			).toBeVisible();
		}
	);

	test(
		`C420329 | Validate selling only to specific countries for ${ gateway.name }`,
		annotateGateway( gateway.slug ),
		async ( { utils, mollieSettingsGateway, classicCheckout } ) => {
			await mollieSettingsGateway.setup( {
				'allowed_countries[]': [ 'Spain' ],
			} );
			await mollieSettingsGateway.saveChanges();

			await utils.fillVisitorsCart( [ products.mollieSimple100 ] );
			await classicCheckout.visit();
			await classicCheckout.fillCheckoutForm( guests[ gateway.country ] );
			await expect(
				classicCheckout.paymentOption( gateway.name )
			).not.toBeVisible();
		}
	);
} );
// }
