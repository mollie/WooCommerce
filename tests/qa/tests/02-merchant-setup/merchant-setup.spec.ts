/**
 * Internal dependencies
 */
import { expect, test } from '../../utils';
import { gateways } from '../../resources';

test.describe.serial( () => {
	test.beforeAll( async ( { utils } ) => {
		await utils.installActivateMollie();
		await utils.cleanReconnectMollie();
	} );

	test( 'C3511 | Validate an error message is returned when the test key is not valid/empty', async ( {
		mollieSettingsApiKeys,
		mollieSettingsAdvanced,
	} ) => {
		await mollieSettingsAdvanced.visit();
		await mollieSettingsAdvanced.cleanDb();
		await mollieSettingsApiKeys.visit();
		await expect(
			mollieSettingsApiKeys.failedToConnectToMollieApiText()
		).toBeVisible();
	} );

	test( 'C3510 | Validate that test/live keys are valid', async ( {
		mollieSettingsApiKeys,
	} ) => {
		await mollieSettingsApiKeys.visit();
		await mollieSettingsApiKeys.setApiKeys();
		await mollieSettingsApiKeys.saveChanges();
		await expect(
			mollieSettingsApiKeys.successfullyConnectedWithTestApiText()
		).toBeVisible();
	} );

	test( 'C419984 | Payments tab - payment methods UI', async ( {
		wooCommerceSettings,
	} ) => {
		await wooCommerceSettings.visit( 'payments' );
		for ( const key in gateways ) {
			const gateway = gateways[ key ];
			const mollieGatewayname = `Mollie - ${ gateway.name }`;
			await expect
				.soft( wooCommerceSettings.gatewayLink( mollieGatewayname ) )
				.toBeVisible();
		}
	} );
} );
