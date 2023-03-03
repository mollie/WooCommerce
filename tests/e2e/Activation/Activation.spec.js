const { expect } = require('@playwright/test');
const { test } = require('../Shared/base-test');
const {deactivateWPPlugin, activateWPPlugin} = require("../Shared/wpUtils");

test.describe('Plugin active when WooCommerce is active', () => {
    test.beforeEach(async ({ page, }) => {
        await page.goto('/wp-admin/plugins.php');
    });

    test('I see plugin active', async ({ page}) => {
        await expect(page.getByTestId('mollie-payments-for-woocommerce')).toHaveClass(/active/);
    });

    test('I see plugin version', async ({ page}) => {
        await expect(page.getByTestId('mollie-payments-for-woocommerce')).toHaveText(/7.3.6/); //TODO: remove this and retrieve the version from the plugin
    });
});

test.describe('Plugin deactivated when WooCommerce is NOT active', () => {
    test('I see plugin notice deactivated', async ({ page}) => {
        await page.goto('/wp-admin/plugins.php');
        await deactivateWPPlugin(page, 'WooCommerce');
        await expect(page.getByText(/Mollie Payments for WooCommerce is inactive/)).toBeVisible();
        //restore state
        await activateWPPlugin(page, 'WooCommerce');
    });
});
