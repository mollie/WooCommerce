// @ts-check
const {test, expect} = require('@playwright/test');
const { loginAdmin } = require('../Shared/wpUtils');
const {setOrderAPI, setPaymentAPI, markPaidInMollie} = require('../Shared/mollieUtils');
const {wooOrderPaidPage, wooOrderDetailsPageOnPaid} = require('../Shared/testMollieInWooPage');
const {addProductToCart, fillCustomerInBlockCheckout} = require('../Shared/wooUtils');

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
 * @param testedProduct
 * @param testedGateway
 */
async function blockCheckoutPaidTransaction(page, testedProduct, testedGateway) {
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
    await fillCustomerInBlockCheckout(page);

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

async function blockCheckoutFailedTransaction(page, testedProduct, testedGateway) {
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
    await fillCustomerInBlockCheckout(page);

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

async function blockCheckoutCancelledTransactionPending(page, testedProduct, testedGateway) {

}

async function blockCheckoutCancelledTransactionCancelled(page, testedProduct, testedGateway) {

}

async function blockCheckoutPaidTransactionFullRefund(page, testedProduct, testedGateway) {
    await blockCheckoutPaidTransaction(page, testedProduct, testedGateway);
        //in order page select quantity
    //refund
}

async function blockCheckoutPaidTransactionPartialRefund(page, testedProduct, testedGateway) {
    await blockCheckoutPaidTransaction(page, testedProduct, testedGateway);
    //in order page select partial amount
    //refund
}

async function blockCheckoutExpiredTransaction(page, testedProduct, testedGateway) {

}

test.describe('Transaction in classic checkout', () => {
    test('Transaction with Order API paid', async ({page}) => {
        await loginAdmin(page);
        await setOrderAPI(page);
        for (const key in GATEWAYS) {
            let testedGateway = GATEWAYS[key]
            for (const key in PRODUCTS) {
                let testedProduct = PRODUCTS[key]
                await blockCheckoutPaidTransaction(page, testedProduct, testedGateway);
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
                await blockCheckoutFailedTransaction(page, testedProduct, testedGateway);
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
                await blockCheckoutCancelledTransactionPending(page, testedProduct, testedGateway);
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
                await blockCheckoutCancelledTransactionCancelled(page, testedProduct, testedGateway);
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
                await blockCheckoutPaidTransactionFullRefund(page, testedProduct, testedGateway);
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
                await blockCheckoutPaidTransactionPartialRefund(page, testedProduct, testedGateway);
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
                await blockCheckoutExpiredTransaction(page, testedProduct, testedGateway);
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
                await blockCheckoutPaidTransaction(page, testedProduct, testedGateway);
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
                await blockCheckoutCancelledTransactionPending(page, testedProduct, testedGateway);
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
                await blockCheckoutCancelledTransactionCancelled(page, testedProduct, testedGateway);
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
                await blockCheckoutPaidTransactionFullRefund(page, testedProduct, testedGateway);
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
                await blockCheckoutPaidTransactionPartialRefund(page, testedProduct, testedGateway);
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
                await blockCheckoutExpiredTransaction(page, testedProduct, testedGateway);
            }// end loop products
        }// end loop gateways
    });
});
