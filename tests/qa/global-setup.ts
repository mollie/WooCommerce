/**
 * External dependencies
 */
import { FullConfig } from '@playwright/test';
import { restLogin, guestStorageState } from '@inpsyde/playwright-utils/build';

async function globalSetup( config: FullConfig ) {
	const projectUse = config.projects[ 0 ].use;

	await restLogin( {
		baseURL: projectUse.baseURL,
		storageStatePath: String( projectUse.storageState ),
		httpCredentials: projectUse.httpCredentials,
		user: {
			// @ts-ignore
			username: process.env.WP_USERNAME,
			// @ts-ignore
			password: process.env.WP_PASSWORD,
		},
	} );

	await guestStorageState( {
		baseURL: projectUse.baseURL,
		httpCredentials: projectUse.httpCredentials,
		storageStatePath: `${ process.env.STORAGE_STATE_PATH }/guest.json`,
	} );
}

export default globalSetup;
