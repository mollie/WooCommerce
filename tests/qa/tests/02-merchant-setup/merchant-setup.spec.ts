/**
 * Internal dependencies
 */
import { expect, test } from '../../utils';
import { gateways } from '../../resources';

test.describe.serial( () => {
	test( 'C3511 | Validate an error message is returned when the test key is not valid/empty @Critical', async ( {
		mollieSettingsApiKeys,
		mollieSettingsAdvanced,
	} ) => {
		await mollieSettingsAdvanced.visit();
		await mollieSettingsAdvanced.cleanDb();
		await mollieSettingsApiKeys.visit();
		await expect(
			mollieSettingsApiKeys.noApiKeyProvidedText(),
			'Assert no API key provided text is visible'
		).toBeVisible();
	} );

	test( 'C3510 | Validate that test/live keys are valid', async ( {
		mollieSettingsApiKeys,
	} ) => {
		await mollieSettingsApiKeys.visit();
		await mollieSettingsApiKeys.setApiKeys();
		await mollieSettingsApiKeys.saveChanges();
		await expect(
			mollieSettingsApiKeys.successfullyConnectedWithTestApiText(),
			'Assert successfully connected with test API text is visible'
		).toBeVisible();
	} );

	test( 'C419984 | Payments tab - payment methods UI', async ( {
		wooCommerceSettings,
		mollieApiMethod,
	} ) => {
		await wooCommerceSettings.visit( 'checkout' );
		for ( const key in gateways ) {
			const gateway = gateways[ key ];

			// exclude tests for payment methods if not available for tested API
			if (
				! gateway.availableForApiMethods.includes( mollieApiMethod )
			) {
				continue;
			}

			// not active on the default Mollie profile connected here
			if ( gateway.mollieProfile ) {
				continue;
			}

			const mollieGatewayName = `Mollie - ${ gateway.name }`;
			await expect
				.soft(
					wooCommerceSettings.gatewayNameCell( mollieGatewayName ),
					`${ mollieGatewayName } is NOT displayed correctly`
				)
				.toBeVisible();
		}
	} );
} );
