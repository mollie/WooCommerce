const { expect } = require('@playwright/test');

const wooOrderPaidPage = async (page, mollieOrder, totalAmount, testedGateway) => {
    // Check order number
    await expect(page.locator('li.woocommerce-order-overview__order.order')).toContainText(mollieOrder);
    // Check total amount in order
    await expect(page.locator('li.woocommerce-order-overview__total.total > strong > span > bdi')).toContainText(totalAmount);

    if(testedGateway.id !== 'paypal'){
        // Check customer in billing details
        await expect(page.locator('section.woocommerce-customer-details > address')).toContainText("Julia Callas");
    }
    // Check Mollie method appears
    await expect(page.locator('li.woocommerce-order-overview__payment-method.method > strong')).toContainText(testedGateway.defaultTitle);
}

const wooOrderRetryPage = async (page, mollieOrder, totalAmount, testedGateway) => {
    // Check we are in retry page
    const regex = new RegExp(`${process.env.E2E_URL_TESTSITE}/checkout/order-pay/${mollieOrder}.`);
    await expect(page).toHaveURL(regex);

}

const wooOrderDetailsPageOnPaid = async (page, mollieOrder, testedGateway) => {
    await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/edit.php?post_type=shop_order');
    // Check order is in status processing in order page
    await expect(page.locator('#post-' + mollieOrder + '> td.order_status.column-order_status > mark > span')).toContainText("Processing");
    await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/post.php?post=' + mollieOrder + '&action=edit');

    // Check order notes has correct text
    await expect(page.locator('#woocommerce-order-notes > div.inside > ul')).toContainText('Order completed using Mollie – ' + testedGateway.defaultTitle + ' payment');
}

const wooOrderDetailsPageOnFailed = async (page, mollieOrder, testedGateway) => {
    await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/edit.php?post_type=shop_order');
    // Check order is in status processing in order page
    await expect(page.locator('#post-' + mollieOrder + '> td.order_status.column-order_status > mark > span')).toContainText("Pending payment");
    await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/post.php?post=' + mollieOrder + '&action=edit');

    // Check order notes has correct text
    await expect(page.locator('#woocommerce-order-notes > div.inside > ul')).toContainText(testedGateway.id + ' payment started');
}

module.exports = {wooOrderPaidPage, wooOrderDetailsPageOnPaid, wooOrderRetryPage, wooOrderDetailsPageOnFailed}
