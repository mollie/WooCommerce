// @ts-check
const {test, expect} = require('@playwright/test');
const GATEWAYS = {
    'banktransfer': {
        'title': 'Bank Transfer',

    }
}
const PRODUCTS = {
    'simple': {
        'name': 'simple_taxes',
        'price': '24,33€'
    }
}

/**
 * @param {import('@playwright/test').Page} page
 */
async function loginAdmin(page) {
    await page.goto(process.env.E2E_URL_TESTSITE + '/wp-login.php');
    await page.locator('#user_pass').fill(process.env.ADMIN_PASS);
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Log in').click()
    ]);
}

/**
 * @param {import('@playwright/test').Page} page
 */
async function setOrderAPI(page) {
    await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section=advanced');
    await page.selectOption('select#mollie-payments-for-woocommerce_api_switch', 'order')
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Save changes').click()
    ]);
}

/**
 * @param {import('@playwright/test').Page} page
 */
async function setPaymentAPI(page) {
    await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section=advanced');
    await page.selectOption('select#mollie-payments-for-woocommerce_api_switch', 'payment')
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Save changes').click()
    ]);
}

async function addProductToCart(page, testedProduct) {
    // Go to shop
    await page.goto(process.env.E2E_URL_TESTSITE + '/shop/');
    // Add product to cart
    const productCartButton = testedProduct.name;
    await page.locator('[data-product_sku="' + productCartButton + '"]').click();
}

async function fillCustomerInCheckout(page) {
    // Fill input[name="billing_first_name"]
    await page.locator('input[name="billing_first_name"]').fill('Test');
    // Fill input[name="billing_last_name"]
    await page.locator('input[name="billing_last_name"]').fill('test');
}

async function markPaidInMollie(page) {
    const mollieHeader = await page.innerText('.header__info');
    const mollieOrder = mollieHeader.substring(6, mollieHeader.length)
    await page.locator('text=Paid').click();
    await page.locator('text=Continue').click();
    return mollieOrder;
}

async function wooOrderPaidPage(page, mollieOrder, totalAmount, testedGateway) {
    // Check order number
    await expect(page.locator('li.woocommerce-order-overview__order.order')).toContainText(mollieOrder);
    // Check total amount in order
    await expect(page.locator('li.woocommerce-order-overview__total.total')).toContainText(totalAmount);
    // Check customer in billind details
    await expect(page.locator('div.woocommerce-column.woocommerce-column--1.woocommerce-column--billing-address.col-1 > address')).toContainText("Test test");
    // Check Mollie method appears
    await expect(page.locator('li.woocommerce-order-overview__payment-method.method')).toContainText(testedGateway.title);
}

async function wooOrderDetailsPageOnPaid(page, mollieOrder, testedGateway) {
    await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/edit.php?post_type=shop_order');
    // Check order is in status processing in order page
    await expect(page.locator('#post-' + mollieOrder + '> td.order_status.column-order_status > mark > span')).toContainText("Processing");
    await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/post.php?post=' + mollieOrder + '&action=edit');

    // Check order notes has correct text
    await expect(page.locator('#woocommerce-order-notes > div.inside > ul')).toContainText('Order completed using Mollie – ' + testedGateway.title + ' payment');
}

/**
 * @param {import('@playwright/test').Page} page
 * @param testedProduct
 * @param testedGateway
 */
async function classicCheckoutPaidTransaction(page, testedProduct, testedGateway) {
    await addProductToCart(page, testedProduct);

    // Go to checkout
    await Promise.all([
        page.waitForNavigation(/*{ url: 'https://www.mollie.com/checkout/test-mode?method=GATEWAY&token=XXX' }*/),
        await page.locator('text=Checkout').first().click()
    ]);

    await expect(page).toHaveURL(process.env.E2E_URL_TESTSITE + '/checkout/');
    //Capture WooCommerce total amount
    const totalAmount = await page.innerText('.order-total > td > strong > span > bdi');

    // CUSTOMER DETAILS
    await fillCustomerInCheckout(page);

    // Check testedGateway option NO ISSUERS DROPDOWN
    await page.locator('text=' + testedGateway.title).check();
    // Click text=Place order
    await Promise.all([
        page.waitForNavigation(/*{ url: 'https://www.mollie.com/checkout/test-mode?method=GATEWAY&token=XXX' }*/),
        page.locator('text=Place order').click()
    ]);

    // IN MOLLIE
    // Capture order number in Mollie and mark as paid
    const mollieOrder = await markPaidInMollie(page);

    // WOOCOMMERCE ORDER PAID PAGE
    await wooOrderPaidPage(page, mollieOrder, totalAmount, testedGateway);

    // WOOCOMMERCE ORDER PAGE
    await wooOrderDetailsPageOnPaid(page, mollieOrder, testedGateway);
}

