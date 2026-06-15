/**
 * Internal dependencies
 */
import { annotateGateway, test, expect } from '../../utils';
import { gateways, guests, products } from '../../resources';

test.beforeAll( async ( { utils } ) => {
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();
} );

// for( let key in  gateways ) {
const key = 'creditcard';
const gateway = gateways[ key ];

test.describe( `Payment method settings ${ gateway.name }`, () => {
	test.beforeEach( async ( { mollieSettingsGateway } ) => {
		await mollieSettingsGateway.visit();
		await mollieSettingsGateway.setup( gateway.settings );
	} );

	test(
		`C3325 | Payment method settings - Title can be changed (${ gateway.name }) @Critical`,
		annotateGateway( gateway.slug ),
		async ( {
			utils,
			mollieSettingsGateway,
			checkout,
			classicCheckout,
		} ) => {
			await mollieSettingsGateway.setup( {
				title: `${ gateway.name } edited`,
			} );
			await mollieSettingsGateway.saveChanges();

			await utils.fillVisitorsCart( [ products.mollieSimple100 ] );

			await utils.configureStore( { enableClassicPages: false } );
			await checkout.visit();
			await checkout.fillCheckoutForm( guests[ gateway.country ] );
			await expect
				.soft( checkout.paymentOption( `${ gateway.name } edited` ) )
				.toBeVisible();

			await utils.configureStore( { enableClassicPages: true } );
			await classicCheckout.visit();
			await classicCheckout.fillCheckoutForm( guests[ gateway.country ] );
			await expect
				.soft(
					classicCheckout.paymentOption( `${ gateway.name } edited` )
				)
				.toBeVisible();
		}
	);

	test(
		`C1729452 | Payment method settings - Logo can be hidden on checkout page (${ gateway.name }) @Critical`,
		annotateGateway( gateway.slug ),
		async ( {
			utils,
			mollieSettingsGateway,
			checkout,
			classicCheckout,
		} ) => {
			test.setTimeout( 90_000 );
			await mollieSettingsGateway.setup( {
				display_logo: 'yes',
			} );
			await mollieSettingsGateway.saveChanges();

			await utils.fillVisitorsCart( [ products.mollieSimple100 ] );

			await utils.configureStore( { enableClassicPages: false } );
			await checkout.visit();
			await checkout.fillCheckoutForm( guests[ gateway.country ] );
			await expect
				.soft( checkout.paymentOptionLogo( `${ gateway.name }` ) )
				.toBeVisible();

			await utils.configureStore( { enableClassicPages: true } );
			await classicCheckout.visit();
			await classicCheckout.fillCheckoutForm( guests[ gateway.country ] );
			await expect
				.soft(
					classicCheckout.paymentOptionLogo( `${ gateway.name }` )
				)
				.toBeVisible();

			await mollieSettingsGateway.visit;
			await mollieSettingsGateway.setup( {
				display_logo: 'no',
			} );
			await mollieSettingsGateway.saveChanges();

			await utils.configureStore( { enableClassicPages: false } );
			await checkout.visit();
			await checkout.fillCheckoutForm( guests[ gateway.country ] );
			await expect
				.soft( checkout.paymentOptionLogo( `${ gateway.name }` ) )
				.not.toBeVisible();

			await utils.configureStore( { enableClassicPages: true } );
			await classicCheckout.visit();
			await classicCheckout.fillCheckoutForm( guests[ gateway.country ] );
			await expect
				.soft(
					classicCheckout.paymentOptionLogo( `${ gateway.name }` )
				)
				.not.toBeVisible();
		}
	);

	test(
		`C3326 | Payment method settings - Logo can be changed (${ gateway.name }) @Critical`,
		annotateGateway( gateway.slug ),
		async ( {
			utils,
			mollieSettingsGateway,
			checkout,
			classicCheckout,
		} ) => {
			test.setTimeout( 3 * 60_000 );
			
			const logos = [
				{ name: 'dummy-logo-jpg', format: 'jpg' },
				{ name: 'dummy-logo-png', format: 'png' },
				{ name: 'dummy-logo-svg', format: 'svg' },
			];

			for( const logo of logos ) {
				await test.step( `Upload ${ logo.name }.${ logo.format } logo in settings`, async () => {
					await mollieSettingsGateway.setup( {
						display_logo: 'yes',
						custom_logo_path:
							`./tests/qa/resources/files/${ logo.name }.${ logo.format }`,
					} );
					await mollieSettingsGateway.saveChanges();
				} );

				
				await test.step( `Check ${ logo.name } logo block checkout`, async () => {
					await utils.fillVisitorsCart( [ products.mollieSimple100 ] );

					await utils.configureStore( { enableClassicPages: false } );
					await checkout.visit();
					await checkout.fillCheckoutForm( guests[ gateway.country ] );
					const blockPaymentOptionLogoSrc = await checkout
						.paymentOptionLogo( `${ gateway.name }` )
						.getAttribute( 'src' );
					await expect
						.soft( blockPaymentOptionLogoSrc )
						.toContain( logo.name );
				} );

				await test.step( `Check ${ logo.name } logo classic checkout`, async () => {
					await utils.configureStore( { enableClassicPages: true } );
					await classicCheckout.visit();
					await classicCheckout.fillCheckoutForm( guests[ gateway.country ] );
					const classicPaymentOptionLogoSrc = await classicCheckout
						.paymentOptionLogo( `${ gateway.name }` )
						.getAttribute( 'src' );
					await expect
						.soft( classicPaymentOptionLogoSrc )
						.toContain( logo.name );
				} );
			}
		}
	);

	test(
		`C3327 | Payment method settings - Description can be changed (${ gateway.name }) @Critical`,
		annotateGateway( gateway.slug ),
		async ( {
			utils,
			mollieSettingsGateway,
			checkout,
			classicCheckout,
		} ) => {
			test.setTimeout( 90_000 );
			await mollieSettingsGateway.setup( {
				description: `${ gateway.name } edited description`,
			} );
			await mollieSettingsGateway.saveChanges();

			await utils.fillVisitorsCart( [ products.mollieSimple100 ] );

			await utils.configureStore( { enableClassicPages: false } );
			await checkout.visit();
			await checkout.fillCheckoutForm( guests[ gateway.country ] );
			await checkout.paymentOption( gateway.name ).click();
			await expect
				.soft(
					await checkout.page.getByText(
						`${ gateway.name } edited description`
					)
				)
				.toBeVisible();

			await utils.configureStore( { enableClassicPages: true } );
			await classicCheckout.visit();
			await classicCheckout.fillCheckoutForm( guests[ gateway.country ] );
			await classicCheckout.paymentOption( gateway.name ).click();
			await expect
				.soft(
					await classicCheckout.page.getByText(
						`${ gateway.name } edited description`
					)
				)
				.toBeVisible();
		}
	);

	test(
		`C420329 | Payment method settings - Selling only to specific countries (${ gateway.name }, Spain) @Critical`,
		annotateGateway( gateway.slug ),
		async ( {
			utils,
			mollieSettingsGateway,
			checkout,
			classicCheckout,
		} ) => {
			await mollieSettingsGateway.setup( {
				'allowed_countries[]': [ 'Spain' ],
			} );
			await mollieSettingsGateway.saveChanges();

			await utils.fillVisitorsCart( [ products.mollieSimple100 ] );

			await utils.configureStore( { enableClassicPages: false } );
			await checkout.visit();
			await checkout.fillCheckoutForm( guests[ gateway.country ] );
			await expect
				.soft( checkout.paymentOption( gateway.name ) )
				.not.toBeVisible();

			await utils.configureStore( { enableClassicPages: true } );
			await classicCheckout.visit();
			await classicCheckout.fillCheckoutForm( guests[ gateway.country ] );
			await expect
				.soft( classicCheckout.paymentOption( gateway.name ) )
				.not.toBeVisible();
		}
	);
} );
// }
