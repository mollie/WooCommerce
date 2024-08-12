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

    /* utils settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
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
    //todo: option random payment method version for every project, for faster execution, filtering the dataset
    //todo: twint and blik payment methods in separate projects, precondition woocommerce different currency?
    //todo: apple with a safari project
    // Configure projects with a reduce set of test for preconditions to point to different configured envs,
    // shared stories: php version, woo and wp version, different currencies, orders/payments api, taxes and shipping matrix
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'setup-default-php81',
            testMatch: 'tests/Playwright/tests/transaction/Payment statuses - Block Checkout/_transaction_scenarios_payment_statuses_-_block_checkout.spec.js',
            use: {
                ...devices['Desktop Chrome'],
                baseURL: process.env.BASEURL_DEFAULT_81
            }
        },
    ],
});

