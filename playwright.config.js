const { devices } = require('@playwright/test');
const {simple, virtual} = require('./tests/e2e/Shared/products');
const {banktransfer, paypal, creditcard} = require('./tests/e2e/Shared/gateways');
const baseURL = require('./tests/e2e/Shared/base-url');
/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
 require('dotenv').config();


/**
 * @see https://playwright.dev/docs/test-configuration
 *
 * @type {import('@playwright/test').PlaywrightTestConfig<{ products: Object, gateways: Object }>}
 */

const config = {
  testDir: './tests/e2e',
  /* Maximum time one test can run for. */
  timeout: 50 * 1000,
  expect: {
    /**
     * Maximum time expect() should wait for the condition to be met.
     * For example in `await expect(locator).toHaveText();`
     */
    timeout: 5000
  },
  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,
  /* Retry on CI only */
  retries: process.env.CI ? 2 : 1,
  /* Opt out of parallel tests on CI. */
  workers: process.env.CI ? 1 : undefined,
  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: [
      [process.env.CI ? 'github' : 'list'],
      ['junit', { outputFile: './tests/e2e/Reports/results.xml' }]],
  globalSetup: require.resolve('./tests/e2e/Shared/global-setup'),
  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    /* Maximum time each action such as `click()` can take. Defaults to 0 (no limit). */
    actionTimeout: 0,
    /* Base URL to use in actions like `await page.goto('/')`. */
    baseURL: baseURL.baseUrl,
    storageState: './storageState.json',
    /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
    trace: 'on-first-retry',
  },

  /* Configure projects for major browsers */
  projects: [
      //all simple classic:simple prod, simple subs, one gw, one browser, checkout, no buttons
      {
          name: 'simple-classic',
          testMatch: ['**/Transaction/Checkout.classic.spec.js'],
          use: {
            ...devices['Desktop Chrome'],
            gateways: {creditcard},
            products: {simple},
          },
      },

      //all simple blocks:simple prod, simple subs, one gw, one browser
      {
          name: 'simple-block',
          testMatch: ['**/Transaction/Checkout.block.spec.js'],
          use: {
              ...devices['Desktop Chrome'],
              gateways: {creditcard},
              products: {simple},
          },
      },
      //cart :paypal
      {
          name: 'cart-paypal',
          testMatch: '**/Cart/PayPalButtonCart.classic.spec.js',
          use: {
              ...devices['Desktop Chrome'],
              gateways: paypal,
              products: {simple, virtual},
          },
      },
       //product:paypal
      {
          name: 'product-paypal',
          testMatch: '**/Product/**',
          use: {
              ...devices['Desktop Chrome'],
              gateways: paypal,
              products: {simple, virtual},
          },
      },
       //settings simple
      {
          name: 'simple-settings',
          testMatch: '**/Settings/GeneralSettings.spec.js',
          use: {
              ...devices['Desktop Chrome'],
              gateways: {banktransfer},
              products: {simple},
          },
      },
       // full settings:all gw, all browsers
      {
          name: 'full-settings',
          testMatch: '**/Settings/PaymentSettingsCheckout.classic.spec.js',
          use: {
              ...devices['Desktop Chrome', 'Desktop Firefox', 'Desktop Safari']
          },
      },
  ],

  /* Folder for test artifacts such as screenshots, videos, traces, etc. */
  outputDir: 'test-results/',

  /* Run your local dev server before starting the tests */
  // webServer: {
  //   command: 'npm run start',
  //   port: 3000,
  // },
};

module.exports = config;
