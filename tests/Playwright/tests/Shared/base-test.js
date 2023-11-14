const base = require('@playwright/test');
const {allProducts} = require('./products');
const {allMethods} = require('./gateways');

exports.test = base.test.extend({
    products: [allProducts, { option: true }],
    gateways: [allMethods, { option: true }],
    baseURL: async ({}, use) => {
        await use(process.env.BASEURL);
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
