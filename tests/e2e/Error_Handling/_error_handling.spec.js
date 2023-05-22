const { expect } = require('@playwright/test');
const { test } = require('../Shared/base-test');
const {getLogByName} = require("../Shared/wooUtils");
const assert = require('assert');

test.describe(' - Error Handling', () => {
    let log;
    test.beforeAll(async () => {
        const searchString = `mollie-payments-for-woocommerce`;
        log = await getLogByName(searchString, __dirname);
    });

    const testData = [
        {
            testId: "C419987",
            mollieStatus: "Paid",
            searchLine: "onWebhookPaid processing paid order via Mollie plugin fully completed"
        },
        {
            testId: "C420052",
            mollieStatus: "Authorized",
            searchLine: "onWebhookAuthorized called for order",
        },
        {
            testId: "C420052",
            mollieStatus: "Open",
            searchLine: "Customer returned to store, but payment still pending for order",
        },
        {
            testId: "C419988",
            mollieStatus: "Failed",
            searchLine: "onWebhookFailed called for order",
        },
        {
            testId: "C420050",
            mollieStatus: "Canceled",
            searchLine: "Pending payment",
        },
        {
            testId: "C420051",
            mollieStatus: "Expired",
            searchLine: "Pending payment",
        },
        {
            testId: "C420054",
            mollieStatus: "Pending",
            wooStatus: "Pending payment",
        },
    ];

    testData.forEach(({ testId, mollieStatus, searchLine }) => {
        test(`[TestId-${testId}] Validate that "${mollieStatus} transaction is logged"`, async ({ page, products, context }) => {
            const pattern = new RegExp(searchLine, 'g');
            console.log(pattern)
            const containsPattern = pattern.test(log);
            assert.ok(containsPattern, 'The file content does not contain the desired string');
        });
    });
});
