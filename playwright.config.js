const {defineConfig, devices} = require('@playwright/test');
const {simple, virtual} = require('./tests/e2e/Shared/products');
const {ideal, banktransfer, paypal, creditcard} = require('./tests/e2e/Shared/gateways');
/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
require('dotenv').config();

/**
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
    testDir: './tests/e2e',
    /* Maximum time one test can run for. */
    timeout: 120000,
    globalTimeout: 0,
    expect: {
        /**
         * Maximum time expect() should wait for the condition to be met.
         * For example in `await expect(locator).toHaveText();`
         */
        timeout: 60000
    },
    /* Run tests in files in parallel */
    fullyParallel: true,
    /* Fail the build on CI if you accidentally left test.only in the source code. */
    forbidOnly: !!process.env.CI,
    /* Retry on CI only */
    retries: process.env.CI ? 2 : 0,
    /* Opt out of parallel tests on CI. */
    workers: process.env.CI ? 1 : undefined,
    /* Reporter to use. See https://playwright.dev/docs/test-reporters */
    reporter: 'html',
    globalSetup: require.resolve('./tests/e2e/Shared/global-setup'),
    /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
    use: {
        /* Maximum time each action such as `click()` can take. Defaults to 0 (no limit). */
        actionTimeout: 0,
        /* Base URL to use in actions like `await page.goto('/')`. */
        baseURL: process.env.BASEURL,
        storageState: './storageState.json',

        /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
        trace: 'on-first-retry',
        extraHTTPHeaders: {'ngrok-skip-browser-warning': '123'}
    },

    /* Configure projects for major browsers */
    projects: [
        {
            name: 'activation',
            testMatch: ['**/Activation/Activation.spec.js'],
            use: {
                ...devices['Desktop Chrome'],
                testIdAttribute: 'data-slug'
            },
        },
        {
            name: 'simple-settings',
            testMatch: '**/Settings/GeneralSettings.spec.js',
            use: {
                ...devices['Desktop Chrome'],
                gateways: {banktransfer},
                products: {simple},
            },
        },
        {
            name: 'full-settings',
            testMatch: '**/Settings/PaymentSettings.classic.spec.js',
            use: {
                ...devices['Desktop Chrome']
            },
        },
        {
            name: 'simple-classic',
            testMatch: ['**/Transaction/Checkout.classic.spec.js'],
            use: {
                ...devices['Desktop Chrome'],
                gateways: {ideal},
                products: {simple},
            },
        },
        {
            name: 'product-paypal',
            testMatch: '**/Product/**',
            use: {
                ...devices['Desktop Chrome'],
                gateways: paypal,
                products: {simple, virtual},
            },
        },
        /*{
            name: 'chromium',
            use: {...devices['Desktop Chrome']},
        },*/

        /* {
             name: 'firefox',
             use: {...devices['Desktop Firefox']},
         },

         {
             name: 'webkit',
             use: {...devices['Desktop Safari']},
         },*/

        /* Test against mobile viewports. */
        // {
        //   name: 'Mobile Chrome',
        //   use: { ...devices['Pixel 5'] },
        // },
        // {
        //   name: 'Mobile Safari',
        //   use: { ...devices['iPhone 12'] },
        // },

        /* Test against branded browsers. */
        // {
        //   name: 'Microsoft Edge',
        //   use: { channel: 'msedge' },
        // },
        // {
        //   name: 'Google Chrome',
        //   use: { channel: 'chrome' },
        // },
    ],

    /* Folder for test artifacts such as screenshots, videos, traces, etc. */
    outputDir: './tests/e2e/Reports/',

    /* Run your local dev server before starting the tests */
    // webServer: {
    //   command: 'npm run start',
    //   port: 3000,
    // },
});

