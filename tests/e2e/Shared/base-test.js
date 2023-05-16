const base = require('@playwright/test');
const {allProducts} = require('./products');
const {allMethods} = require('./gateways');

exports.test = base.test.extend({
    products: [allProducts, { option: true }],
    gateways: [allMethods, { option: true }],
});