async function classicCheckoutFailedTransaction(page, testedProduct, testedGateway) {
    await addProductToCart(page, testedProduct);

    // Go to checkout
    await Promise.all([
        page.waitForNavigation(/*{ url: 'https://www.mollie.com/checkout/test-mode?method=GATEWAY&token=XXX' }*/),
        await page.locator('text=Checkout').first().click()
    ]);

    await expect(page).toHaveURL(process.env.E2E_URL_TESTSITE + '/checkout/');
    //Capture WooCommerce total amount
    const totalAmount = await page.innerText('.order-total > td > strong > span > bdi');

    // CUSTOMER DETAILS
    await fillCustomerInCheckout(page);

    // Check testedGateway option NO ISSUERS DROPDOWN
    await page.locator('text=' + testedGateway.title).check();
    // Click text=Place order
    await Promise.all([
        page.waitForNavigation(/*{ url: 'https://www.mollie.com/checkout/test-mode?method=GATEWAY&token=XXX' }*/),
        page.locator('text=Place order').click()
    ]);

    // IN MOLLIE
    // Capture order number in Mollie and mark as paid
    const mollieOrder = await markFailedInMollie(page);

    // WOOCOMMERCE ORDER PAID PAGE
    await wooOrderRetryPage(page, mollieOrder, totalAmount, testedGateway);

    // WOOCOMMERCE ORDER PAGE
    await wooOrderDetailsPageOnFailed(page, mollieOrder, testedGateway);
}

async function classicCheckoutCancelledTransactionPending(page, testedProduct, testedGateway) {

}

async function classicCheckoutCancelledTransactionCancelled(page, testedProduct, testedGateway) {

}

async function classicCheckoutPaidTransactionFullRefund(page, testedProduct, testedGateway) {
    await classicCheckoutPaidTransaction(page, testedProduct, testedGateway);
        //in order page select quantity
    //refund
}

async function classicCheckoutPaidTransactionPartialRefund(page, testedProduct, testedGateway) {
    await classicCheckoutPaidTransaction(page, testedProduct, testedGateway);
    //in order page select partial amount
    //refund
}

async function classicCheckoutExpiredTransaction(page, testedProduct, testedGateway) {

}

