const wooOrderPaidPage = async (page, mollieOrder, totalAmount, testedGateway) => {
    // Check order number
    await expect(page.locator('li.woocommerce-order-overview__order.order')).toContainText(mollieOrder);
    // Check total amount in order
    await expect(page.locator('li.woocommerce-order-overview__total.total')).toContainText(totalAmount);
    // Check customer in billind details
    await expect(page.locator('div.woocommerce-column.woocommerce-column--1.woocommerce-column--billing-address.col-1 > address')).toContainText("Test test");
    // Check Mollie method appears
    await expect(page.locator('li.woocommerce-order-overview__payment-method.method')).toContainText(testedGateway.title);
}

const wooOrderDetailsPageOnPaid = async (page, mollieOrder, testedGateway) => {
    await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/edit.php?post_type=shop_order');
    // Check order is in status processing in order page
    await expect(page.locator('#post-' + mollieOrder + '> td.order_status.column-order_status > mark > span')).toContainText("Processing");
    await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/post.php?post=' + mollieOrder + '&action=edit');

    // Check order notes has correct text
    await expect(page.locator('#woocommerce-order-notes > div.inside > ul')).toContainText('Order completed using Mollie – ' + testedGateway.title + ' payment');
}

module.exports = {wooOrderPaidPage, wooOrderDetailsPageOnPaid}
