const banktransfer = {
    'id': 'banktransfer',
    'defaultTitle': 'Bank Transfer',
    'settingsDescription': '',
    'defaultDescription': '',
    'paymentFields': false,
    'instructions': true,
    'supports': [
        'products',
        'refunds',
    ],
    'filtersOnBuild': true,
    'confirmationDelayed': true,
    'SEPA': false,
    'customRedirect': true,
}
const ideal = {
    'id': 'ideal',
    'defaultTitle': 'iDeal',
    'settingsDescription': '',
    'defaultDescription': 'Select your bank',
    'paymentFields': true,
    'instructions': true,
    'supports': [
        'products',
        'refunds',
    ],
    'SEPA': true
}
const creditcard = {
    'id': 'creditcard',
    'defaultTitle': 'Credit card',
}
const paypal = {
    'id' : 'paypal',
    'defaultTitle' : 'PayPal',
    'settingsDescription' : '',
    'defaultDescription' : '',
    'paymentFields' : false,
    'instructions' : true,
    'supports' : [
        'products',
        'refunds',
    ],
    'filtersOnBuild' : false,
    'confirmationDelayed' : false,
    'SEPA' : false,
}
module.exports = {banktransfer, ideal, creditcard, paypal};