test.describe('Transaction in classic checkout', () => {
    test('Transaction with Order API paid', async ({page}) => {
        await loginAdmin(page);
        await setOrderAPI(page);
        for (const key in GATEWAYS) {
            let testedGateway = GATEWAYS[key]
            for (const key in PRODUCTS) {
                let testedProduct = PRODUCTS[key]
                await classicCheckoutPaidTransaction(page, testedProduct, testedGateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction with Order API failed', async ({page}) => {
        await loginAdmin(page);
        await setOrderAPI(page);
        for (const key in GATEWAYS) {
            let testedGateway = GATEWAYS[key]
            for (const key in PRODUCTS) {
                let testedProduct = PRODUCTS[key]
                await classicCheckoutFailedTransaction(page, testedProduct, testedGateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction with Order API cancelled setting as pending', async ({page}) => {
        await loginAdmin(page);
        await setOrderAPI(page);
        //setting as pending
        await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section=advanced');
        await page.selectOption('select#mollie-payments-for-woocommerce_order_status_cancelled_payments', 'pending');
        await Promise.all([
            page.waitForNavigation(),
            page.locator('text=Save changes').click()
        ]);
        for (const key in GATEWAYS) {
            let testedGateway = GATEWAYS[key]
            for (const key in PRODUCTS) {
                let testedProduct = PRODUCTS[key]
                await classicCheckoutCancelledTransactionPending(page, testedProduct, testedGateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction with Order API cancelled setting as cancelled', async ({page}) => {
        await loginAdmin(page);
        await setOrderAPI(page);
        //setting as cancelled
        await page.goto(process.env.E2E_URL_TESTSITE + '/wp-admin/admin.php?page=wc-settings&tab=mollie_settings&section=advanced');
        await page.selectOption('select#mollie-payments-for-woocommerce_order_status_cancelled_payments', 'pending');
        await Promise.all([
            page.waitForNavigation(),
            page.locator('text=Save changes').click()
        ]);
        for (const key in GATEWAYS) {
            let testedGateway = GATEWAYS[key]
            for (const key in PRODUCTS) {
                let testedProduct = PRODUCTS[key]
                await classicCheckoutCancelledTransactionCancelled(page, testedProduct, testedGateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction full refund Order', async ({page}) => {
        await loginAdmin(page);
        await setOrderAPI(page);
        for (const key in GATEWAYS) {
            let testedGateway = GATEWAYS[key]
            for (const key in PRODUCTS) {
                let testedProduct = PRODUCTS[key]
                await classicCheckoutPaidTransactionFullRefund(page, testedProduct, testedGateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction partial refund Order', async ({page}) => {
        await loginAdmin(page);
        await setOrderAPI(page);
        for (const key in GATEWAYS) {
            let testedGateway = GATEWAYS[key]
            for (const key in PRODUCTS) {
                let testedProduct = PRODUCTS[key]
                await classicCheckoutPaidTransactionPartialRefund(page, testedProduct, testedGateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction with Order API expired', async ({page}) => {
        await loginAdmin(page);
        await setOrderAPI(page);
        for (const key in GATEWAYS) {
            let testedGateway = GATEWAYS[key]
            for (const key in PRODUCTS) {
                let testedProduct = PRODUCTS[key]
                await classicCheckoutExpiredTransaction(page, testedProduct, testedGateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction with Payment API paid', async ({page}) => {
        //login as Admin
        await loginAdmin(page);
        //Set Payment API
        await setPaymentAPI(page);
        for (const key in GATEWAYS) {
            let testedGateway = GATEWAYS[key]
            for (const key in PRODUCTS) {
                let testedProduct = PRODUCTS[key]
                await classicCheckoutPaidTransaction(page, testedProduct, testedGateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction with Payment API cancelled setting as pending', async ({page}) => {
        //login as Admin
        await loginAdmin(page);
        //Set Payment API
        await setPaymentAPI(page);
        //setting as pending
        await page.selectOption('select#mollie-payments-for-woocommerce_order_status_cancelled_payments', 'pending');
        await Promise.all([
            page.waitForNavigation(),
            page.locator('text=Save changes').click()
        ]);
        for (const key in GATEWAYS) {
            let testedGateway = GATEWAYS[key]
            for (const key in PRODUCTS) {
                let testedProduct = PRODUCTS[key]
                await classicCheckoutCancelledTransactionPending(page, testedProduct, testedGateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction with Payment API cancelled setting as cancelled', async ({page}) => {
        //login as Admin
        await loginAdmin(page);
        //Set Payment API
        await setPaymentAPI(page);
        //setting as cancelled
        await page.selectOption('select#mollie-payments-for-woocommerce_order_status_cancelled_payments', 'pending');
        await Promise.all([
            page.waitForNavigation(),
            page.locator('text=Save changes').click()
        ]);
        for (const key in GATEWAYS) {
            let testedGateway = GATEWAYS[key]
            for (const key in PRODUCTS) {
                let testedProduct = PRODUCTS[key]
                await classicCheckoutCancelledTransactionCancelled(page, testedProduct, testedGateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction full refund Payment', async ({page}) => {
        await loginAdmin(page);
        await setPaymentAPI(page);
        for (const key in GATEWAYS) {
            let testedGateway = GATEWAYS[key]
            for (const key in PRODUCTS) {
                let testedProduct = PRODUCTS[key]
                await classicCheckoutPaidTransactionFullRefund(page, testedProduct, testedGateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction partial refund Payment', async ({page}) => {
        await loginAdmin(page);
        await setPaymentAPI(page);
        for (const key in GATEWAYS) {
            let testedGateway = GATEWAYS[key]
            for (const key in PRODUCTS) {
                let testedProduct = PRODUCTS[key]
                await classicCheckoutPaidTransactionPartialRefund(page, testedProduct, testedGateway);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction with Payment API expired', async ({page}) => {
        await loginAdmin(page);
        await setPaymentAPI(page);
        for (const key in GATEWAYS) {
            let testedGateway = GATEWAYS[key]
            for (const key in PRODUCTS) {
                let testedProduct = PRODUCTS[key]
                await classicCheckoutExpiredTransaction(page, testedProduct, testedGateway);
            }// end loop products
        }// end loop gateways
    });
});
