const { expect } = require('@playwright/test');
const { test } = require('../Shared/base-test');

test.describe('CI is working', () => {
    test('I see product page', async ({ page, gateways, products }) => {
        // Go to virtual product page
        await page.goto('/product/album/');
        await expect(page).toHaveTitle(/Album/);
    });
});
