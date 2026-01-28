/**
 * External dependencies
 */
import { defineConfig, devices } from '@playwright/test';
/**
 * Internal dependencies
 */
import { MollieSettings } from './resources';
import { TestBaseExtend } from './utils';
require( 'dotenv' ).config();

export default defineConfig< TestBaseExtend >( {
	testDir: 'tests',
	expect: {
		timeout: 10 * 1000,
	},
	timeout: 1 * 60 * 1000,
	/* Run tests in files in parallel */
	fullyParallel: true,
	/* Fail the build on CI if you accidentally left test.only in the source code. */
	forbidOnly: !! process.env.CI,
	/* Retry on CI only */
	retries: process.env.CI ? 2 : 0,
	/* Opt out of parallel tests on CI. */
	workers: process.env.CI ? 1 : 1,
	/* Reporter to use. See https://playwright.dev/docs/test-reporters */
	reporter: process.env.CI
		? [
				[ 'list' ],
				// [ 'html', { outputFolder: 'playwright-report' } ],
				[
					'@inpsyde/playwright-utils/build/integration/testrail/testrail-reporter.js',
				],
		  ]
		: [
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

	globalSetup: require.resolve( './global-setup' ),

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

		viewport: { width: 1280, height: 850 },

		trace: 'retain-on-failure', //'on-first-retry',//'on',//

		screenshot: {
			mode: 'only-on-failure',
			fullPage: true, // Captures entire scrollable page
		},

		video: {
			mode: 'retain-on-failure', //'on',//
			size: { width: 1280, height: 850 },
		},

		recordVideoOptions: {
			mode: 'retain-on-failure',
			size: { width: 1280, height: 850 },
		},

		mollieApiMethod:
			( process.env.MOLLIE_API_METHOD as MollieSettings.ApiMethod ) ||
			'payment',
	},

	/* Configure projects for major browsers */
	projects: [
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
			name: 'all',
			dependencies: [ 'setup-woocommerce' ],
			fullyParallel: false,
			testIgnore: /refund\.spec\.ts/,
		},
		{
			name: 'refund',
			dependencies: [ 'setup-woocommerce' ],
			fullyParallel: false,
			testMatch: /refund\.spec\.ts/,
		},
	],
} );
