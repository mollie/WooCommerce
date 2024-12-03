/**
 * Internal dependencies
 */
import { expect, test } from '../../utils';

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
