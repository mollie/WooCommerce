const base = require('@playwright/test');
const {allProducts, randomProduct} = require('../utils/products');
const {allMethods} = require('../utils/gateways');
const {default: WooCommerceRestApi} = require("@woocommerce/woocommerce-rest-api");

exports.test = base.test.extend({
    products: [allProducts, { option: true }],
    gateways: [allMethods, { option: true }],
    canListenWebhooks: [process.env.WEBHOOKS, { option: true }],
    baseURL: async ({}, use, testInfo) => {
        const projectBaseURL = testInfo.project.use && testInfo.project.use.baseURL;
        console.log('projectBaseURL', projectBaseURL);
        await use(projectBaseURL || process.env.BASEURL_DEFAULT_80);
    },
    context: async ({ browser, baseURL }, use) => {
        // Additional options can be included when creating the context
        const context = await browser.newContext({baseURL});
        await use(context);
        await context.close();
    },
    page: async ({ context }, use) => {
        const page = await context.newPage();
        await use(page);
        await page.close();
    },
});
