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
import { buildShards, buildSetupProjects } from './tests/qa/project-shards';

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
		// Manual setup (grep scripts target this — no dependency chain)
		{
			name: 'setup',
			testMatch: /(01-env|02-woocommerce|03-mollie|04-multistep)\.setup\.ts/,
			fullyParallel: false,
		},

		// WooCommerce (dependency target only)
		{
			name: 'setup-woocommerce',
			testMatch: /02-woocommerce\.setup\.ts/,
			grep: /setup:wc;/,
			fullyParallel: false,
		},

		// =============================================================
		// Per-state setup projects (per API method, only the states that API's
		// shards use), consumed by shards
		// =============================================================
		...buildSetupProjects( 'payment' ),
		...buildSetupProjects( 'order' ),

		// =============================================================
		// Project shards (for parallel/separate executions)
		// =============================================================
		...buildShards( 'payment' ),
		...buildShards( 'order' ),
	],
} );