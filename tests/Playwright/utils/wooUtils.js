const path = require("path");
const fs = require("fs");
const { request } = require('@playwright/test');
const wooUrls = {
    settingsPaymentTab: '/wp-admin/admin.php?page=wc-settings&tab=checkout'
}
const WooCommerceRestApi = require("@woocommerce/woocommerce-rest-api").default;
//todo: turn into fixtures and POM or use monorepo utils

async function gotoWPPage(page, url) {
    await page.goto(url);
}
async function gotoWooPaymentTab(page) {
    await gotoWPPage(page, wooUrls.settingsPaymentTab);
}
/**
 *
 * @param baseUrl
 * @param productId
 * @param productQuantity
 */
const addProductToCart = async (baseUrl, productId, productQuantity) => {
    const context = await request.newContext();
    const cartResponse = await context.post(`${baseUrl}/wp-json/wc/store/v1/cart/add-item`, {
        data: {
            id: productId,
            quantity: productQuantity
        }
    });

    // Check if the product was added successfully
    if (cartResponse.ok()) {
        //console.log('Product added to cart:', await cartResponse.json());
    } else {
        console.error('Failed to add product to cart:', cartResponse.status(), await cartResponse.text());
    }
}

const emptyCart = async (baseUrl) => {
    const context = await request.newContext();
    const cartItemsResponse = await context.get(`${baseUrl}/wp-json/wc/store/v1/cart/items`);

    if (cartItemsResponse.ok()) {
        const items = await cartItemsResponse.json();
        for (const item of items) {
            const removeResponse = await context.post(`${baseUrl}/wp-json/wc/store/v1/cart/remove-item`, {
                data: {
                    key: item.key
                }
            });
            if (!removeResponse.ok()) {
                console.error('Failed to remove item from cart:', removeResponse.status(), await removeResponse.text());
            }
        }
        //console.log('All items removed from cart');
    } else {
        console.error('Failed to retrieve cart items:', cartItemsResponse.status(), await cartItemsResponse.text());
    }
}
/**
 * @param {import('@playwright/test').Page} page
 */
const fillCustomerInCheckout = async (page, country = 'Germany') => {
    const countryIsSet = await page.getByText(country).first().isVisible()
    const canEdit = await page.getByText('Edit').first().isVisible();
    const isBlockCheckout = !page.url().includes('classic');
    const postCodes = {
        'Netherlands': '1234 AB',
        'Belgium': '1000',
    }

    if(isBlockCheckout && countryIsSet) {
        return;
    }
    if (isBlockCheckout && canEdit) {
        await page.getByText('Edit', {exact: true}).first().click();
    }
    await page.getByLabel('First name').first().fill('Julia');
    await page.getByLabel('Last name').first().fill('Callas');
    if(await page.getByText('Use same address for billing').isVisible()) {
        await page.getByLabel('Use same address for billing').first().click();
        await page.locator('#shipping-country').click();
        await page.getByRole('option', { name: country }).click();
    }else{
        await page.selectOption('select#billing_country', country);
    }


    await page.getByLabel('City').first().fill('Berlin');
    await page.getByLabel(/address|Address/).first().fill('Calle Drutal');
    await page.getByLabel(/Postcode|Postal code/).first().fill('22100');
    if(postCodes[country] !== undefined) {
        await page.getByLabel(/Postcode|Postal code/).first().fill(postCodes[country]);
    }
    await page.getByLabel('Phone').first().fill('+361234566788');
    await page.getByLabel('Email address').first().fill('test@test.com');

}

const selectPaymentMethodInCheckout = async (page, paymentMethod) => {
    await page.locator('label').filter({ hasText: paymentMethod }).first().click();
}

const placeOrderCheckout = async (page) => {
    // Click text=Place order
    await page.locator('text=Place order').click()
}

const placeOrderPayPage = async (page) => {
    // Click text=Place order
    await page.getByRole('button', { name: 'Pay for order' }).click()
}

const captureTotalAmountCheckout = async (page) => {
    return await page.innerText('.order-total > td > strong > span > bdi');
}

const parseTotalAmount = (totalAmount) => {
    // "€30.80" => 30.80
    const numberStr = totalAmount.replace(/[^\d.]/g, '');
    return parseFloat(numberStr);
}

const captureTotalAmountPayPage = async (page) => {
    const totalSelector = 'tr:last-child >> td.product-total >> .woocommerce-Price-amount.amount >> bdi';
    return await page.innerText(totalSelector);
}

const captureTotalAmountBlockCheckout = async (page) => {
    let totalLine = await page.locator('div').filter({ hasText: /^Total/ }).first()
    let totalAmount = await totalLine.innerText('.woocommerce-Price-amount amount > bdi');
    // totalAmount is "Total\n72,00 €" and we need to remove the "Total\n" part
    return totalAmount.substring(6, totalAmount.length);
}

