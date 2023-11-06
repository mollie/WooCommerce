const { expect } = require('@playwright/test');
const { test } = require('../Shared/base-test');
const {getLogByName} = require("../Shared/wooUtils");
const assert = require('assert');
const {testData} = require("./testData");

test.describe(' - Error Handling', () => {
    let log;
    test.beforeAll(async () => {
        const searchString = `mollie-payments-for-woocommerce`;
        log = await getLogByName(searchString, __dirname);
    });

    testData.forEach(({ testId, mollieStatus, searchLine }) => {
        test(`[${testId}] Validate that "${mollieStatus} transaction is logged"`, async ({ page, products, context }) => {
            const pattern = new RegExp(searchLine, 'g');
            const containsPattern = pattern.test(log);
            assert.ok(containsPattern, 'The file content does not contain the desired string');
        });
    });
});
