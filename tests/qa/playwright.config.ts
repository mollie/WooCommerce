/**
 * External dependencies
 */
import { defineConfig, devices } from '@playwright/test';
require( 'dotenv' ).config();

export default defineConfig( {
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
				// [ 'html', { outputFolder: 'playwright-report' } ],
				[
					'@inpsyde/playwright-utils/build/integration/testrail/testrail-reporter.js',
				],
		  ],
	/* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */

	globalSetup: require.resolve( './global-setup' ),

	use: {
		baseURL: process.env.WP_BASE_URL,

		storageState: process.env.STORAGE_STATE_PATH_ADMIN,

		httpCredentials: {
			// @ts-ignore
			username: process.env.WP_BASIC_AUTH_USER,
			// @ts-ignore
			password: process.env.WP_BASIC_AUTH_PASS,
		},

		/* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
		trace: 'on-first-retry',

		// Capture screenshot after each test failure.
		screenshot: 'only-on-failure', //'off', //

		// Record video only when retrying a test for the first time.
		video: 'retain-on-failure', //'on', //

		...devices[ 'Desktop Chrome' ],

		viewport: { width: 1280, height: 850 },
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
			testMatch: /mollie-default\.setup\.ts/,
			dependencies: [ 'setup-woocommerce' ],
			fullyParallel: false,
		},
		{
			name: 'setup-pages-classic',
			testMatch: /pages-classic\.setup\.ts/,
			dependencies: [ 'setup-woocommerce' ],
			fullyParallel: false,
		},
		{
			name: 'setup-pages-block',
			testMatch: /pages-block\.setup\.ts/,
			dependencies: [ 'setup-woocommerce' ],
			fullyParallel: false,
		},
		{
			name: 'all',
			dependencies: [ 'setup-woocommerce' ],
			fullyParallel: false,
		},
		{
			name: 'sequential',
			dependencies: [ 'setup-woocommerce' ],
			fullyParallel: false,
			testIgnore: [
				'eur-checkout-classic.spec.ts',
				'eur-checkout.spec.ts',
				'eur-pay-for-order.spec.ts',
			],
		},
		{
			name: 'transaction-non-eur',
			dependencies: [ 'setup-mollie' ],
			testMatch: [
				'non-eur-checkout-classic.spec.ts',
				'non-eur-checkout.spec.ts',
				'non-eur-pay-for-order.spec.ts',
			],
			fullyParallel: false,
		},
		{
			name: 'transaction-eur-classic',
			dependencies: [ 'setup-mollie', 'setup-pages-classic' ],
			testMatch: [ 'eur-checkout-classic.spec.ts' ],
			fullyParallel: true,
		},
		{
			name: 'transaction-eur-block',
			dependencies: [ 'setup-mollie', 'setup-pages-block' ],
			testMatch: [ 'eur-checkout.spec.ts', 'eur-pay-for-order.spec.ts' ],
			fullyParallel: true,
		},
	],
} );
