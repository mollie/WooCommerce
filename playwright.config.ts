/**
 * External dependencies
 */
import { defineConfig, devices } from '@playwright/test';
import { WpCliEnvType } from '@inpsyde/playwright-utils/build/@types/wp-cli';
import dotenv from 'dotenv';
import path from 'path';
/**
 * Internal dependencies
 */
import { TestBaseExtend } from './tests/qa/utils';

const dotenvPath = process.env.CI
    ? path.resolve( __dirname, '.env.ci' )
    : undefined;
dotenv.config( { path: dotenvPath } );

export default defineConfig< TestBaseExtend >( {
	testDir: 'tests/qa/tests',
	expect: {
		timeout: 10_000,
	},
	timeout: 1.5 * 60_000,
	/* Run tests in files in parallel */
	fullyParallel: true,
	/* Fail the build on CI if you accidentally left test.only in the source code. */
	forbidOnly: !! process.env.CI,
	/* Retry on CI only */
	retries: process.env.CI ? 1 : 0,
	/* Opt out of parallel tests on CI. */
	workers: process.env.CI ? 1 : 1,
	/* Reporter to use. See https://playwright.dev/docs/test-reporters */
	reporter: [
		[ 'list' ],
		[ 'html', { outputFolder: 'playwright-report' } ],
		[
			'@inpsyde/playwright-utils/build/integration/testrail/testrail-reporter.js',
			{
				apiUrl: process.env.TESTRAIL_URL,
				apiUsername: process.env.TESTRAIL_USERNAME,
				apiPassword: process.env.TESTRAIL_PASSWORD,
				plan_id: process.env.TESTRAIL_PLAN_ID,
				run_id: process.env.TESTRAIL_RUN_ID,
			},
		],
	],
	/* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */

	globalSetup: require.resolve( './tests/qa/global-setup' ),

	use: {
		baseURL: process.env.WP_BASE_URL,

		storageState: process.env.STORAGE_STATE_PATH_ADMIN,

		ignoreHTTPSErrors: process.env.IGNORE_HTTPS_ERRORS === 'true',

		httpCredentials: {
			// @ts-ignore
			username: process.env.WP_BASIC_AUTH_USER,
			// @ts-ignore
			password: process.env.WP_BASIC_AUTH_PASS,
		},

		...devices[ 'Desktop Chrome' ],

		screenshot: {
			mode: 'only-on-failure',
			fullPage: true, // Captures entire scrollable page
		},

		viewport: { width: 1280, height: 850 },

		trace: process.env.CI
			? 'off'
			: 'retain-on-failure', //'on-first-retry',//'on',//

		video: process.env.CI
			? 'off'
			: {
				mode: 'retain-on-failure', //'on',//
				size: { width: 1280, height: 850 },
			},

		recordVideoOptions: process.env.CI
			? undefined
			: {
				mode: 'retain-on-failure',
				size: { width: 1280, height: 850 },
			},

		mollieApiMethod: 'payment',
		
		isMultistepCheckout: false,

		cliConfig: {
			envType: process.env.WPCLI_ENV_TYPE as WpCliEnvType,
			path: process.env.WPCLI_PATH,
		},
	},

	/* Configure projects for major browsers */
	projects: [
		{
			name: 'setup-env',
			testMatch: /env\.setup\.ts/,
			fullyParallel: false,
		},
		{
			name: 'setup-woocommerce',
			testMatch: /woocommerce\.setup\.ts/,
			fullyParallel: false,
		},
		{
			name: 'setup-mollie',
			testMatch: /mollie\.setup\.ts/,
			fullyParallel: false,
		},
		{
			name: 'setup-mollie-payment-api',
			dependencies: [ 'setup-woocommerce' ],
			testMatch: /mollie\.setup\.ts/,
			grep: /setup:mollie;/,
			fullyParallel: false,
		},
		{
			name: 'setup-mollie-order-api',
			dependencies: [ 'setup-woocommerce' ],
			testMatch: /mollie\.setup\.ts/,
			grep: /setup:mollie;/,
			fullyParallel: false,
			use: {
				mollieApiMethod: 'order',
			},
		},
		{
			name: 'setup-multistep',
			testMatch: /multistep\.setup\.ts/,
			fullyParallel: false,
		},
		{
			name: 'setup-multistep-tests',
			dependencies: [ 'setup-woocommerce' ],
			testMatch: /multistep\.setup\.ts/,
			fullyParallel: false,
		},
		{
			name: 'payment-api',
			dependencies: [ 'setup-woocommerce' ],
			fullyParallel: false,
			testIgnore: /refund\.spec\.ts/,
		},
		{
			name: 'order-api',
			dependencies: [ 'setup-woocommerce' ],
			fullyParallel: false,
			testIgnore: /refund\.spec\.ts/,
			use: {
				mollieApiMethod: 'order',
			},
		},
		{
			name: 'refund-payment-api',
			dependencies: [ 'setup-mollie-payment-api' ],
			fullyParallel: true,
			testMatch: /refund\.spec\.ts/,
		},
		{
			name: 'refund-order-api',
			dependencies: [ 'setup-mollie-order-api' ],
			fullyParallel: true,
			testMatch: /refund\.spec\.ts/,
			use: {
				mollieApiMethod: 'order',
				isMultistepCheckout: false,
			},
		},
		{
			name: 'multistep-payment-api',
			dependencies: [ 'setup-multistep-tests' ],
			fullyParallel: false,
			testIgnore: /refund\.spec\.ts/,
			// grep: /Transaction - (Classic checkout|Checkout) - (iDEAL -|PayPal|Card|KBC)|Transaction - Checkout - (iDEAL Pay in 3|Przelewy24|MyBank)/,
			grep: /Transaction/,
			grepInvert: /Transaction - Pay for order/,
			use: {
				isMultistepCheckout: true,
			},
		},
		{
			name: 'multistep-order-api',
			dependencies: [ 'setup-multistep-tests' ],
			fullyParallel: false,
			testIgnore: /refund\.spec\.ts/,
			// grep: /Transaction - (Classic checkout|Checkout) - (iDEAL -|PayPal|Card|KBC)|Transaction - Checkout - (iDEAL Pay in 3|Przelewy24|MyBank)/,
			grep: /Transaction/,
			grepInvert: /Transaction - Pay for order/,
			use: {
				mollieApiMethod: 'order',
				isMultistepCheckout: true,
			},
		},
	],
} );
