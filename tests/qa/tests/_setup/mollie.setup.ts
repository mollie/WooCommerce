/**
 * Internal dependencies
 */
import { test as setup } from '../../utils';
import {
	taxSettings,
	mollieApiKeys,
	MollieSettings,
	StoreSettings,
	shopSettings,
} from '../../resources';
import path from 'path';

type EnvConfig = {
	title: string;
	store?: StoreSettings;
	mollie?: {
		cleanDb?: boolean;
		apiKeys?: MollieSettings.ApiKeys;
		apiMethod?: MollieSettings.ApiMethod;
	};
};

const configureEnv = ( data: EnvConfig ) => {
	const { title, store, mollie } = data;

	if ( store ) {
		setup( `${ title } Setup store settings`, async ( { utils } ) => {
			await utils.configureStore( store );
		} );
	}

	if ( mollie ) {
		const { cleanDb, apiKeys, apiMethod } = mollie;

		setup( `${ title } Install/activate Mollie`, async ( { utils } ) => {
			await utils.installActivateMollie();
		} );

		if ( cleanDb ) {
			setup( `${ title } Clean Mollie DB`, async ( { mollieApi } ) => {
				await mollieApi.setMollieApiKeys( mollieApiKeys.default );
				await mollieApi.cleanMollieDb();
			} );
		}

		if ( apiKeys ) {
			setup( `${ title } Set API keys`, async ( { mollieApi } ) => {
				await mollieApi.setMollieApiKeys( apiKeys );
			} );
		}

		if ( apiMethod ) {
			setup( `${ title } Set API method`, async ( { mollieApi } ) => {
				await mollieApi.setApiMethod( apiMethod );
			} );
		}
	}
};

configureEnv( {
	title: 'setup:checkout:block;',
	store: { enableClassicPages: false },
} );

configureEnv( {
	title: 'setup:checkout:classic;',
	store: { enableClassicPages: true },
} );

configureEnv( {
	title: 'setup:tax:inc;',
	store: { taxes: taxSettings.including },
} );

configureEnv( {
	title: 'setup:tax:exc;',
	store: { taxes: taxSettings.excluding },
} );

configureEnv( {
	title: 'setup:mollie:germany;',
	store: shopSettings.germany.general,
	mollie: {
		cleanDb: true,
		apiKeys: mollieApiKeys.default,
	},
} );
//  Bizum feature flag activation
setup( 'setup:bizum: Activate Bizum feature flag', async ( { page } ) => {
	const pluginPath = path.resolve(
		__dirname,
		'../../resources/files/enable-bizum.zip'
	);

	console.log( '🔧 Activating Bizum feature flag...' );

	try {
		//  Open plugins page
		await page.goto( '/wp-admin/plugins.php' );
		await page.waitForLoadState( 'networkidle' );

		//  Find a plugin row
		const pluginRow = page.locator( 'tr[data-slug="enable-bizum"]' );
		
		//  Check if plugin is active
		const deactivateLink = pluginRow.locator( 'a:has-text("Deactivate")' );
		const isActive = await deactivateLink.count() > 0;

		if ( isActive ) {
			console.log( '✅ Bizum plugin already active' );
			return;
		}

		//  Check if plugin is installed but not active
		const activateLink = pluginRow.locator( 'a:has-text("Activate")' );
		const isInstalled = await activateLink.count() > 0;

		if ( isInstalled ) {
			console.log( 'ℹ️  Bizum plugin found, activating...' );
			await activateLink.click();
			
			// Wait for page reload
			await page.waitForURL( '**/wp-admin/plugins.php**', { timeout: 10000 } );
			await page.waitForLoadState( 'networkidle' );
			
			// Check if activated
			const deactivateLinkAfter = page.locator( 'tr[data-slug="enable-bizum"] a:has-text("Deactivate")' );
			if ( await deactivateLinkAfter.count() > 0 ) {
				console.log( '✅ Bizum plugin successfully activated' );
			} else {
				throw new Error( '❌ Failed to activate Bizum plugin' );
			}
			return;
		}

		// Install if not installed
		console.log( 'ℹ️  Bizum plugin not found, installing...' );
		await page.goto( '/wp-admin/plugin-install.php?tab=upload' );
		await page.waitForLoadState( 'networkidle' );

		// Install plugin
		const fileInput = page.locator( 'input[type="file"]#pluginzip' );
		await fileInput.setInputFiles( pluginPath );

		// Click Install Now
		await page.click( 'input#install-plugin-submit' );
		await page.waitForLoadState( 'networkidle', { timeout: 30000 } );

		// Check if existing plugin should be replaced
		const replaceButton = page.locator( 'button:has-text("Replace current with uploaded")' );
		if ( await replaceButton.isVisible() ) {
			console.log( 'ℹ️  Replacing existing plugin...' );
			await replaceButton.click();
			await page.waitForLoadState( 'networkidle', { timeout: 30000 } );
		}

		// Looking for the activation button
		const activateButtonAfterInstall = page.locator( 'a:has-text("Activate Plugin")' );
		
		// Click if button is present
		if ( await activateButtonAfterInstall.isVisible() ) {
			console.log( 'ℹ️  Activating newly installed plugin...' );
			await activateButtonAfterInstall.click();
			await page.waitForURL( '**/wp-admin/plugins.php**', { timeout: 10000 } );
			await page.waitForLoadState( 'networkidle' );
		} else {
			// If no button - navigate to plugin page and activate manually
			console.log( 'ℹ️  Going to plugins page to activate...' );
			await page.goto( '/wp-admin/plugins.php' );
			await page.waitForLoadState( 'networkidle' );
			
			const activateLinkFinal = page.locator( 'tr[data-slug="enable-bizum"] a:has-text("Activate")' );
			if ( await activateLinkFinal.count() > 0 ) {
				await activateLinkFinal.click();
				await page.waitForURL( '**/wp-admin/plugins.php**', { timeout: 10000 } );
				await page.waitForLoadState( 'networkidle' );
			}
		}

		// Final activation verification
		await page.goto( '/wp-admin/plugins.php' );
		await page.waitForLoadState( 'networkidle' );
		
		const finalDeactivateLink = page.locator( 'tr[data-slug="enable-bizum"] a:has-text("Deactivate")' );
		if ( await finalDeactivateLink.count() > 0 ) {
			console.log( '✅ Bizum plugin successfully activated' );
		} else {
			throw new Error( '❌ Plugin installed but not activated' );
		}

	} catch ( error ) {
		console.error( '❌ Failed to activate Bizum:', error );
		
		// Add a screenshoot for debugging
		await page.screenshot( { 
			path: `test-results/bizum-activation-error-${Date.now()}.png`,
			fullPage: true 
		} );
		
		throw error;
	}
} );