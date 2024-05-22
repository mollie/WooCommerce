import molliePaymentMethod from './blocks/molliePaymentMethod'

(
    function ({ mollieBlockData, wc, _, jQuery}) {
        if (_.isEmpty(mollieBlockData)) {
            return;
        }
        window.onload = (event) => {
            const { registerPaymentMethod } = wc.wcBlocksRegistry;
            const { checkoutData, defaultFields } = wc.wcSettings.allSettings;
            const { billing_address, shipping_address } = checkoutData;
            const { ajaxUrl, filters, gatewayData, availableGateways } = mollieBlockData.gatewayData;
            const {useEffect} = wp.element;
            const isAppleSession = typeof window.ApplePaySession === "function"
            const isBlockEditor = !!wp?.blockEditor;

            function getCompanyField() {
                let shippingCompany = shipping_address.company ?? false;
                let billingCompany = billing_address.company ?? false;
                return shippingCompany ? shippingCompany : billingCompany;
            }

            function getPhoneField()
            {
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
                if (item.name === 'mollie_wc_gateway_applepay'  && !isBlockEditor) {
                    if ((isAppleSession && window.ApplePaySession.canMakePayments())) {
                        register();
                    }
                    return;
                }
                register();
            });
        };

    }
)(window, wc)
