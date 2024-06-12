// @ts-check
const {defineConfig, devices} = require('@playwright/test');

/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
require('dotenv').config();
const testRailOptions = {
    // Whether to add <properties> with all annotations; default is false
    embedAnnotationsAsProperties: true,
    // Where to put the report.
    outputFile: './test-results/junit-report.xml'
};
/**
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
    retries: 1,
    testDir: './tests',
    /* Run tests in files in parallel */
    fullyParallel: false,
    //timeout: 120000,
    /* Reporter to use. See https://playwright.dev/docs/test-reporters */
    reporter: [
        ['line'],
        ['junit', testRailOptions]
    ],
    globalSetup: './globalSetup.js',

    /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
    use: {
        baseURL: process.env.BASEURL_DEFAULT_80,
        storageState: './storageState.json',
        //extraHTTPHeaders: {'ngrok-skip-browser-warning': '123'},
        actionTimeout: 120000,
        ignoreHTTPSErrors: true,
        /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
        trace: 'on-first-retry',
        video: {
            mode: 'on-first-retry',
            size: {width: 1280, height: 720},
            dir: './videos'
        }
    },

    /* Configure projects for major browsers */
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
        /*{
            name: 'setup-default-settings-merchant',
            testMatch: './tests/Shared/setup-default-settings-merchant.spec.js',
            use: {
                ...devices['Desktop Chrome'],
                baseURL: process.env.BASEURL_DEFAULT_80
            }
        },
        {
            name: 'setup-settings-merchant',
            testMatch: './tests/Shared/setup-default-settings-merchant.spec.js',
            use: {
                ...devices['Desktop Chrome'],
                baseURL: process.env.BASEURL_SETTINGS_80
            }
        },
        {
            name: 'setup-payment-api-merchant',
            testMatch: './tests/Shared/setup-default-settings-merchant.spec.js',
            use: {
                ...devices['Desktop Chrome'],
                baseURL: process.env.BASEURL_PAYMENT_80
            }
        },
        {
            name: 'plugins-page-80',
            testDir: './tests/Plugins page',
            dependencies: ['setup-settings-merchant'],
            use: {
                ...devices['Desktop Chrome'],
                testIdAttribute: 'data-slug',
                baseURL: process.env.BASEURL_SETTINGS_80
            }
        },
        {
            name: 'woo-payments-tab-80',
            testDir: './tests/WooCommerce Payments tab',
            dependencies: ['setup-default-settings-merchant'],
            use: {
                ...devices['Desktop Chrome'],
                baseURL: process.env.BASEURL_DEFAULT_80
            }
        },*/
        /*{
            name: 'transaction-scenarios-orders-80',
            testDir: './tests/transaction',
            //dependencies: ['setup-default-settings-merchant'],
            use: {
                ...devices['Desktop Chrome'],
                baseURL: process.env.BASEURL_DEFAULT_80
            }
        },*/
        /*{
            name: 'transaction-scenarios-payments-80',
            testDir: './tests/Transaction Scenarios',
            dependencies: ['setup-payment-api-merchant'],
            use: {
                ...devices['Desktop Chrome'],
                baseURL: process.env.BASEURL_PAYMENT_80
            }
        },
        {
            name: 'mollie-settings-tab-80',
            testDir: './tests/Mollie Settings tab',
            dependencies: ['setup-settings-merchant'],
            use: {
                ...devices['Desktop Chrome'],
                baseURL: process.env.BASEURL_SETTINGS_80
            }
        },
        {
            name: 'error-handling-80',
            testDir: './tests/Error Handling',
            dependencies: ['setup-default-settings-merchant'],
            use: {
                ...devices['Desktop Chrome'],
                baseURL: process.env.BASEURL_DEFAULT_80
            }
        },*/
    ],
});

