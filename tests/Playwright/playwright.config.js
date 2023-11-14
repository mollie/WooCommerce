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
    retries: 0,
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
        baseURL: process.env.BASEURL,
        storageState: './storageState.json',
        extraHTTPHeaders: {'ngrok-skip-browser-warning': '123'},
        //actionTimeout: 120000,
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
            name: 'plugins-page',
            testDir: './tests/Plugins page',
            use: {
                ...devices['Desktop Chrome'],
                testIdAttribute: 'data-slug'
            }
        },
        {
            name: 'woo-payments-tab',
            testDir: './tests/WooCommerce Payments tab',
            use: {
                ...devices['Desktop Chrome']
            }
        },
        {
            name: 'transaction-scenarios',
            testDir: './tests/Transaction Scenarios',
            use: {
                ...devices['Desktop Chrome']
            }
        },
        {
            name: 'mollie-settings-tab',
            testDir: './tests/Mollie Settings tab',
            use: {
                ...devices['Desktop Chrome']
            }
        },
        {
            name: 'error-handling',
            testDir: './tests/Error Handling',
            use: {
                ...devices['Desktop Chrome']
            }
        },
    ],
});

