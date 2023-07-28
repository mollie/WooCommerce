const {expect} = require('@playwright/test');
const {test} = require('../Shared/base-test');
const {deactivateWPPlugin, activateWPPlugin, gotoWPPlugins} = require("../Shared/wpUtils");

    test.beforeEach(async ({page}) => {
        await gotoWPPlugins(page);
    });

//TestId-C3317
    test('[C3317] Validate installation of the latest plugin version', async ({page}) => {
        await expect(page.getByTestId('mollie-payments-for-woocommerce')).toHaveText(/7.3.9/); //TODO: remove this and retrieve the version from the plugin
    });

//TestId-C419986
    test('[C419986] Validate that the latest plugin version is displayed per UI design', async ({page}) => {
        await expect(page.getByTestId('mollie-payments-for-woocommerce')).toHaveClass(/active/);
    });

//TestId-C3318
    test.skip('[C3318] Validate uninstalling the latest plugin version', async ({page}) => {
        // Your code here...
    });

//TestId-C3319
    test('[C3319] Validate deactivation of the latest plugin version', async ({page}) => {
        await deactivateWPPlugin(page, 'Mollie Payments for WooCommerce');
        await expect(page.getByTestId('mollie-payments-for-woocommerce')).toHaveClass(/inactive/);
        //restore state
        await activateWPPlugin(page, 'Mollie Payments for WooCommerce');
    });

//TestId-C3322
    test.skip('[C3322] Validate manual plugin update', async ({page}) => {
        // Your code here...
    });

//TestId-C3328
    test.skip('[C3328] Validate automatic plugin update', async ({page}) => {
        // Your code here...
    });
