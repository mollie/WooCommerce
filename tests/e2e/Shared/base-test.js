const base = require('@playwright/test');
const {simple} = require('../Shared/products');
const {ideal, banktransfer} = require('../Shared/gateways');

exports.test = base.test.extend({
    products: [simple, { option: true }],
    gateways: [ideal, banktransfer, { option: true }],
});
