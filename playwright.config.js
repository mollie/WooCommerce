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
    testDir: './tests/e2e',
    /* Run tests in files in parallel */
    fullyParallel: false,
    timeout: 320000,
    /* Reporter to use. See https://playwright.dev/docs/test-reporters */
    reporter: [
        ['list'],
        ['junit', testRailOptions]
    ],
    globalSetup: './tests/e2e/globalSetup.js',
    /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
    use: {
        baseURL: process.env.BASEURL,
        storageState: './storageState.json',
        extraHTTPHeaders: {'ngrok-skip-browser-warning': '123'},
        actionTimeout: 120000,
    },

    /* Configure projects for major browsers */
    projects: [
        {
            name: 'plugins-page',
            testDir: './tests/e2e/Plugins page',
            use: {
                ...devices['Desktop Chrome'],
                testIdAttribute: 'data-slug'
            }
        },
        {
            name: 'woo-payments-tab',
            testDir: './tests/e2e/WooCommerce Payments tab',
            use: {
                ...devices['Desktop Chrome']
            }
        },
        {
            name: 'transaction-scenarios',
            testDir: './tests/e2e/Transaction Scenarios',
            use: {
                ...devices['Desktop Chrome']
            }
        },
        {
            name: 'mollie-settings-tab',
            testDir: './tests/e2e/Mollie Settings tab',
            use: {
                ...devices['Desktop Chrome']
            }
        },
        {
            name: 'error-handling',
            testDir: './tests/e2e/Error Handling',
            use: {
                ...devices['Desktop Chrome']
            }
        },
    ],
});

