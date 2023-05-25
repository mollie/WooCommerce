const methodsConfig = require('../methodsConfig.json')


const banktransfer = methodsConfig.banktransfer;
const ideal = methodsConfig.ideal;
const creditcard = methodsConfig.creditcard;
const paypal = methodsConfig.paypal;
const normalizedName = (name) => {
    name = name.replace('\", \"mollie-payments-for-woocommerce\")', '');
    return name.replace('__(\"', '');
}
const getMethodNames = () => {
    return Object.values(methodsConfig).map((method) => normalizedName(method.defaultTitle));
};
const allMethodsIds = Object.keys(methodsConfig);
const allMethods = methodsConfig;
module.exports = {banktransfer, ideal, creditcard, paypal, normalizedName, getMethodNames, allMethods, allMethodsIds};
