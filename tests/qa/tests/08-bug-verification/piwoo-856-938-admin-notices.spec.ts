/**
 * Re-checks PIWOO-938 (API key connection messages) and PIWOO-856 (admin notice
 * styles) on the 8.2.0 package. Real Mollie errors are red notices with the
 * data-mollie-error attribute, warnings are yellow and have no attribute.
 *
 * NOTE-04 (status error notice) renders only for an incompatible installation,
 * so it stays manual.
 *
 * Mollie 8.2.0 Phase 2 test cases:
 * P1-04 (a, c, d) and NOTE-01 to NOTE-03.
 */

/**
 * External dependencies
 */
import fs from 'fs';
/**
 * Internal dependencies
 */
import { annotateGateway, test, expect } from '../../utils';
import { mollieApiKeys } from '../../resources';

const testModeNotice =
	'The test mode is active, disable it before deploying into production.';
const apiKeysMissingNotice =
	'Mollie Payments for WooCommerce: API keys missing';

// Order matters: without a working key only the API keys section is shown
test.describe
	.serial( 'API key messages and admin notice styles (PIWOO-938, PIWOO-856)', () => {
	test.afterAll( async ( { utils, mollieProfile } ) => {
		await utils.cleanReconnectMollie( mollieApiKeys[ mollieProfile ] );
	} );

	test( 'C4567669 | Valid test key: connected, test mode notice is a warning without data-mollie-error', async ( {
		wpAdminDashboard,
		mollieApi,
		mollieSettingsApiKeys,
		mollieProfile,
	} ) => {
		await mollieApi.setMollieApiKeys( mollieApiKeys[ mollieProfile ] );
		await mollieSettingsApiKeys.visit();
		await expect(
			mollieSettingsApiKeys.successfullyConnectedWithTestApiText()
		).toBeVisible();

		await wpAdminDashboard.visit();
		const notice = wpAdminDashboard.adminNotice( testModeNotice );
		await expect( notice ).toHaveClass( /notice-warning/ );
		await expect( notice ).not.toHaveAttribute( 'data-mollie-error' );
	} );

	test( 'C4567670 | No keys: no API key message, API keys missing notice is a dismissible warning', async ( {
		wpAdminDashboard,
		mollieSettingsAdvanced,
		mollieSettingsApiKeys,
	} ) => {
		// Saving empty key fields keeps the stored keys, only the DB cleanup removes them
		await mollieSettingsAdvanced.visit();
		await mollieSettingsAdvanced.cleanDb();
		await mollieSettingsApiKeys.visit();
		await expect(
			mollieSettingsApiKeys.noApiKeyProvidedText()
		).toBeVisible();
		await expect(
			mollieSettingsApiKeys.communicatingWithMollieFailedText()
		).toHaveCount( 0 );

		await wpAdminDashboard.visit();
		const notice = wpAdminDashboard.adminNotice( apiKeysMissingNotice );
		await expect( notice ).toHaveClass( /notice-warning/ );
		await expect( notice ).toHaveClass( /is-dismissible/ );
		await expect( notice ).not.toHaveAttribute( 'data-mollie-error' );
	} );

	test( 'C4567671 | Wrong test key: failed to connect message', async ( {
		mollieApi,
		mollieSettingsApiKeys,
		mollieProfile,
	} ) => {
		const validKey = mollieApiKeys[ mollieProfile ].testApiKey;
		const wrongKeys = {
			// Mollie answers 401
			'one character changed':
				validKey.slice( 0, -1 ) +
				( validKey.endsWith( 'a' ) ? 'b' : 'a' ),
			// Passes the plugin format check, Mollie answers 400 "Invalid Authorization header"
			'characters added': validKey + 'abc',
		};
		for ( const [ label, wrongKey ] of Object.entries( wrongKeys ) ) {
			await test.step( label, async () => {
				await mollieApi.setMollieApiKeys( {
					...mollieApiKeys[ mollieProfile ],
					testApiKey: wrongKey,
				} );
				await mollieSettingsApiKeys.visit();
				await expect
					.soft( mollieSettingsApiKeys.failedToConnectText() )
					.toBeVisible();
				await expect
					.soft(
						mollieSettingsApiKeys.communicatingWithMollieFailedText()
					)
					.toHaveCount( 0 );
			} );
		}
	} );
} );

test(
	'C4567672 | Logo upload errors are dismissible errors with data-mollie-error',
	annotateGateway( 'creditcard' ),
	async ( { mollieSettingsGateway } ) => {
		const uploads = [
			{
				file: {
					name: 'logo.txt',
					mimeType: 'text/plain',
					buffer: Buffer.from( 'not an image' ),
				},
				message:
					'Unable to upload the file. Only jpg, jpeg, png, gif and svg files are allowed.',
			},
			{
				file: {
					name: 'logo.png',
					mimeType: 'image/png',
					// Real PNG header, padded over the 500 KB limit
					buffer: Buffer.concat( [
						fs.readFileSync(
							'./tests/qa/resources/files/dummy-logo-png.png'
						),
						Buffer.alloc( 600_000 ),
					] ),
				},
				message: 'Unable to upload the file. Size must be under 500kb.',
			},
		];
		for ( const { file, message } of uploads ) {
			await test.step( file.name, async () => {
				await mollieSettingsGateway.visit();
				await mollieSettingsGateway.setup( { display_logo: 'yes' } );
				await mollieSettingsGateway
					.uploadCustomLogoButton()
					.setInputFiles( file );
				await mollieSettingsGateway.saveChanges();
				const notice = mollieSettingsGateway.adminNotice( message );
				await expect( notice ).toHaveClass( /notice-error/ );
				await expect( notice ).toHaveClass( /is-dismissible/ );
				await expect( notice ).toHaveAttribute( 'data-mollie-error' );
			} );
		}
	}
);
