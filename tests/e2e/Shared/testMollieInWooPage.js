const { expect } = require('@playwright/test');

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
    await expect(page.getByRole('strong').filter({ hasText: testedGateway.defaultTitle })).toBeVisible();
}

export const wooOrderRetryPage = async (page, mollieOrder, totalAmount, testedGateway) => {
    // Check we are in retry page
    const regex = new RegExp(/checkout\/order-pay/);
    await expect(page).toHaveURL(regex);
}

export const wooOrderCanceledPage = async (page, mollieOrder, totalAmount, testedGateway) => {
    await expect(page.locator('#wp--skip-link--target > div.wp-container-7.entry-content.wp-block-post-content > div > div > p')).toContainText('cancelled');
}

export const wooOrderDetailsPageOnPaid = async (page, mollieOrder, testedGateway) => {
    await page.goto('/wp-admin/edit.php?post_type=shop_order');
    // Check order is in status processing in order page
    await expect(page.locator('#post-' + mollieOrder + '> td.order_status.column-order_status > mark > span')).toContainText("Processing");
    await page.goto('/wp-admin/post.php?post=' + mollieOrder + '&action=edit');

    // Check order notes has correct text
    await expect(page.getByText('Order completed using Mollie – ' + testedGateway.defaultTitle + ' payment')).toBeVisible();
}

export const wooOrderDetailsPageVirtual = async (page, mollieOrder, testedGateway) => {
    await page.goto('/wp-admin/edit.php?post_type=shop_order');
    // Check order is in status processing in order page
    await expect(page.locator('#post-' + mollieOrder + '> td.order_status.column-order_status > mark > span')).toContainText("Completed");
    await page.goto('/wp-admin/post.php?post=' + mollieOrder + '&action=edit');

    // Check order notes has correct text
    await expect(page.locator('#woocommerce-order-notes > div.inside > ul')).toContainText('Order completed using Mollie – ' + testedGateway.defaultTitle + ' payment');
}

export const wooOrderDetailsPageOnFailed = async (page, mollieOrder, testedGateway) => {
    await page.goto('/wp-admin/edit.php?post_type=shop_order');
    // Check order is in status processing in order page
    await expect(page.locator('#post-' + mollieOrder + '> td.order_status.column-order_status > mark > span')).toContainText("Pending payment");
    await page.goto('/wp-admin/post.php?post=' + mollieOrder + '&action=edit');

    // Check order notes has correct text
    await expect(page.locator('#woocommerce-order-notes > div.inside > ul')).toContainText(testedGateway.id + ' payment started');
}
export const wooOrderDetailsPageOnCanceled = async (page, mollieOrder, testedGateway) => {
    await page.goto('/wp-admin/edit.php?post_type=shop_order');
    // Check order is in status processing in order page
    await expect(page.locator('#post-' + mollieOrder + '> td.order_status.column-order_status > mark > span')).toContainText("Cancelled");
    await page.goto('/wp-admin/post.php?post=' + mollieOrder + '&action=edit');

    // Check order notes has correct text
    await expect(page.locator('#woocommerce-order-notes > div.inside > ul')).toContainText(testedGateway.id + ' payment started');
}