// If we use a dump of a WP site as template for an env, the keys remain the same
const createWooCommerceInstance = (baseURL) => {
    return new WooCommerceRestApi({
        url: baseURL,
        consumerKey: process.env.WOO_REST_CONSUMER_KEY,
        consumerSecret: process.env.WOO_REST_CONSUMER_SECRET,
        version: 'wc/v3'
    });
}

const changeWooCurrency = async (baseURL, currency = 'EUR') => {
    const data = {
        value: currency
    };
    const WooCommerce = createWooCommerceInstance(baseURL);
    WooCommerce.put("settings/general/woocommerce_currency", data)
        .then((response) => {
            console.log(response.data);
        })
        .catch((error) => {
            console.log(error.response.data);
        });
}

const createManualOrder = async (page, productId, quantity=1, country='DE', postcode='', baseURL) => {
    try {
        const order = manualOrder(productId, quantity, country, postcode)
        const WooCommerce = createWooCommerceInstance(baseURL);

        const response = await WooCommerce.post("orders", order);
        const url = `/checkout/order-pay/${response.data.id}?pay_for_order=true&key=${response.data.order_key}`;
        return {
            url: url,
            orderId: response.data.id,
            orderKey: response.data.order_key
        };
    } catch (error) {
        console.log(error.response.data);
    }
}

const updateMethodSetting = async (method, payload, baseURL) => {
    method = 'mollie_wc_gateway_'+method.toLowerCase();
    try {
        const WooCommerce = createWooCommerceInstance(baseURL);

        const response = await WooCommerce.put(
            `payment_gateways/${method}`,
            payload);
        //console.log('Method setting updated:', response.data);
       return response.data;
    } catch (error) {
        console.log(error.response.data);
    }
}

const fetchOrderStatus = async (orderId, baseURL) => {
    try {
        const WooCommerce = createWooCommerceInstance(baseURL);

        const response = await WooCommerce.get(`orders/${orderId}`);
        return response.data.status; // This will contain the order's status
    } catch (error) {
        console.log('Error fetching order status:', error);
        return null;
    }
};
const fetchOrderNotes = async (orderId, baseURL) => {
    try {
        const WooCommerce = createWooCommerceInstance(baseURL);

        const response = await WooCommerce.get(`orders/${orderId}/notes`);
        return response.data;  // This will contain an array of order notes
    } catch (error) {
        console.log('Error fetching order notes:', error);
        return null;
    }
};

const getLogByName = async (name, dirname) => {
    const currentDate = new Date().toISOString().split('T')[0];
    // Construct the relative path to the log file
    const logsDirectory = path.join(dirname, '..', '..', '..', '.ddev', 'wordpress', 'wp-content', 'uploads', 'wc-logs');
    const files = fs.readdirSync(logsDirectory);
    const matchingFiles = files.filter(file => file.includes(`${name}-${currentDate}-`));
    // Select the first matching file
    const logFileName = matchingFiles[0];
    const logFilePath = path.join(logsDirectory, logFileName);
    return fs.readFileSync(logFilePath, 'utf-8');
}

const manualOrder = (productId, productQuantity, country, postcode) => {
    return  {
        set_paid: false,
        billing: {
            first_name: "Tester",
            last_name: "testing",
            address_1: "969 Market",
            address_2: "",
            city: "San Francisco",
            state: "CA",
            postcode: postcode,
            country: country,
            email: "john.doe@example.com",
            phone: "(555) 555-5555"
        },
        shipping: {
            first_name: "John",
            last_name: "Doe",
            address_1: "969 Market",
            address_2: "",
            city: "San Francisco",
            state: "CA",
            postcode: postcode,
            country: country
        },
        line_items: [
            {
                product_id: productId,
                quantity: productQuantity
            }
        ],
        shipping_lines: [
            {
                method_id: "flat_rate",
                method_title: "Flat Rate",
                total: "0.00"
            }
        ]
    };
};


module.exports = {
    addProductToCart,
    fillCustomerInCheckoutBlock: fillCustomerInCheckout,
    gotoWooPaymentTab,
    placeOrderCheckout,
    emptyCart,
    placeOrderPayPage,
    selectPaymentMethodInCheckout,
    captureTotalAmountCheckout,
    captureTotalAmountBlockCheckout,
    captureTotalAmountPayPage,
    createManualOrder,
    getLogByName,
    fetchOrderStatus,
    fetchOrderNotes,
    updateMethodSetting,
    parseTotalAmount,
    changeWooCurrency
}
