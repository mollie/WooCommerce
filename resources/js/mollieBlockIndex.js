import molliePaymentMethod from './blocks/molliePaymentMethod'
import ApplePayButtonComponent from './blocks/ApplePayButtonComponent'
import ApplePayButtonEditorComponent from './blocks/ApplePayButtonEditorComponent'

(
    function ({mollieBlockData, wc, _, jQuery}) {
        if (_.isEmpty(mollieBlockData)) {
            return;
        }
        const {registerPaymentMethod} = wc.wcBlocksRegistry;
        const {checkoutData, defaultFields} = wc.wcSettings.allSettings;
        let billing_address, shipping_address;
        if (checkoutData) {
            ({billing_address, shipping_address} = checkoutData);
        } else {
            billing_address = {};
            shipping_address = {};
        }
        const {ajaxUrl, filters, gatewayData, availableGateways} = mollieBlockData.gatewayData;
        const {useEffect} = wp.element;
        const isAppleSession = typeof window.ApplePaySession === "function"
        const isBlockEditor = !!wp?.blockEditor;

        function getCompanyField() {
            let shippingCompany = shipping_address.company ?? false;
            let billingCompany = billing_address.company ?? false;
            return shippingCompany ? shippingCompany : billingCompany;
        }

        function getPhoneField() {
            const phoneFieldDataset = document.querySelector('[data-show-phone-field]');
            if (!phoneFieldDataset) {
                return true;
            }
            return phoneFieldDataset.dataset.showPhoneField !== "false"
        }

            const isCompanyFieldVisible = getCompanyField();
            const companyNameString = defaultFields.company.label
            const isPhoneFieldVisible = getPhoneField();
            const phoneString = defaultFields.phone.label
            let requiredFields = {
                'companyNameString': companyNameString,
                'phoneString': phoneString,
            }
            gatewayData.forEach(item => {
                let register = () => registerPaymentMethod(molliePaymentMethod(useEffect, ajaxUrl, filters, gatewayData, availableGateways, item, jQuery, requiredFields, isCompanyFieldVisible, isPhoneFieldVisible));
                if (item.name === 'mollie_wc_gateway_applepay') {
                    const {isExpressEnabled} = item;
                    if ((isAppleSession && window.ApplePaySession.canMakePayments())) {
                        register();
                        if (isExpressEnabled !== true) {
                            return;
                        }
                        const {registerExpressPaymentMethod} = wc.wcBlocksRegistry;
                        registerExpressPaymentMethod({
                            name: 'mollie_wc_gateway_applepay_express',
                            title: 'Apple Pay Express button',
                            description: 'Apple Pay Express button',
                            content: <ApplePayButtonComponent />,
                            edit: <ApplePayButtonEditorComponent />,
                            ariaLabel: 'Apple Pay',
                            canMakePayment: () => true,
                            paymentMethodId: 'mollie_wc_gateway_applepay',
                            gatewayId: 'mollie_wc_gateway_applepay',
                            supports: {
                                features: ['products'],
                                style: ['height', 'borderRadius']
                            },
                        })
                    }
                    return;
                }
                register();
            });

    }
)(window, wc)
