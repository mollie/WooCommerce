/**
 * External dependencies
 */
import { WpCliEnvType } from '@inpsyde/playwright-utils/build/@types/wp-cli';
import { restLogin } from '@inpsyde/playwright-utils/build';
import { execFileSync } from 'node:child_process';

/**
 * Throw if any of the listed env vars are missing.
 */
const checkEnvVars = ( names: string[] ): void => {
	const missing = names.filter( ( name ) => ! process.env[ name ] );
	if ( missing.length ) {
		throw new Error(
			`Missing required environment variable(s): ${ missing.join( ', ' ) }`
		);
	}
};

/**
 * Reset the WordPress environment to a clean state.
 * Supports 'localhost' (PowerShell/XAMPP) and 'ssh' env types.
 */
export const resetEnvironment = async (): Promise< void > => {
	checkEnvVars( [ 'WPCLI_ENV_TYPE' ] );
	const envType = process.env.WPCLI_ENV_TYPE as WpCliEnvType;

	let command: string;
	let args: string[];

	if ( envType === 'localhost' ) {
		checkEnvVars( [ 'WPCLI_PATH', 'WP_BASE_URL' ] );

		const psCommand = [
			'$env:PATH += ";C:\\xampp\\mysql\\bin"',
			`cd ${ process.env.WPCLI_PATH }`,
			'wp db reset --yes',
			'wp config create --dbname=geniuscourse --dbuser=root --dbpass="" --dbhost=localhost --skip-check --force',
			`wp core install --url="${ process.env.WP_BASE_URL }" --title="Test Site" --admin_user="admin" --admin_password="password" --admin_email="test@test.com"`,
			'wp plugin delete --all',
			'wp theme delete --all',
			'wp plugin install woocommerce --activate',
			'wp cache flush',
		].join( '; ' );

		command = 'powershell';
		args = [ '-NoProfile', '-Command', psCommand ];
	} else if ( envType === 'ssh' ) {
		checkEnvVars( [ 'SSH_LOGIN', 'SSH_HOST', 'SSH_PORT' ] );

		const WP_VERSION = process.env.WP_VERSION ?? '6.9';
		const WP_TYPE = process.env.WP_TYPE ?? 'single';
		const remoteCmd = `$HOME/bin/reset-wp.sh --wp-version=${ WP_VERSION } --wp-type=${ WP_TYPE }`;

		command = 'ssh';
		args = [
			`${ process.env.SSH_LOGIN }@${ process.env.SSH_HOST }`,
			'-p', process.env.SSH_PORT!,
			'-o', 'StrictHostKeyChecking=no',
			remoteCmd,
		];
	} else {
		throw new Error( `Unsupported WPCLI_ENV_TYPE: ${ envType }` );
	}

	console.log( `Executing: ${ command } ${ args.join( ' ' ) }` );

	execFileSync( command, args, {
		stdio: 'inherit',
		timeout: 60_000,
	} );
};

/**
 * Create admin and guest storage states.
 */
export const createStorageStates = async (): Promise< void > => {
	checkEnvVars( [
		'WP_BASE_URL',
		'STORAGE_STATE_PATH_ADMIN',
		'WP_USERNAME',
		'WP_PASSWORD',
	] );

	await restLogin( {
		baseURL: process.env.WP_BASE_URL!,
		storageStatePath: process.env.STORAGE_STATE_PATH_ADMIN,
		httpCredentials: {
			username: process.env.WP_BASIC_AUTH_USER,
			password: process.env.WP_BASIC_AUTH_PASS,
		},
		user: {
			username: process.env.WP_USERNAME,
			password: process.env.WP_PASSWORD,
		},
	} );
};