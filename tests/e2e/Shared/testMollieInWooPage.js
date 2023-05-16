import {normalizedName} from "./gateways";

const { expect } = require('@playwright/test');
const {sharedUrl} = require("./sharedUrl");

async function gotoWPPage(page, url) {
    await page.goto(url);
}

async function gotoMollieGeneralSettings(page) {
    await gotoWPPage(page, sharedUrl.mollieSettingsTab);
}

export const wooOrderPaidPage = async (page, mollieOrder, totalAmount, testedGateway) => {
    // Check order number
    await expect(page.locator('li.woocommerce-order-overview__order.order')).toContainText(mollieOrder);
    // Check total amount in order
    await expect(page.locator('li.woocommerce-order-overview__total.total > strong > span > bdi')).toContainText(totalAmount);

    if(testedGateway.id !== 'paypal'){
        // Check customer in billing details
        await expect(page.getByText('My company nameJulia CallasCalle Drutal22100 BerlinGermany 1234566788 test@test.')).toBeVisible;
    }
    // Check Mollie method appears
    const methodName = normalizedName(testedGateway.defaultTitle);
    await expect(page.getByRole('cell', { name:  methodName})).toBeVisible();
}

export const wooOrderRetryPage = async (page) => {
    // Check we are in retry page
    const regex = new RegExp(/checkout\/order-pay/);
    await expect(page).toHaveURL(regex);
}

export const wooOrderCanceledPage = async (page, mollieOrder, totalAmount, testedGateway) => {
    await expect(page.locator('#wp--skip-link--target > div.wp-container-7.entry-content.wp-block-post-content > div > div > p')).toContainText('cancelled');
}

export const wooOrderDetailsPage = async (page, mollieOrder, testedGateway, status, notice) => {
    await page.goto('/wp-admin/edit.php?post_type=shop_order');
    // Check order is in status processing in order page
    await expect(page.locator('#post-' + mollieOrder + '> td.order_status.column-order_status > mark > span')).toContainText(status);
    await page.goto('/wp-admin/post.php?post=' + mollieOrder + '&action=edit');

    // Check order notes has correct text
    await expect(page.locator('#woocommerce-order-notes > div.inside > ul')).toContainText(notice);
}
