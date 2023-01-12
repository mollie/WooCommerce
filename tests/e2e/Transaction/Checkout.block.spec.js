// @ts-check
const {test} = require('../Shared/base-test');
const {setOrderAPI, setPaymentAPI, markStatusInMollie, resetSettings, insertAPIKeys} = require('../Shared/mollieUtils');
const {
    wooOrderPaidPage, wooOrderDetailsPageOnPaid, wooOrderRetryPage, wooOrderDetailsPageOnFailed,
    wooOrderCanceledPage,
    wooOrderDetailsPageOnCanceled
} = require('../Shared/testMollieInWooPage');
const {addProductToCart, fillCustomerInBlockCheckout} = require('../Shared/wooUtils');
const {sharedUrl: {settingsRoot}} = require('../Shared/sharedUrl');

/**
 * @param {import('@playwright/test').Page} page
 * @param testedProduct
 * @param testedGateway
 */
async function beforePlacingOrder(page, testedProduct, testedGateway) {
    await addProductToCart(page, testedProduct.name);
    await page.goto('/checkout/');

    //Capture WooCommerce total amount
    const totalAmount = await page.innerText('.order-total > td > strong > span > bdi');

    // CUSTOMER DETAILS
    await fillCustomerInBlockCheckout(page);

    // Check testedGateway option NO ISSUERS DROPDOWN

    await page.locator(`text=${testedGateway.defaultTitle}`).click();
    // Click text=Place order
    await Promise.all([
        page.waitForNavigation(/*{ url: 'https://www.mollie.com/checkout/test-mode?method=GATEWAY&token=XXX' }*/),
        page.locator('text=Place order').click()
    ]);
    return totalAmount;
}

/**
 * @param {import('@playwright/test').Page} page
 * @param testedProduct
 * @param testedGateway
 */
async function blockCheckoutPaidTransaction(page, testedProduct, testedGateway) {
    const totalAmount = await beforePlacingOrder(page, testedProduct, testedGateway);

    // IN MOLLIE
    // Capture order number in Mollie and mark as paid
    const mollieOrder = await markStatusInMollie(page, "Paid");

    // WOOCOMMERCE ORDER PAID PAGE
    await wooOrderPaidPage(page, mollieOrder, totalAmount, testedGateway);

    // WOOCOMMERCE ORDER PAGE
    await wooOrderDetailsPageOnPaid(page, mollieOrder, testedGateway);
}

async function blockCheckoutFailedTransaction(page, testedProduct, testedGateway) {
    const totalAmount = await beforePlacingOrder(page, testedProduct, testedGateway);

    // IN MOLLIE
    // Capture order number in Mollie and mark as paid
    const mollieOrder = await markStatusInMollie(page, "Failed");

    // WOOCOMMERCE ORDER PAID PAGE
    await wooOrderRetryPage(page, mollieOrder, totalAmount, testedGateway);

    // WOOCOMMERCE ORDER PAGE
    await wooOrderDetailsPageOnFailed(page, mollieOrder, testedGateway);
}

async function blockCheckoutCancelledTransactionPending(page, testedProduct, testedGateway) {
    const totalAmount = await beforePlacingOrder(page, testedProduct, testedGateway);

    // IN MOLLIE
    // Capture order number in Mollie and mark as paid
    const mollieOrder = await markStatusInMollie(page, "Canceled");
    // WOOCOMMERCE ORDER PAID PAGE
    await wooOrderRetryPage(page, mollieOrder, totalAmount, testedGateway);

    // WOOCOMMERCE ORDER PAGE
    await wooOrderDetailsPageOnFailed(page, mollieOrder, testedGateway);
}

async function blockCheckoutCancelledTransactionCancelled(page, testedProduct, testedGateway) {
    const totalAmount = await beforePlacingOrder(page, testedProduct, testedGateway);

    // IN MOLLIE
    // Capture order number in Mollie and mark as paid
    const mollieOrder = await markStatusInMollie(page, "Canceled");
    // WOOCOMMERCE ORDER PAID PAGE
    await wooOrderCanceledPage(page, mollieOrder, totalAmount, testedGateway);

    // WOOCOMMERCE ORDER PAGE
    await wooOrderDetailsPageOnCanceled(page, mollieOrder, testedGateway);
}

test.describe('Transaction in block checkout', () => {
    test.beforeAll(async ({browser, baseURL}) => {
        const page = await browser.newPage({ baseURL: baseURL, extraHTTPHeaders: {'ngrok-skip-browser-warning': '123'}});
        await resetSettings(page);
        await insertAPIKeys(page);
    });
    test('Transaction block with Order API paid', async ({page, products, gateways}) => {
        await setOrderAPI(page);
        for (const gateway in gateways) {
            for (const product in products) {
                await blockCheckoutPaidTransaction(page, products[product], gateways[gateway]);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction block with Order API failed', async ({page, products, gateways}) => {
        for (const gateway in gateways) {
            for (const product in products) {
                await blockCheckoutFailedTransaction(page, products[product], gateways[gateway]);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction block with Order API cancelled setting as pending', async ({page, products, gateways}) => {
        //setting as pending
        await page.goto(settingsRoot + '&section=advanced');
        await page.selectOption('select#mollie-payments-for-woocommerce_order_status_cancelled_payments', 'pending');
        await Promise.all([
            page.waitForNavigation(),
            page.locator('text=Save changes').click()
        ]);
        for (const gateway in gateways) {
            for (const product in products) {
                await blockCheckoutCancelledTransactionPending(page, products[product], gateways[gateway]);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction block with Payment API paid', async ({page, products, gateways}) => {
        //Set Payment API
        await setPaymentAPI(page);
        for (const gateway in gateways) {
            for (const product in products) {
                await blockCheckoutPaidTransaction(page, products[product], gateways[gateway]);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction block with Payment API cancelled setting as pending', async ({page, products, gateways}) => {
        //setting as pending
        await page.goto(settingsRoot + '&section=advanced');
        await page.selectOption('select#mollie-payments-for-woocommerce_order_status_cancelled_payments', 'pending');
        await Promise.all([
            page.waitForNavigation(),
            page.locator('text=Save changes').click()
        ]);
        for (const gateway in gateways) {
            for (const product in products) {
                await blockCheckoutCancelledTransactionPending(page, products[product], gateways[gateway]);
            }// end loop products
        }// end loop gateways
    });
    test('Transaction block with Payment API cancelled setting as cancelled', async ({page, products, gateways}) => {
        //setting as cancelled
        await page.goto(settingsRoot + '&section=advanced');
        await page.selectOption('select#mollie-payments-for-woocommerce_order_status_cancelled_payments', 'cancelled');
        await Promise.all([
            page.waitForNavigation(),
            page.locator('text=Save changes').click()
        ]);
        for (const gateway in gateways) {
            for (const product in products) {
                await blockCheckoutCancelledTransactionCancelled(page, products[product], gateways[gateway]);
            }// end loop products
        }// end loop gateways
    });
});
